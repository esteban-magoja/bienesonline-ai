<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Wave\Traits\HasProfileKeyValues;
use Wave\User as WaveUser;

class User extends WaveUser
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
    ];

    protected static function boot()
    {
        parent::boot();

        // Listen for the creating event of the model
        static::creating(function ($user) {
            // Check if the username attribute is empty
            if (empty($user->username)) {
                // Use the name to generate a slugified username
                $username = Str::slug($user->name, '');
                $i = 1;
                while (self::where('username', $username)->exists()) {
                    $username = Str::slug($user->name, '').$i;
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
}
