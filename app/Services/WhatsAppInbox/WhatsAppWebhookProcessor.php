<?php

namespace App\Services\WhatsAppInbox;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageLog;
use App\Services\WhatsAppService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;

/**
 * Procesa un aviso del webhook de WhatsApp (Meta Cloud API):
 * - mensajes entrantes → conversación + mensaje (+ adjunto descargado, vínculo con el usuario y con el aviso respondido)
 * - estados (sent/delivered/read/failed) → mensajes de la bandeja o avisos automáticos (whatsapp_message_logs)
 *
 * Es idempotente: Meta reintenta los avisos y un mismo mensaje (wamid) se guarda una sola vez.
 */
class WhatsAppWebhookProcessor
{
    /**
     * Largo máximo de la vista previa de la conversación. La columna es varchar(191) y
     * Str::limit() agrega "...": con 250 los mensajes largos hacían fallar la actualización
     * de la conversación (quedaba sin last_inbound_at y la bandeja daba la ventana de 24 h por vencida).
     */
    public const PREVIEW_LENGTH = 150;

    /** Largo máximo del nombre de archivo adjunto (columna varchar(191)). */
    private const FILENAME_LENGTH = 180;

    public function __construct(private readonly WhatsAppService $whatsApp)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        $phoneNumberId = (string) config('whatsapp.phone_number_id');

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'messages' || ! is_array($change['value'] ?? null)) {
                    continue;
                }

                $value = $change['value'];
                $targetNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                // Otro número de la misma cuenta de WhatsApp Business: no es de esta bandeja
                if ($phoneNumberId !== '' && $targetNumberId !== '' && $targetNumberId !== $phoneNumberId) {
                    continue;
                }

                $profileNames = collect($value['contacts'] ?? [])
                    ->filter(fn ($contact): bool => is_array($contact) && filled($contact['wa_id'] ?? null))
                    ->mapWithKeys(fn (array $contact): array => [
                        WhatsAppPhone::digits($contact['wa_id']) => $contact['profile']['name'] ?? null,
                    ]);

                foreach ($value['messages'] ?? [] as $message) {
                    if (is_array($message)) {
                        $this->storeIncomingMessage($message, $profileNames->get(WhatsAppPhone::digits($message['from'] ?? '')));
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (is_array($status)) {
                        $this->applyStatus($status);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeIncomingMessage(array $data, ?string $profileName): void
    {
        $waId = WhatsAppPhone::digits($data['from'] ?? '');
        $wamid = $data['id'] ?? null;

        if ($waId === '' || blank($wamid)) {
            return;
        }

        if (WhatsAppMessage::where('wamid', $wamid)->exists()) {
            return;
        }

        $conversation = $this->findOrCreateConversation($waId, $profileName);
        $type = (string) ($data['type'] ?? 'unknown');
        $sentAt = isset($data['timestamp']) ? Carbon::createFromTimestamp((int) $data['timestamp']) : now();
        $contextWamid = $data['context']['id'] ?? null;
        $notificationLog = $contextWamid
            ? WhatsAppMessageLog::where('whatsapp_message_id', $contextWamid)->first()
            : null;
        $media = in_array($type, WhatsAppMessage::MEDIA_TYPES, true) && is_array($data[$type] ?? null) ? $data[$type] : null;

        // Mensaje y conversación en una sola transacción: si algo falla no queda el mensaje
        // guardado sin actualizar la conversación (el reintento lo saltearía por wamid repetido).
        try {
            $message = DB::transaction(function () use ($conversation, $wamid, $type, $data, $media, $contextWamid, $notificationLog, $sentAt): WhatsAppMessage {
                $message = WhatsAppMessage::create([
                    'whatsapp_conversation_id' => $conversation->id,
                    'wamid' => $wamid,
                    'direction' => WhatsAppMessage::DIRECTION_IN,
                    'type' => $type,
                    'body' => $this->extractBody($type, $data),
                    'media_id' => $media['id'] ?? null,
                    'media_mime' => $media['mime_type'] ?? null,
                    'media_filename' => filled($media['filename'] ?? null) ? Str::limit((string) $media['filename'], self::FILENAME_LENGTH) : null,
                    'context_wamid' => $contextWamid,
                    'whatsapp_message_log_id' => $notificationLog?->id,
                    'status' => 'received',
                    'payload' => $data,
                    'sent_at' => $sentAt,
                ]);

                $this->linkUser($conversation, $notificationLog);
                $this->touchConversation($conversation, $message);

                if ($this->isOptOut($type, $data)) {
                    $this->optOut($conversation);
                }

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($media !== null && filled($media['id'] ?? null)) {
            $this->downloadMedia($message);
        }
    }

    private function findOrCreateConversation(string $waId, ?string $profileName): WhatsAppConversation
    {
        try {
            $conversation = WhatsAppConversation::firstOrCreate(['wa_id' => $waId], ['profile_name' => $profileName]);
        } catch (UniqueConstraintViolationException) {
            $conversation = WhatsAppConversation::where('wa_id', $waId)->firstOrFail();
        }

        if (filled($profileName) && $conversation->profile_name !== $profileName) {
            $conversation->update(['profile_name' => $profileName]);
        }

        return $conversation;
    }

    /**
     * Vincula la conversación con un usuario del sitio: primero por el aviso respondido,
     * luego por los avisos enviados a ese número y por último por el móvil del perfil.
     */
    private function linkUser(WhatsAppConversation $conversation, ?WhatsAppMessageLog $notificationLog): void
    {
        if ($conversation->user_id !== null) {
            return;
        }

        $userId = $notificationLog?->notifiable_type === User::class ? $notificationLog->notifiable_id : null;
        $variants = WhatsAppPhone::variants($conversation->wa_id);

        if ($userId === null) {
            $userId = WhatsAppMessageLog::query()
                ->where('notifiable_type', User::class)
                ->whereIn(DB::raw("regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g')"), $variants)
                ->latest('id')
                ->value('notifiable_id');
        }

        if ($userId === null) {
            $userId = User::query()
                ->whereIn(DB::raw("regexp_replace(coalesce(movil, ''), '[^0-9]', '', 'g')"), $variants)
                ->orderByDesc('whatsapp_opt_in')
                ->orderByDesc('id')
                ->value('id');
        }

        if ($userId !== null && User::whereKey($userId)->exists()) {
            $conversation->update(['user_id' => $userId]);
        }
    }

    private function touchConversation(WhatsAppConversation $conversation, WhatsAppMessage $message): void
    {
        $conversation->refresh();
        $sentAt = $message->sent_at;
        $attributes = ['unread_count' => $conversation->unread_count + 1];

        if ($conversation->last_inbound_at === null || $sentAt->greaterThan($conversation->last_inbound_at)) {
            $attributes['last_inbound_at'] = $sentAt;
        }

        if ($conversation->last_message_at === null || $sentAt->greaterThanOrEqualTo($conversation->last_message_at)) {
            $attributes['last_message_at'] = $sentAt;
            $attributes['last_message_preview'] = self::preview($message->type, $message->body);
        }

        $conversation->update($attributes);
    }

    /**
     * Texto corto para la lista de conversaciones.
     */
    public static function preview(string $type, ?string $body): string
    {
        $label = match ($type) {
            'image' => '📷 Foto',
            'video' => '🎥 Video',
            'audio' => '🎤 Audio',
            'document' => '📄 Documento',
            'sticker' => 'Sticker',
            'location' => '📍 Ubicación',
            'contacts' => '👤 Contacto',
            'reaction' => 'Reacción',
            'template' => '📋 Template',
            default => null,
        };

        $text = trim((string) $body);

        if ($label !== null && $text !== '' && $type !== 'reaction') {
            return Str::limit($label.': '.$text, self::PREVIEW_LENGTH);
        }

        if ($label !== null && $type === 'reaction') {
            return Str::limit($label.' '.$text, self::PREVIEW_LENGTH);
        }

        return Str::limit($text !== '' ? $text : ($label ?? 'Mensaje no soportado'), self::PREVIEW_LENGTH);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractBody(string $type, array $data): ?string
    {
        $content = is_array($data[$type] ?? null) ? $data[$type] : [];

        $body = match ($type) {
            'text' => $content['body'] ?? null,
            'image', 'video', 'document' => $content['caption'] ?? ($content['filename'] ?? null),
            'button' => $content['text'] ?? ($content['payload'] ?? null),
            'interactive' => $content['button_reply']['title']
                ?? $content['list_reply']['title']
                ?? $content['nfm_reply']['body']
                ?? null,
            'reaction' => $content['emoji'] ?? null,
            'location' => $this->describeLocation($content),
            'contacts' => collect($data['contacts'] ?? [])
                ->map(fn ($contact): string => trim(($contact['name']['formatted_name'] ?? '').' '.collect($contact['phones'] ?? [])->pluck('phone')->implode(', ')))
                ->filter()
                ->implode("\n"),
            'system' => $content['body'] ?? null,
            default => null,
        };

        return filled($body) ? (string) $body : null;
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function describeLocation(array $location): ?string
    {
        $parts = array_filter([
            $location['name'] ?? null,
            $location['address'] ?? null,
            isset($location['latitude'], $location['longitude'])
                ? $location['latitude'].', '.$location['longitude']
                : null,
        ]);

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /**
     * El mensaje completo es una palabra de baja (BAJA, STOP...).
     *
     * @param  array<string, mixed>  $data
     */
    private function isOptOut(string $type, array $data): bool
    {
        $text = match ($type) {
            'text' => $data['text']['body'] ?? '',
            'button' => $data['button']['text'] ?? '',
            'interactive' => $data['interactive']['button_reply']['title'] ?? '',
            default => '',
        };

        $normalized = trim((string) preg_replace('/[^a-z0-9 ]+/', '', Str::lower(Str::ascii((string) $text))));

        return $normalized !== '' && in_array($normalized, config('whatsapp.inbox.opt_out_keywords', []), true);
    }

    private function optOut(WhatsAppConversation $conversation): void
    {
        $conversation->update(['opted_out_at' => now()]);

        $user = $conversation->user;

        if ($user !== null && $user->whatsapp_opt_in) {
            $user->forceFill(['whatsapp_opt_in' => false])->save();

            Log::info('WhatsApp: usuario dado de baja de los avisos por mensaje', ['user_id' => $user->id]);
        }
    }

    /**
     * Descarga el adjunto al disco privado. Un error no impide guardar el mensaje.
     */
    private function downloadMedia(WhatsAppMessage $message): void
    {
        $info = $this->whatsApp->getMedia($message->media_id);

        if ($info === null) {
            $message->update(['error_message' => 'No se pudo obtener el adjunto de WhatsApp.']);

            return;
        }

        $maxBytes = (int) config('whatsapp.inbox.max_media_bytes');

        if ($info['file_size'] !== null && $info['file_size'] > $maxBytes) {
            $message->update(['error_message' => 'Adjunto demasiado grande, no se descargó ('.round($info['file_size'] / 1048576, 1).' MB).']);

            return;
        }

        $content = $this->whatsApp->downloadMedia($info['url']);

        if ($content === null || strlen($content) > $maxBytes) {
            $message->update(['error_message' => 'No se pudo descargar el adjunto de WhatsApp.']);

            return;
        }

        $mime = trim(explode(';', (string) ($info['mime_type'] ?? $message->media_mime ?? 'application/octet-stream'))[0]);
        $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? 'bin';
        $path = "whatsapp/{$message->whatsapp_conversation_id}/{$message->id}.{$extension}";

        Storage::disk(config('whatsapp.inbox.media_disk', 'local'))->put($path, $content);

        $message->update([
            'media_path' => $path,
            'media_mime' => $mime,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyStatus(array $data): void
    {
        $wamid = $data['id'] ?? null;
        $status = $data['status'] ?? null;

        if (blank($wamid) || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $error = $this->describeStatusError($data['errors'] ?? []);
        $message = WhatsAppMessage::where('wamid', $wamid)->first();

        if ($message !== null) {
            if ($this->shouldApplyStatus($message->status, $status)) {
                $message->update([
                    'status' => $status,
                    'error_message' => $status === 'failed' ? $error : $message->error_message,
                ]);
            }

            return;
        }

        $log = WhatsAppMessageLog::where('whatsapp_message_id', $wamid)->first();

        if ($log !== null && $this->shouldApplyStatus($log->delivery_status, $status)) {
            $log->update([
                'delivery_status' => $status,
                'delivery_status_at' => isset($data['timestamp']) ? Carbon::createFromTimestamp((int) $data['timestamp']) : now(),
                'delivery_error' => $status === 'failed' ? $error : $log->delivery_error,
            ]);
        }
    }

    /**
     * Los estados solo avanzan (sent → delivered → read); "failed" siempre se aplica.
     */
    private function shouldApplyStatus(?string $current, string $new): bool
    {
        if ($new === 'failed') {
            return $current !== 'failed';
        }

        if ($current === 'failed') {
            return false;
        }

        return (WhatsAppMessage::STATUS_RANK[$new] ?? 0) > (WhatsAppMessage::STATUS_RANK[$current] ?? 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function describeStatusError(array $errors): ?string
    {
        $error = $errors[0] ?? null;

        if (! is_array($error)) {
            return null;
        }

        $text = $error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? null;
        $code = $error['code'] ?? null;

        return $text === null ? null : ($code ? "({$code}) {$text}" : $text);
    }
}
