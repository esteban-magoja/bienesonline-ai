<?php

namespace Wave;

use App\Models\User;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_LEGACY_CANCELED = 'cancelled';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'billable_type',
        'billable_id',
        'plan_id',
        'vendor_slug',
        'vendor_product_id',
        'vendor_transaction_id',
        'vendor_customer_id',
        'vendor_subscription_id',
        'cycle',
        'status',
        'seats',
        'trial_ends_at',
        'ends_at',
        'cancelled_at',
        'last_payment_at',
        'next_payment_at',
        'cancel_url',
        'update_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * The user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('wave.user_model', User::class), 'billable_id');
    }

    public function cancel(): void
    {
        app(SubscriptionLifecycleService::class)->cancel($this);
    }

    public function suspend(): void
    {
        app(SubscriptionLifecycleService::class)->suspend($this);
    }

    public function markPastDue(): void
    {
        app(SubscriptionLifecycleService::class)->markPastDue($this);
    }

    public function activate(): void
    {
        app(SubscriptionLifecycleService::class)->activate($this);
    }

    /**
     * The plan that belongs to the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
