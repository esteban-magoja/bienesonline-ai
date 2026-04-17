<?php

namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Wave\Traits\HasProfileKeyValues;
use Wave\User as WaveUser;

class User extends WaveUser implements HasLocalePreference
{
    use HasProfileKeyValues, Notifiable;

    public $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar',
        'password',
        'role_id',
        'verification_code',
        'verified',
        'trial_ends_at',
        'agency',
        'movil',
        'address',
        'city',
        'state',
        'country',
        'locale',
        'terms_accepted',
        'terms_accepted_at',
        'whatsapp_opt_in',
        'whatsapp_opt_in_at',
        'movil_verified_at',
        'movil_verification_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'whatsapp_opt_in' => 'boolean',
        'whatsapp_opt_in_at' => 'datetime',
        'movil_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Listen for the creating event of the model
        static::creating(function ($user) {
            // Check if the username attribute is empty
            if (empty($user->username)) {
                // Use the name to generate a slugified username
                $base = !empty($user->agency) ? $user->agency : $user->name;
                $username = Str::slug($base, '');
                $i = 1;
                while (self::where('username', $username)->exists()) {
                    $username = Str::slug($base, '').$i;
                    $i++;
                }
                $user->username = $username;
            }
        });

        // Listen for the created event of the model
        static::created(function ($user) {
            // Remove all roles
            $user->syncRoles([]);
            // Assign the default role
            $user->assignRole(config('wave.default_user_role', 'registered'));
        });
    }

    /**
     * Get the property listings for the user.
     */
    public function propertyListings()
    {
        return $this->hasMany(\App\Models\PropertyListing::class);
    }

    /**
     * Get the admin notes for the user.
     */
    public function userNotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\UserNote::class)->latest();
    }

    /**
     * Get the property requests for the user.
     */
    public function propertyRequests()
    {
        return $this->hasMany(\App\Models\PropertyRequest::class);
    }

    /**
     * Check if user has accepted terms and conditions.
     */
    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted;
    }

    /**
     * Accept terms and conditions.
     */
    public function acceptTerms(): void
    {
        $this->update([
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ]);
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmail);
    }

    public function preferredLocale()
    {
        return in_array($this->locale, ['es', 'en']) ? $this->locale : config('app.locale', 'es');
    }
}
