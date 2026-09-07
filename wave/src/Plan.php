<?php

namespace Wave;

use Exception;
use App\Models\BillingPlanPrice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function billingPrices(): HasMany
    {
        return $this->hasMany(BillingPlanPrice::class);
    }

    public function billingPrice(string $provider, string $cycle): ?BillingPlanPrice
    {
        if ($this->relationLoaded('billingPrices')) {
            $price = $this->billingPrices
                ->first(fn (BillingPlanPrice $price): bool => $price->provider === $provider
                    && $price->cycle === $cycle);

            return $price?->active ? $price : null;
        }

        return $this->billingPrices()
            ->where('provider', $provider)
            ->where('cycle', $cycle)
            ->where('active', true)
            ->first();
    }

    public function externalPriceId(string $provider, string $cycle): ?string
    {
        $configuredPrice = $this->relationLoaded('billingPrices')
            ? $this->billingPrices->first(fn (BillingPlanPrice $price): bool => $price->provider === $provider
                && $price->cycle === $cycle)
            : $this->billingPrices()
                ->where('provider', $provider)
                ->where('cycle', $cycle)
                ->first();

        if ($configuredPrice) {
            return $configuredPrice->active ? $configuredPrice->external_id : null;
        }

        if ($provider !== 'stripe') {
            return null;
        }

        return match ($cycle) {
            'month' => $this->monthly_price_id,
            'year' => $this->yearly_price_id,
            default => null,
        };
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Get all active plans with caching
     */
    public static function getActivePlans()
    {
        // Use cache if available, otherwise direct query
        if (app()->bound('cache')) {
            try {
                return Cache::remember('wave_active_plans', 1800, function () {
                    return self::where('active', 1)->with('role')->get();
                });
            } catch (Exception $e) {
                // Fallback to direct query if cache fails
            }
        }

        return self::where('active', 1)->with('role')->get();
    }

    /**
     * Get plan by name with caching
     */
    public static function getByName($name)
    {
        // Use cache if available, otherwise direct query
        if (app()->bound('cache')) {
            try {
                return Cache::remember("wave_plan_{$name}", 1800, function () use ($name) {
                    return self::where('name', $name)->with('role')->first();
                });
            } catch (Exception $e) {
                // Fallback to direct query if cache fails
            }
        }

        return self::where('name', $name)->with('role')->first();
    }

    /**
     * Clear plan cache
     */
    public static function clearCache()
    {
        // Only clear cache if it's available
        if (app()->bound('cache')) {
            try {
                Cache::forget('wave_active_plans');
                $plans = self::pluck('name');
                foreach ($plans as $planName) {
                    Cache::forget("wave_plan_{$planName}");
                }
            } catch (Exception $e) {
                // Silently handle cache clearing failures
            }
        }
    }
}
