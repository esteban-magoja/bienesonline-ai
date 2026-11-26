<?php

declare(strict_types=1);

use App\Filament\Pages\WhatsAppInbox;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsAppInbox\WhatsAppInboxService;
use App\Services\WhatsAppInbox\WhatsAppPhone;
use App\Services\WhatsAppInbox\WhatsAppWebhookProcessor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

const WA_TEST_APP_SECRET = 'test-app-secret';
const WA_TEST_PHONE_NUMBER_ID = '1234567890';

beforeEach(function (): void {
    config([
        'whatsapp.enabled' => true,
        'whatsapp.access_token' => 'test-token',
        'whatsapp.phone_number_id' => WA_TEST_PHONE_NUMBER_ID,
        'whatsapp.business_account_id' => '999',
        'whatsapp.app_secret' => WA_TEST_APP_SECRET,
        'whatsapp.webhook_verify_token' => 'verify-me',
    ]);

    Cache::forget(WhatsAppInboxService::TEMPLATES_CACHE_KEY);
});

function waInboxUser(array $attributes = []): User
{
    return User::factory()->create(['avatar' => 'demo/default.png', 'whatsapp_opt_in' => true] + $attributes);
}

/**
 * Payload como el que envía Meta al webhook.
 *
 * @param  array<int, array<string, mixed>>  $messages
 * @param  array<int, array<string, mixed>>  $statuses
 * @return array<string, mixed>
 */
function waWebhookPayload(array $messages = [], array $statuses = [], string $profileName = 'Juan WhatsApp'): array
{
    $value = [
        'messaging_product' => 'whatsapp',
        'metadata' => ['display_phone_number' => '15550000000', 'phone_number_id' => WA_TEST_PHONE_NUMBER_ID],
    ];

    if ($messages !== []) {
        $value['contacts'] = collect($messages)
            ->map(fn (array $message): array => ['profile' => ['name' => $profileName], 'wa_id' => $message['from']])
            ->unique('wa_id')
            ->values()
            ->all();
        $value['messages'] = $messages;
    }

    if ($statuses !== []) {
        $value['statuses'] = $statuses;
    }

    return [
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => '999', 'changes' => [['field' => 'messages', 'value' => $value]]]],
    ];
}

/**
 * @return array<string, mixed>
 */
function waTextMessage(string $from, string $body, ?string $wamid = null, array $extra = []): array
{
    return [
        'from' => $from,
        'id' => $wamid ?? 'wamid.'.uniqid('in', true),
        'timestamp' => (string) now()->timestamp,
        'type' => 'text',
        'text' => ['body' => $body],
    ] + $extra;
}

function waProcess(array $payload): void
{
    app(WhatsAppWebhookProcessor::class)->process($payload);
}

describe('webhook', function (): void {
    it('responde el desafío de verificación de Meta con el token correcto', function (): void {
        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');

        $this->get('/webhook/whatsapp?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=12345')
            ->assertForbidden();
    });

    it('guarda el aviso firmado y lo encola', function (): void {
        Queue::fake();
        $body = json_encode(waWebhookPayload([waTextMessage('5493510000001', 'Hola')]));

        $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, WA_TEST_APP_SECRET),
        ], $body)->assertOk();

        $event = WhatsAppWebhookEvent::latest('id')->first();

        expect($event->status)->toBe('pending')
            ->and($event->payload['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])->toBe('Hola');

        Queue::assertPushed(ProcessWhatsAppWebhook::class, fn (ProcessWhatsAppWebhook $job): bool => $job->webhookEventId === $event->id);
    });

    it('rechaza avisos con firma inválida o sin App Secret configurado', function (): void {
        Queue::fake();
        $body = json_encode(waWebhookPayload([waTextMessage('5493510000001', 'Hola')]));
        $before = WhatsAppWebhookEvent::count();

        $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=firma-falsa',
        ], $body)->assertForbidden();

        config(['whatsapp.app_secret' => '']);

        $this->call('POST', '/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, WA_TEST_APP_SECRET),
        ], $body)->assertStatus(500);

        expect(WhatsAppWebhookEvent::count())->toBe($before);
        Queue::assertNothingPushed();
    });

    it('el job procesa el aviso y lo marca como procesado', function (): void {
        $event = WhatsAppWebhookEvent::create([
            'payload' => waWebhookPayload([waTextMessage('5493510000002', 'Consulta por el anuncio')]),
            'status' => 'pending',
        ]);

        (new ProcessWhatsAppWebhook($event->id))->handle(app(WhatsAppWebhookProcessor::class));

        expect($event->fresh()->status)->toBe('processed')
            ->and(WhatsAppConversation::where('wa_id', '5493510000002')->first()?->messages()->count())->toBe(1);
    });
});

