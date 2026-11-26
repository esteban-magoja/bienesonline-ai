<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PhoneVerificationNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

uses(DatabaseTransactions::class);

function makeVerifiedPhoneUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'terms_accepted'           => true,
        'avatar'                   => 'demo/default.png',
        'movil'                    => '+5491112345678',
        'whatsapp_opt_in'          => true,
        'movil_verified_at'        => now(),
        'movil_verification_token' => null,
    ], $overrides));
}

describe('phone number change', function () {
    it('removes the verification when the phone number changes', function () {
        $user = makeVerifiedPhoneUser(['movil_verification_token' => 'old-token']);

        $user->update(['movil' => '+5491187654321']);

        expect($user->fresh())
            ->movil_verified_at->toBeNull()
            ->movil_verification_token->toBeNull();
    });

    it('keeps the verification when only the phone format changes', function () {
        $user = makeVerifiedPhoneUser();

        $user->update(['movil' => '+54 9 11 1234-5678']);

        expect($user->fresh()->hasVerifiedPhone())->toBeTrue();
    });

    it('keeps the verification when the number and the verification are set together', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => now()->subDay()]);

        $user->update(['movil' => '+5491187654321', 'movil_verified_at' => now()]);

        expect($user->fresh()->hasVerifiedPhone())->toBeTrue();
    });

    it('does not consider a user without phone as verified', function () {
        $user = makeVerifiedPhoneUser(['movil' => null]);

        expect($user->hasVerifiedPhone())->toBeFalse();
    });
});

describe('verify button in profile', function () {
    beforeEach(fn () => Notification::fake());

    it('shows the verify button to users with an unverified phone', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => null]);

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk()
            ->assertSee(__('settings.profile.phone_send_verification'));
    });

    it('shows the verify box in the dashboard to users with an unverified phone', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => null]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(__('settings.profile.phone_verification_title'))
            ->assertSee(__('settings.profile.phone_send_verification'));
    });

    it('does not show the verify box in the dashboard to users with a verified phone', function () {
        $user = makeVerifiedPhoneUser();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('settings.profile.phone_verification_title'));
    });

    it('does not show the verify button to users with a verified phone', function () {
        $user = makeVerifiedPhoneUser();

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk()
            ->assertDontSee(__('settings.profile.phone_send_verification'));
    });

    it('sends the verification link by WhatsApp', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => null]);
        RateLimiter::clear('phone-verification:' . $user->id);

        $this->actingAs($user);
        Volt::test('settings.phone-verification')->call('send');

        Notification::assertSentTo($user, PhoneVerificationNotification::class);
        expect($user->fresh()->movil_verification_token)->not->toBeEmpty();
        RateLimiter::clear('phone-verification:' . $user->id);
    });

    it('limits the verification messages to 3 per hour', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => null]);
        RateLimiter::clear('phone-verification:' . $user->id);

        $this->actingAs($user);
        $component = Volt::test('settings.phone-verification');

        foreach (range(1, 4) as $attempt) {
            $component->call('send');
        }

        $component->assertSet('status', 'throttled');

        Notification::assertSentToTimes($user, PhoneVerificationNotification::class, 3);
        RateLimiter::clear('phone-verification:' . $user->id);
    });

    it('does not send anything when the phone is already verified', function () {
        $user = makeVerifiedPhoneUser();

        $this->actingAs($user);
        Volt::test('settings.phone-verification')->call('send');

        Notification::assertNothingSent();
    });

    it('marks the phone as verified with the link', function () {
        $user = makeVerifiedPhoneUser(['movil_verified_at' => null, 'movil_verification_token' => 'token-123']);

        $this->get('/verify-phone/token-123')->assertOk();

        expect($user->fresh())
            ->hasVerifiedPhone()->toBeTrue()
            ->movil_verification_token->toBeNull();
    });

    it('turns on the WhatsApp opt-in when verifying with the link', function () {
        $user = makeVerifiedPhoneUser([
            'movil_verified_at'        => null,
            'movil_verification_token' => 'token-456',
            'whatsapp_opt_in'          => false,
            'whatsapp_opt_in_at'       => null,
        ]);

        $this->get('/verify-phone/token-456')->assertOk();

        expect($user->fresh())
            ->whatsapp_opt_in->toBeTrue()
            ->whatsapp_opt_in_at->not->toBeNull();
    });

    it('keeps the original opt-in date when the user had already opted in', function () {
        $optedInAt = now()->subMonth()->startOfSecond();
        $user      = makeVerifiedPhoneUser([
            'movil_verified_at'        => null,
            'movil_verification_token' => 'token-789',
            'whatsapp_opt_in_at'       => $optedInAt,
        ]);

        $this->get('/verify-phone/token-789')->assertOk();

        expect($user->fresh()->whatsapp_opt_in_at->equalTo($optedInAt))->toBeTrue();
    });
});

describe('WhatsApp notifications toggle in profile', function () {
    it('turns off the WhatsApp notifications', function () {
        $user = makeVerifiedPhoneUser(['whatsapp_opt_in_at' => now()]);

        $this->actingAs($user);
        Volt::test('settings.profile')
            ->set('data.whatsapp_opt_in', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh())
            ->whatsapp_opt_in->toBeFalse()
            ->whatsapp_opt_in_at->toBeNull()
            ->hasVerifiedPhone()->toBeTrue();
    });

    it('turns on the WhatsApp notifications', function () {
        $user = makeVerifiedPhoneUser(['whatsapp_opt_in' => false, 'whatsapp_opt_in_at' => null]);

        $this->actingAs($user);
        Volt::test('settings.profile')
            ->set('data.whatsapp_opt_in', true)
            ->call('save')
            ->assertHasNoErrors();

        expect($user->fresh())
            ->whatsapp_opt_in->toBeTrue()
            ->whatsapp_opt_in_at->not->toBeNull();
    });
});
