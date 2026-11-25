<?php

namespace App\Services\WhatsAppInbox;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Acciones de la bandeja de WhatsApp del admin: responder, escribir con un template y marcar como leído.
 */
class WhatsAppInboxService
{
    public const TEMPLATES_CACHE_KEY = 'whatsapp_inbox_templates';

    public function __construct(private readonly WhatsAppService $whatsApp)
    {
    }

    /**
     * Respuesta con texto libre: solo dentro de las 24 h desde el último mensaje del usuario.
     * Si Meta rechaza el envío, el mensaje queda guardado como fallido con el motivo.
     */
    public function sendText(WhatsAppConversation $conversation, string $text, ?User $sender): WhatsAppMessage
    {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException('El mensaje está vacío.');
        }

        if (! $conversation->isServiceWindowOpen()) {
            throw new InvalidArgumentException('Pasaron más de 24 horas desde el último mensaje del usuario: solo se puede escribir con un template aprobado.');
        }

        $wamid = $this->whatsApp->sendText($conversation->wa_id, $text);

        return $this->storeOutgoing($conversation, [
            'type' => 'text',
            'body' => $text,
        ], $wamid, $sender);
    }

    /**
     * Mensaje con un template aprobado (válido en cualquier momento).
     *
     * @param  array<int|string, string>  $params  variables del cuerpo: posicionales (1, 2...) o nombradas
     */
    public function sendTemplate(WhatsAppConversation $conversation, string $templateName, string $language, array $params, ?User $sender): WhatsAppMessage
    {
        $template = collect($this->availableTemplates())
            ->first(fn (array $item): bool => $item['name'] === $templateName && $item['language'] === $language);

        if ($template === null) {
            throw new InvalidArgumentException('El template no existe, no está aprobado o tiene partes que la bandeja no completa (imágenes, botones con variables).');
        }

        $values = [];

        foreach ($template['variables'] as $variable) {
            $value = trim((string) ($params[$variable] ?? ''));

            if ($value === '') {
                throw new InvalidArgumentException("Falta completar la variable {{{$variable}}}.");
            }

            $values[$variable] = $value;
        }

        // Variables posicionales: Meta las toma en orden; nombradas: por nombre
        $bodyParams = $template['named'] ? $values : array_values($values);
        $wamid = $this->whatsApp->sendTemplate($conversation->wa_id, $templateName, $language, $bodyParams);

        return $this->storeOutgoing($conversation, [
            'type' => 'template',
            'template_name' => $templateName,
            'body' => self::renderTemplate($template['body'], $values),
        ], $wamid, $sender);
    }

    /**
     * Marca la conversación como leída y avisa a WhatsApp (tildes azules) por el último mensaje recibido.
     */
    public function markAsRead(WhatsAppConversation $conversation): void
    {
        if ($conversation->unread_count === 0) {
            return;
        }

        $conversation->update(['unread_count' => 0]);

        $lastInboundWamid = $conversation->messages()
            ->where('direction', WhatsAppMessage::DIRECTION_IN)
            ->whereNotNull('wamid')
            ->latest('id')
            ->value('wamid');

        if ($lastInboundWamid !== null) {
            $this->whatsApp->markAsRead($lastInboundWamid);
        }
    }

    /**
     * Templates aprobados que se pueden enviar desde la bandeja: solo con variables en el cuerpo
     * (sin encabezados con imagen/video/documento ni botones con URL variable). Cache de 10 minutos.
     *
     * @return array<int, array{key: string, name: string, language: string, category: ?string, body: string, variables: array<int, string>, named: bool}>
     */
    public function availableTemplates(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::TEMPLATES_CACHE_KEY);
        }

        $cached = Cache::get(self::TEMPLATES_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $templates = collect($this->whatsApp->getApprovedTemplates())
            ->map(fn (array $template): ?array => $this->describeTemplate($template))
            ->filter()
            ->sortBy('key')
            ->values()
            ->all();

        // Sin templates (o con error de Meta) se reintenta antes, para no esperar 10 minutos
        Cache::put(self::TEMPLATES_CACHE_KEY, $templates, $templates === [] ? 60 : 600);

        return $templates;
    }

    /**
     * Reemplaza las variables {{1}} / {{nombre}} del cuerpo por los valores enviados.
     *
     * @param  array<int|string, string>  $values
     */
    public static function renderTemplate(string $body, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/',
            fn (array $match): string => (string) ($values[$match[1]] ?? $match[0]),
            $body,
        );
    }

    /**
     * @param  array{name: string, language: string, category: ?string, components: array<int, array<string, mixed>>}  $template
     * @return array{key: string, name: string, language: string, category: ?string, body: string, variables: array<int, string>, named: bool}|null
     */
    private function describeTemplate(array $template): ?array
    {
        if (($template['category'] ?? null) === 'AUTHENTICATION') {
            return null;
        }

        $body = '';

        foreach ($template['components'] as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($component['format'] ?? 'TEXT'));

                if ($format !== 'TEXT' || preg_match('/\{\{.+?\}\}/', (string) ($component['text'] ?? ''))) {
                    return null;
                }
            }

            if ($type === 'BUTTONS') {
                foreach ($component['buttons'] ?? [] as $button) {
                    if (preg_match('/\{\{.+?\}\}/', (string) ($button['url'] ?? ''))) {
                        return null;
                    }
                }
            }

            if ($type === 'BODY') {
                $body = (string) ($component['text'] ?? '');
            }
        }

        if ($body === '') {
            return null;
        }

        preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $body, $matches);
        $variables = array_values(array_unique($matches[1]));
        $named = collect($variables)->contains(fn (string $variable): bool => ! ctype_digit($variable));

        if (! $named) {
            sort($variables, SORT_NUMERIC);
        }

        return [
            'key' => $template['name'].'|'.$template['language'],
            'name' => $template['name'],
            'language' => $template['language'],
            'category' => $template['category'] ?? null,
            'body' => $body,
            'variables' => $variables,
            'named' => $named,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeOutgoing(WhatsAppConversation $conversation, array $attributes, string|false $wamid, ?User $sender): WhatsAppMessage
    {
        $sent = $wamid !== false;

        $message = $conversation->messages()->create($attributes + [
            'wamid' => $sent && str_starts_with($wamid, 'wamid.') ? $wamid : null,
            'direction' => WhatsAppMessage::DIRECTION_OUT,
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : ($this->whatsApp->lastError() ?? 'No se pudo enviar el mensaje.'),
            'sent_by' => $sender?->id,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => $message->sent_at,
            'last_message_preview' => WhatsAppWebhookProcessor::preview($message->type, $message->body),
        ]);

        return $message;
    }
}