describe('mensajes entrantes', function (): void {
    it('crea la conversación, vincula al usuario por su móvil sin el 9 de Argentina y no duplica reintentos', function (): void {
        $user = waInboxUser(['movil' => '+54 351 000-0003']);
        $payload = waWebhookPayload([waTextMessage('5493510000003', 'Hola, ¿sigue disponible?', 'wamid.TEST-IN-1')]);

        waProcess($payload);
        waProcess($payload);

        $conversation = WhatsAppConversation::where('wa_id', '5493510000003')->firstOrFail();

        expect($conversation->user_id)->toBe($user->id)
            ->and($conversation->profile_name)->toBe('Juan WhatsApp')
            ->and($conversation->unread_count)->toBe(1)
            ->and($conversation->last_message_preview)->toBe('Hola, ¿sigue disponible?')
            ->and($conversation->isServiceWindowOpen())->toBeTrue()
            ->and($conversation->messages()->count())->toBe(1)
            ->and($conversation->messages()->first()->direction)->toBe(WhatsAppMessage::DIRECTION_IN);
    });

    it('abre la ventana de 24 horas aunque el mensaje sea más largo que la vista previa', function (): void {
        $body = str_repeat('Mensaje largo de un asistente virtual con acentos y emojis 🏡. ', 10);

        waProcess(waWebhookPayload([waTextMessage('50496723177', $body)]));

        $conversation = WhatsAppConversation::where('wa_id', '50496723177')->firstOrFail();

        expect($conversation->isServiceWindowOpen())->toBeTrue()
            ->and($conversation->unread_count)->toBe(1)
            ->and(mb_strlen($conversation->last_message_preview))->toBeLessThanOrEqual(191)
            ->and($conversation->messages()->first()->body)->toBe($body);
    });

    it('guarda los documentos con nombres de archivo largos', function (): void {
        Http::fake(['*' => Http::response('', 500)]);

        waProcess(waWebhookPayload([[
            'from' => '50496723178',
            'id' => 'wamid.DOC-LONG',
            'timestamp' => (string) now()->timestamp,
            'type' => 'document',
            'document' => ['id' => 'media-1', 'mime_type' => 'application/pdf', 'filename' => str_repeat('contrato-de-alquiler-', 15).'.pdf'],
        ]]));

        $message = WhatsAppMessage::where('wamid', 'wamid.DOC-LONG')->firstOrFail();

        expect(mb_strlen($message->media_filename))->toBeLessThanOrEqual(191)
            ->and($message->conversation->isServiceWindowOpen())->toBeTrue();
    });

    it('vincula la respuesta con el aviso automático al que contesta', function (): void {
        $user = waInboxUser(['movil' => '+5491100000004']);
        $log = WhatsAppMessageLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'phone' => '+5491100000004',
            'notification_class' => 'Test',
            'event_type' => 'match_ad',
            'template_name' => 'matchanuncio',
            'status' => 'sent',
            'whatsapp_message_id' => 'wamid.TEMPLATE-1',
        ]);

        waProcess(waWebhookPayload([
            waTextMessage('5491100000004', 'Me interesa', null, ['context' => ['from' => '15550000000', 'id' => 'wamid.TEMPLATE-1']]),
        ]));

        $message = WhatsAppMessage::where('context_wamid', 'wamid.TEMPLATE-1')->firstOrFail();

        expect($message->whatsapp_message_log_id)->toBe($log->id)
            ->and($message->conversation->user_id)->toBe($user->id);
    });

    it('da de baja los avisos por WhatsApp cuando el usuario escribe BAJA', function (): void {
        $user = waInboxUser(['movil' => '+5491100000005']);

        waProcess(waWebhookPayload([waTextMessage('5491100000005', ' Baja. ')]));

        expect($user->fresh()->whatsapp_opt_in)->toBeFalse()
            ->and(WhatsAppConversation::where('wa_id', '5491100000005')->first()->opted_out_at)->not->toBeNull();
    });

    it('no da de baja si la palabra está dentro de un mensaje más largo', function (): void {
        $user = waInboxUser(['movil' => '+5491100000006']);

        waProcess(waWebhookPayload([waTextMessage('5491100000006', 'Quiero saber si bajó el precio, no me den de baja')]));

        expect($user->fresh()->whatsapp_opt_in)->toBeTrue();
    });

    it('descarga las imágenes recibidas al disco privado', function (): void {
        Storage::fake('local');
        Http::fake([
            'https://graph.facebook.com/*/media-123' => Http::response(['url' => 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=123', 'mime_type' => 'image/jpeg', 'file_size' => 9]),
            'https://lookaside.fbsbx.com/*' => Http::response('JPEGDATA!', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        waProcess(waWebhookPayload([[
            'from' => '5491100000007',
            'id' => 'wamid.IMG-1',
            'timestamp' => (string) now()->timestamp,
            'type' => 'image',
            'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg', 'caption' => 'Frente de la casa'],
        ]]));

        $message = WhatsAppMessage::where('wamid', 'wamid.IMG-1')->firstOrFail();

        expect($message->media_path)->not->toBeNull()
            ->and($message->body)->toBe('Frente de la casa')
            ->and($message->conversation->last_message_preview)->toBe('📷 Foto: Frente de la casa');

        Storage::disk('local')->assertExists($message->media_path);
        expect(Storage::disk('local')->get($message->media_path))->toBe('JPEGDATA!');
    });

    it('ignora mensajes dirigidos a otro número de la cuenta', function (): void {
        $payload = waWebhookPayload([waTextMessage('5491100000008', 'Hola')]);
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'otro-numero';

        waProcess($payload);

        expect(WhatsAppConversation::where('wa_id', '5491100000008')->exists())->toBeFalse();
    });
});

describe('estados de entrega', function (): void {
    it('avanza el estado de las respuestas sin retroceder y guarda el motivo del fallo', function (): void {
        $message = WhatsAppMessage::factory()->outbound()->create(['wamid' => 'wamid.OUT-1']);
        $status = fn (string $value, array $extra = []): array => ['id' => 'wamid.OUT-1', 'status' => $value, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '549'] + $extra;

        waProcess(waWebhookPayload([], [$status('read')]));
        waProcess(waWebhookPayload([], [$status('delivered')]));

        expect($message->fresh()->status)->toBe('read');

        waProcess(waWebhookPayload([], [$status('failed', ['errors' => [['code' => 131047, 'title' => 'Re-engagement message', 'error_data' => ['details' => 'Pasaron más de 24 horas']]]])]));

        expect($message->fresh()->status)->toBe('failed')
            ->and($message->fresh()->error_message)->toBe('(131047) Pasaron más de 24 horas');
    });

    it('guarda el estado de entrega de los avisos automáticos', function (): void {
        $user = waInboxUser();
        $log = WhatsAppMessageLog::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'phone' => '+5491100000009',
            'notification_class' => 'Test',
            'status' => 'sent',
            'whatsapp_message_id' => 'wamid.LOG-1',
        ]);

        waProcess(waWebhookPayload([], [['id' => 'wamid.LOG-1', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp]]));

        expect($log->fresh()->delivery_status)->toBe('delivered')
            ->and($log->fresh()->status)->toBe('sent');
    });
});

