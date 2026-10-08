<?php

declare(strict_types=1);

use App\Events\PropertyRequestCreated;
use App\Listeners\NotifyMatchingListingsByEmail;
use App\Models\EmailMessageLog;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use App\Notifications\PropertyMatchAdEmailNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(DatabaseTransactions::class);

afterEach(fn () => Cache::flush());

function makeEmailTestUser(array $overrides = []): User
{
    $uid = str_replace('.', '', uniqid('', true));

    $id = DB::table('users')->insertGetId(array_merge([
        'name'              => 'Email User',
        'email'             => "email-{$uid}@example.com",
        'username'          => "emailuser{$uid}",
        'avatar'            => 'demo/default.png',
        'password'          => bcrypt('password'),
        'locale'            => 'es',
        'email_verified_at' => now(),
        'terms_accepted'    => true,
        'terms_accepted_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ], $overrides));

    return User::find($id);
}

function makeEmailTestRequest(): PropertyRequest
{
    return PropertyRequest::withoutEvents(fn () => PropertyRequest::create([
        'user_id'          => makeEmailTestUser()->id,
        'title'            => 'Busco casa',
        'description'      => 'Necesito casa amplia en Buenos Aires',
        'property_type'    => 'casa',
        'transaction_type' => 'venta',
        'country'          => 'Argentina',
        'currency'         => 'USD',
        'is_active'        => true,
    ]));
}

/** Ejecuta el listener con anuncios (owner => score) devueltos por el matching mockeado. */
function runEmailListener(PropertyRequest $request, array $listingsByOwner): void
{
    $listings = collect($listingsByOwner)->map(function (array $item) {
        $listing              = PropertyListing::factory()->make(['user_id' => $item[0]->id]);
        $listing->match_score = $item[1];

        return $listing;
    });

    $matchingService = Mockery::mock(PropertyMatchingService::class);
    $matchingService->shouldReceive('findMatchesForRequest')->andReturn($listings->values());

    (new NotifyMatchingListingsByEmail($matchingService))->handle(new PropertyRequestCreated($request));
}

it('emails verified listing owners and logs the delivery, at any hour', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2025-01-01 03:00:00'));

    $owner   = makeEmailTestUser();
    $request = makeEmailTestRequest();

    runEmailListener($request, [[$owner, 85], [$owner, 90]]);

    Notification::assertSentToTimes($owner, PropertyMatchAdEmailNotification::class, 1);

    $log = EmailMessageLog::where('notifiable_id', $owner->id)->sole();
    expect($log->status)->toBe('sent')
        ->and($log->email)->toBe($owner->email)
        ->and($log->property_request_id)->toBe($request->id)
        ->and($log->event_type)->toBe('match_ad');
});

it('does not email owners with an unverified email', function () {
    Notification::fake();

    $owner = makeEmailTestUser(['email_verified_at' => null]);

    runEmailListener(makeEmailTestRequest(), [[$owner, 90]]);

    Notification::assertNothingSent();
    expect(EmailMessageLog::where('notifiable_id', $owner->id)->exists())->toBeFalse();
});

it('does not email owners whose listings score below the threshold', function () {
    Notification::fake();

    $owner = makeEmailTestUser();

    runEmailListener(makeEmailTestRequest(), [[$owner, 50]]);

    Notification::assertNothingSent();
});

it('sends at most one email per user per day', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2025-01-01 09:00:00'));

    $owner = makeEmailTestUser();

    runEmailListener(makeEmailTestRequest(), [[$owner, 90]]);
    Cache::flush(); // el respaldo en DB debe bloquear igual
    runEmailListener(makeEmailTestRequest(), [[$owner, 90]]);

    Notification::assertSentToTimes($owner, PropertyMatchAdEmailNotification::class, 1);

    Carbon::setTestNow(Carbon::parse('2025-01-02 08:00:00'));
    runEmailListener(makeEmailTestRequest(), [[$owner, 90]]);

    Notification::assertSentToTimes($owner, PropertyMatchAdEmailNotification::class, 2);
});

it('builds a plural email linking to the dashboard matches page', function () {
    $owner = makeEmailTestUser();

    $mail = (new PropertyMatchAdEmailNotification(1))->toMail($owner);

    expect($mail->subject)->toBe(__('emails.property_match_ad.subject'))
        ->and($mail->actionUrl)->toBe(route('dashboard.matches.index'));
});
