<?php

declare(strict_types=1);

use App\Events\PropertyListingCreated;
use App\Listeners\NotifyMatchingRequests;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use App\Notifications\PropertyMatchFoundNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

afterEach(fn () => Cache::flush());

function makeRequesterUser(): User
{
    return User::factory()->create(['terms_accepted' => true]);
}

/** Ejecuta el listener con solicitudes (owner => score) devueltas por el matching mockeado. */
function runRequestsListener(array $requestsByOwner): PropertyListing
{
    $requests = collect($requestsByOwner)->map(function (array $item) {
        $request              = new PropertyRequest(['user_id' => $item[0] instanceof User ? $item[0]->id : $item[0]]);
        $request->id          = random_int(1_000_000, 9_000_000);
        $request->match_score = $item[1];

        return $request;
    });

    $matchingService = Mockery::mock(PropertyMatchingService::class);
    $matchingService->shouldReceive('getAllMatchesForListing')->andReturn($requests->values());

    $listing     = PropertyListing::factory()->make();
    $listing->id = random_int(1_000_000, 9_000_000);

    (new NotifyMatchingRequests($matchingService))->handle(new PropertyListingCreated($listing));

    return $listing;
}

it('notifies requesters once even with several matching requests', function () {
    Notification::fake();

    $requester = makeRequesterUser();

    runRequestsListener([[$requester, 75], [$requester, 92], [$requester, 80]]);

    Notification::assertSentToTimes($requester, PropertyMatchFoundNotification::class, 1);
    Notification::assertSentTo(
        $requester,
        PropertyMatchFoundNotification::class,
        fn (PropertyMatchFoundNotification $notification) => $notification->matchScore === 92
    );
});

it('skips legacy requests owned by the import user', function () {
    Notification::fake();
    config(['import.legacy_requests_user_id' => 1]);

    $requester = makeRequesterUser();

    runRequestsListener([[1, 95], [$requester, 85]]);

    Notification::assertSentToTimes($requester, PropertyMatchFoundNotification::class, 1);
    Notification::assertSentTimes(PropertyMatchFoundNotification::class, 1);
});

it('notifies requests that have a client email', function () {
    Notification::fake();

    $requester = makeRequesterUser();

    runRequestsListener([[$requester, 85]]);

    Notification::assertSentTo($requester, PropertyMatchFoundNotification::class);
});

it('does not notify matches below the threshold', function () {
    Notification::fake();

    runRequestsListener([[makeRequesterUser(), 60]]);

    Notification::assertNothingSent();
});

it('sends at most one notification per user per day', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::parse('2025-01-01 09:00:00'));

    $requester = makeRequesterUser();

    runRequestsListener([[$requester, 90]]);
    runRequestsListener([[$requester, 90]]);

    Notification::assertSentToTimes($requester, PropertyMatchFoundNotification::class, 1);

    Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00'));
    runRequestsListener([[$requester, 90]]);

    Notification::assertSentToTimes($requester, PropertyMatchFoundNotification::class, 2);

    Carbon::setTestNow();
});

it('uses the notifications table as a fallback when the cache was cleared', function () {
    Notification::fake();

    $requester = makeRequesterUser();

    DB::table('notifications')->insert([
        'id'              => (string) Str::uuid(),
        'type'            => PropertyMatchFoundNotification::class,
        'notifiable_type' => $requester->getMorphClass(),
        'notifiable_id'   => $requester->id,
        'data'            => '{}',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    runRequestsListener([[$requester, 90]]);

    Notification::assertNothingSent();
});