describe('respuestas desde el admin', function (): void {
    it('responde con texto libre dentro de las 24 horas', function (): void {
        Http::fake(['https://graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.REPLY-1']]])]);
        $admin = waInboxUser();
        $conversation = WhatsAppConversation::factory()->create();

        $message = app(WhatsAppInboxService::class)->sendText($conversation, 'Hola, sí, sigue disponible', $admin);

        expect($message->wamid)->toBe('wamid.REPLY-1')
            ->and($message->status)->toBe('sent')
            ->and($message->sent_by)->toBe($admin->id)
            ->and($conversation->fresh()->last_message_preview)->toBe('Hola, sí, sigue disponible');

        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), WA_TEST_PHONE_NUMBER_ID.'/messages')
            && $request['to'] === $conversation->wa_id
            && $request['text']['body'] === 'Hola, sí, sigue disponible');
    });

    it('responde con un texto largo sin fallar al actualizar la conversación', function (): void {
        Http::fake(['https://graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.REPLY-LONG']]])]);
        $conversation = WhatsAppConversation::factory()->create();
        $body = trim(str_repeat('Gracias por escribirnos, te contamos los detalles de la propiedad. ', 8));

        $message = app(WhatsAppInboxService::class)->sendText($conversation, $body, null);

        expect($message->body)->toBe($body)
            ->and(mb_strlen($conversation->fresh()->last_message_preview))->toBeLessThanOrEqual(191);
    });

    it('no permite texto libre fuera de la ventana de 24 horas', function (): void {
        $conversation = WhatsAppConversation::factory()->windowClosed()->create();

        app(WhatsAppInboxService::class)->sendText($conversation, 'Hola', null);
    })->throws(InvalidArgumentException::class);

    it('guarda como fallido el mensaje que Meta rechaza', function (): void {
        Http::fake(['https://graph.facebook.com/*/messages' => Http::response(['error' => ['code' => 131026, 'message' => 'Message undeliverable']], 400)]);
        $conversation = WhatsAppConversation::factory()->create();

        $message = app(WhatsAppInboxService::class)->sendText($conversation, 'Hola', null);

        expect($message->status)->toBe('failed')
            ->and($message->wamid)->toBeNull()
            ->and($message->error_message)->toBe('(131026) Message undeliverable');
    });

    it('escribe con un template aprobado y solo ofrece los que tienen variables en el texto', function (): void {
        Http::fake([
            'https://graph.facebook.com/*/999/message_templates*' => Http::response(['data' => [
                ['name' => 'seguimiento', 'language' => 'es', 'status' => 'APPROVED', 'category' => 'UTILITY', 'components' => [
                    ['type' => 'BODY', 'text' => 'Hola {{1}}, te escribimos por {{2}}.'],
                ]],
                ['name' => 'con_imagen', 'language' => 'es', 'status' => 'APPROVED', 'category' => 'MARKETING', 'components' => [
                    ['type' => 'HEADER', 'format' => 'IMAGE'],
                    ['type' => 'BODY', 'text' => 'Mira esto'],
                ]],
                ['name' => 'codigo', 'language' => 'es', 'status' => 'APPROVED', 'category' => 'AUTHENTICATION', 'components' => [
                    ['type' => 'BODY', 'text' => 'Tu código es {{1}}'],
                ]],
            ]]),
            'https://graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.TPL-1']]]),
        ]);
        $conversation = WhatsAppConversation::factory()->windowClosed()->create();
        $service = app(WhatsAppInboxService::class);

        expect(collect($service->availableTemplates())->pluck('name')->all())->toBe(['seguimiento']);

        $message = $service->sendTemplate($conversation, 'seguimiento', 'es', ['1' => 'Ana', '2' => 'tu consulta'], null);

        expect($message->type)->toBe('template')
            ->and($message->template_name)->toBe('seguimiento')
            ->and($message->body)->toBe('Hola Ana, te escribimos por tu consulta.')
            ->and($message->status)->toBe('sent');

        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/messages')
            && $request['type'] === 'template'
            && $request['template']['components'][0]['parameters'][1]['text'] === 'tu consulta');
    });

    it('marca como leída la conversación y avisa a WhatsApp', function (): void {
        Http::fake(['https://graph.facebook.com/*/messages' => Http::response(['success' => true])]);
        $conversation = WhatsAppConversation::factory()->create(['unread_count' => 2]);
        WhatsAppMessage::factory()->create(['whatsapp_conversation_id' => $conversation->id, 'wamid' => 'wamid.LAST-IN']);

        app(WhatsAppInboxService::class)->markAsRead($conversation);

        expect($conversation->fresh()->unread_count)->toBe(0);
        Http::assertSent(fn (HttpRequest $request): bool => ($request['status'] ?? null) === 'read' && $request['message_id'] === 'wamid.LAST-IN');
    });
});

