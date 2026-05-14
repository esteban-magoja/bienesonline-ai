<?php

declare(strict_types=1);

use App\Channels\WhatsAppChannel;
use App\Contracts\ProvidesWhatsAppLogContext;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use App\Notifications\PropertyMatchAdNotification;
use App\Notifications\WelcomeWhatsAppNotification;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/** Helper: crea un usuario mínimo con número de móvil. */
function makeWhatsAppTestUser(array $overrides = []): User
{
    $uid = str_replace('.', '', uniqid('', true));

    $id = DB::table('users')->insertGetId(array_merge([
        'name'              => 'WA User',
        'email'             => "wa-{$uid}@example.com",
        'username'          => "wauser{$uid}",
        'avatar'            => 'demo/default.png',
        'password'          => bcrypt('password'),
        'locale'            => 'es',
        'movil'             => '+5491199998888',
        'whatsapp_opt_in'   => true,
        'terms_accepted'    => true,
        'terms_accepted_at' => now(),
        'created_at'        => now(),
        'updated_at'        => now(),
    ], $overrides));

    return User::find($id);
}

/** Notification stub que no depende de config de WhatsApp. */
function makeMatchAdNotificationStub(?int $requestId = null): Notification
{
    return new class($requestId) extends Notification implements ProvidesWhatsAppLogContext {
        public function __construct(private readonly ?int $requestId) {}
        public function via(mixed $notifiable): array { return []; }
        public function toWhatsApp(mixed $notifiable): array {
            return ['template' => 'test_match', 'language' => 'es', 'params' => []];
        }
        public function getWhatsAppLogContext(): array {
            return ['event_type' => 'match_ad', 'property_listing_id' => null, 'property_request_id' => $this->requestId];
        }
    };
}

describe('WhatsAppChannel logging', function () {
    it('creates a sent log entry when sendTemplate succeeds', function () {
        $user = makeWhatsAppTestUser();

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendTemplate')
            ->once()
            ->andReturn('wamid.test123');
        $whatsAppService->shouldReceive('isEnabled')->andReturn(true);

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, makeMatchAdNotificationStub(null));

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('sent')
            ->and($log->whatsapp_message_id)->toBe('wamid.test123')
            ->and($log->event_type)->toBe('match_ad')
            ->and($log->property_request_id)->toBeNull()
            ->and($log->phone)->toBe('+5491199998888');
    });

    it('creates a failed log entry when sendTemplate fails', function () {
        $user = makeWhatsAppTestUser();

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendTemplate')
            ->once()
            ->andReturn(false);
        $whatsAppService->shouldReceive('isEnabled')->andReturn(true);

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, makeMatchAdNotificationStub());

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('failed')
            ->and($log->whatsapp_message_id)->toBeNull();
    });

    it('creates a disabled log entry when WhatsApp service is disabled', function () {
        $user = makeWhatsAppTestUser();

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendTemplate')
            ->once()
            ->andReturn(false);
        $whatsAppService->shouldReceive('isEnabled')->andReturn(false);

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, makeMatchAdNotificationStub());

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('disabled');
    });

    it('creates a no_phone log entry when notifiable has no phone', function () {
        $user = makeWhatsAppTestUser(['movil' => null]);

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldNotReceive('sendTemplate');

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, makeMatchAdNotificationStub());

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('no_phone');
    });

    it('stores the template_name in the log', function () {
        $user = makeWhatsAppTestUser();

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendTemplate')->andReturn('wamid.abc');
        $whatsAppService->shouldReceive('isEnabled')->andReturn(true);

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, makeMatchAdNotificationStub());

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log->template_name)->toBe('test_match')
            ->and($log->language_code)->toBe('es');
    });

    it('stores the notification_class for PropertyMatchAdNotification', function () {
        $user = makeWhatsAppTestUser();

        Config::set('whatsapp.templates.match_ad.es', ['name' => 'match_ad_es', 'language' => 'es']);

        $whatsAppService = Mockery::mock(WhatsAppService::class);
        $whatsAppService->shouldReceive('sendTemplate')->andReturn('wamid.abc');
        $whatsAppService->shouldReceive('isEnabled')->andReturn(true);

        $channel = new WhatsAppChannel($whatsAppService);
        $channel->send($user, new PropertyMatchAdNotification());

        $log = WhatsAppMessageLog::where('notifiable_id', $user->id)->first();

        expect($log->notification_class)->toBe(PropertyMatchAdNotification::class);
    });
});

describe('PropertyMatchAdNotification context', function () {
    it('implements ProvidesWhatsAppLogContext', function () {
        $notification = new PropertyMatchAdNotification(propertyRequestId: 5, propertyListingId: 10);

        expect($notification)->toBeInstanceOf(ProvidesWhatsAppLogContext::class);

        $context = $notification->getWhatsAppLogContext();

        expect($context['event_type'])->toBe('match_ad')
            ->and($context['property_request_id'])->toBe(5)
            ->and($context['property_listing_id'])->toBe(10);
    });

    it('returns null for listing/request IDs when not provided', function () {
        $notification = new PropertyMatchAdNotification();
        $context      = $notification->getWhatsAppLogContext();

        expect($context['property_request_id'])->toBeNull()
            ->and($context['property_listing_id'])->toBeNull();
    });
});

describe('WelcomeWhatsAppNotification context', function () {
    it('returns event_type welcome', function () {
        $notification = new WelcomeWhatsAppNotification();
        $context      = $notification->getWhatsAppLogContext();

        expect($context['event_type'])->toBe('welcome')
            ->and($context['property_listing_id'])->toBeNull()
            ->and($context['property_request_id'])->toBeNull();
    });
});
