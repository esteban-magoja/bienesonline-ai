<?php

declare(strict_types=1);

use App\Events\PropertyRequestCreated;
use App\Listeners\NotifyMatchingListings;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use App\Notifications\PropertyMatchAdNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(DatabaseTransactions::class);

/** Helper: crea un usuario con los campos mínimos necesarios. */
function makeTestUser(array $overrides = []): User
{
    $uid = str_replace('.', '', uniqid('', true));

    $id = DB::table('users')->insertGetId(array_merge([
        'name'              => 'Test User',
        'email'             => "test-{$uid}@example.com",
        'username'          => "testuser{$uid}",
        'avatar'            => 'demo/default.png',
        'password'          => bcrypt('password'),
        'locale'            => 'es',
        'terms_accepted'    => true,
        'terms_accepted_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ], $overrides));

    return User::find($id);
}

describe('PropertyRequestCreated event dispatch', function () {
    it('fires the event when a PropertyRequest is created via Eloquent', function () {
        Event::fake([PropertyRequestCreated::class]);

        $user = makeTestUser();

        PropertyRequest::create([
            'user_id'          => $user->id,
            'title'            => 'Busco casa',
            'description'      => 'Necesito una casa en Buenos Aires',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        Event::assertDispatched(PropertyRequestCreated::class);
    });
});

describe('NotifyMatchingListings listener', function () {
    it('sends a WhatsApp notification to listing owners with matching listings during allowed hours', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $listingOwner = makeTestUser(['movil' => '+5491112345678', 'whatsapp_opt_in' => true]);
        $requester    = makeTestUser();

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco casa',
            'description'      => 'Necesito casa amplia en Buenos Aires',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        $listing             = PropertyListing::factory()->make(['user_id' => $listingOwner->id]);
        $listing->match_score = 85;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertSentTo($listingOwner, PropertyMatchAdNotification::class);
    });

    it('sends only one notification per user even if they have multiple matching listings', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 14:00:00'));

        $listingOwner = makeTestUser(['movil' => '+5491112345678', 'whatsapp_opt_in' => true]);
        $requester    = makeTestUser();

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco depto',
            'description'      => 'Departamento en Palermo',
            'property_type'    => 'departamento',
            'transaction_type' => 'alquiler',
            'country'          => 'Argentina',
            'currency'         => 'ARS',
            'is_active'        => true,
        ]);

        $listing1              = PropertyListing::factory()->make(['user_id' => $listingOwner->id]);
        $listing1->match_score = 90;
        $listing2              = PropertyListing::factory()->make(['user_id' => $listingOwner->id]);
        $listing2->match_score = 75;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing1, $listing2]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertSentToTimes($listingOwner, PropertyMatchAdNotification::class, 1);
    });

    it('does not send notifications to listing owners without a phone number', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 13:00:00'));

        $ownerWithoutPhone = makeTestUser(['movil' => null]);
        $requester         = makeTestUser();

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco local',
            'description'      => 'Local comercial en microcentro',
            'property_type'    => 'local',
            'transaction_type' => 'alquiler',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        $listing             = PropertyListing::factory()->make(['user_id' => $ownerWithoutPhone->id]);
        $listing->match_score = 80;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertNothingSent();
    });

    it('does not send notifications to listing owners without whatsapp opt-in', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 13:00:00'));

        $ownerWithoutOptIn = makeTestUser(['movil' => '+5491112345678', 'whatsapp_opt_in' => false]);
        $requester         = makeTestUser();

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco oficina',
            'description'      => 'Oficina en microcentro porteño',
            'property_type'    => 'oficina',
            'transaction_type' => 'alquiler',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        $listing              = PropertyListing::factory()->make(['user_id' => $ownerWithoutOptIn->id]);
        $listing->match_score = 80;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertNothingSent();
    });

    it('re-queues the job when executed outside the 11-18 time window', function () {
        Carbon::setTestNow(Carbon::parse('2025-01-01 09:00:00'));

        $requester = makeTestUser();
        $request   = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco terreno',
            'description'      => 'Terreno en provincia de Buenos Aires',
            'property_type'    => 'terreno',
            'transaction_type' => 'venta',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldNotReceive('findMatchesForRequest');

        $fakeJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $fakeJob->shouldReceive('release')->once()->with(Mockery::on(fn ($v) => is_int($v) && $v > 0));
        $fakeJob->shouldReceive('isReleased')->andReturn(false)->byDefault();

        $listener = new NotifyMatchingListings($matchingService);
        $listener->setJob($fakeJob);
        $listener->handle(new PropertyRequestCreated($request));

        // If we reach here without exception the release() was called (Mockery verifies it)
        expect(true)->toBeTrue();
    });

    it('does not send a second notification to a user notified less than 60 minutes ago', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $listingOwner = makeTestUser(['movil' => '+5491112345678', 'whatsapp_opt_in' => true]);
        $requester    = makeTestUser();

        // Registrar un envío reciente en el log (hace 30 minutos)
        DB::table('whatsapp_message_logs')->insert([
            'notifiable_type'    => User::class,
            'notifiable_id'      => $listingOwner->id,
            'phone'              => $listingOwner->movil,
            'notification_class' => 'App\Notifications\PropertyMatchAdNotification',
            'event_type'         => 'match_ad',
            'status'             => 'sent',
            'created_at'         => now()->subMinutes(30),
            'updated_at'         => now()->subMinutes(30),
        ]);

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco depto',
            'description'      => 'Departamento en Palermo con luz',
            'property_type'    => 'departamento',
            'transaction_type' => 'alquiler',
            'country'          => 'Argentina',
            'currency'         => 'ARS',
            'is_active'        => true,
        ]);

        $listing             = PropertyListing::factory()->make(['user_id' => $listingOwner->id]);
        $listing->match_score = 90;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertNothingSent();
    });

    it('sends a notification to a user whose last message was more than 60 minutes ago', function () {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00'));

        $listingOwner = makeTestUser(['movil' => '+5491112345678', 'whatsapp_opt_in' => true]);
        $requester    = makeTestUser();

        // Registrar un envío antiguo en el log (hace 90 minutos)
        DB::table('whatsapp_message_logs')->insert([
            'notifiable_type'    => User::class,
            'notifiable_id'      => $listingOwner->id,
            'phone'              => $listingOwner->movil,
            'notification_class' => 'App\Notifications\PropertyMatchAdNotification',
            'event_type'         => 'match_ad',
            'status'             => 'sent',
            'created_at'         => now()->subMinutes(90),
            'updated_at'         => now()->subMinutes(90),
        ]);

        $request = PropertyRequest::create([
            'user_id'          => $requester->id,
            'title'            => 'Busco casa grande',
            'description'      => 'Casa con jardín en zona norte',
            'property_type'    => 'casa',
            'transaction_type' => 'venta',
            'country'          => 'Argentina',
            'currency'         => 'USD',
            'is_active'        => true,
        ]);

        $listing             = PropertyListing::factory()->make(['user_id' => $listingOwner->id]);
        $listing->match_score = 85;

        $matchingService = Mockery::mock(PropertyMatchingService::class);
        $matchingService->shouldReceive('findMatchesForRequest')
            ->once()
            ->andReturn(collect([$listing]));

        $listener = new NotifyMatchingListings($matchingService);
        $listener->handle(new PropertyRequestCreated($request));

        Notification::assertSentTo($listingOwner, PropertyMatchAdNotification::class);
    });
});