describe('bandeja en el admin', function (): void {
    beforeEach(function (): void {
        Http::fake(['https://graph.facebook.com/*' => Http::response(['success' => true, 'data' => []])]);
        $this->admin = waInboxUser();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($this->admin);
    });

    it('carga la página y la muestra en el menú', function (): void {
        $this->get('/admin/whatsapp-inbox')->assertOk()->assertSee('Bandeja de WhatsApp');
    });

    it('abre una conversación, la marca como leída y responde', function (): void {
        $conversation = WhatsAppConversation::factory()->create(['unread_count' => 1, 'profile_name' => 'Cliente Bandeja']);
        WhatsAppMessage::factory()->create(['whatsapp_conversation_id' => $conversation->id, 'body' => 'Mensaje del cliente en la bandeja']);

        Livewire::test(WhatsAppInbox::class)
            ->call('openConversation', $conversation->id)
            ->assertSee('Mensaje del cliente en la bandeja')
            ->set('reply', 'Respuesta desde el admin')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('reply', '')
            ->assertSee('Respuesta desde el admin');

        expect($conversation->fresh()->unread_count)->toBe(0)
            ->and($conversation->messages()->where('direction', 'out')->value('sent_by'))->toBe($this->admin->id);
    });

    it('muestra el aviso de 24 horas vencidas en lugar del campo de texto libre', function (): void {
        $conversation = WhatsAppConversation::factory()->windowClosed()->create();

        Livewire::test(WhatsAppInbox::class)
            ->call('openConversation', $conversation->id)
            ->assertSee('Pasaron más de 24 horas')
            ->assertDontSee('Ctrl + Enter');
    });

    it('sirve los adjuntos solo a administradores', function (): void {
        Storage::fake('local');
        Storage::disk('local')->put('whatsapp/test/foto.jpg', 'JPEG');
        $message = WhatsAppMessage::factory()->create(['type' => 'image', 'media_path' => 'whatsapp/test/foto.jpg', 'media_mime' => 'image/jpeg']);

        $this->get(route('whatsapp.media', $message))->assertOk();

        $this->actingAs(waInboxUser());
        $this->get(route('whatsapp.media', $message))->assertForbidden();
    });
});

it('reconoce las variantes de números de Argentina y México', function (): void {
    expect(WhatsAppPhone::variants('5493511234567'))->toBe(['5493511234567', '543511234567'])
        ->and(WhatsAppPhone::variants('+54 351 123-4567'))->toBe(['543511234567', '5493511234567'])
        ->and(WhatsAppPhone::variants('5215512345678'))->toBe(['5215512345678', '525512345678'])
        ->and(WhatsAppPhone::digits('0034 600 123 456'))->toBe('34600123456');
});
