<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiUrl;
    private string $accessToken;
    private string $phoneNumberId;
    private string $apiVersion;
    private bool $enabled;

    /** Motivo del último envío fallido (para mostrarlo en el admin). */
    private ?string $lastError = null;

    public function __construct()
    {
        $this->enabled = config('whatsapp.enabled', false);
        $this->accessToken = config('whatsapp.access_token', '');
        $this->phoneNumberId = config('whatsapp.phone_number_id', '');
        $this->apiVersion = config('whatsapp.api_version', 'v19.0');
        $this->apiUrl = config('whatsapp.api_url', 'https://graph.facebook.com');
    }

    /**
     * Enviar un mensaje usando un template aprobado en Meta Business Suite.
     *
     * @param string $to            Número en formato internacional (ej: +5491112345678)
     * @param string $templateName  Nombre del template aprobado
     * @param string $languageCode  Código de idioma (ej: es, en)
     * @param array  $bodyParams    Parámetros del cuerpo: {{1}}, {{2}}, etc.
     * @param array  $buttonParams  Parámetros del botón URL dinámico ({{token}} u otros)
     * @return string|false  El message_id de Meta si se envió, false en caso contrario
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $bodyParams = [], array $buttonParams = []): string|false
    {
        $this->lastError = null;

        if (!$this->enabled) {
            $this->lastError = 'El envío de WhatsApp está deshabilitado (WHATSAPP_ENABLED).';

            if (config('whatsapp.logging')) {
                Log::channel('stack')->info('WhatsApp deshabilitado. Template no enviado.', [
                    'to' => $to,
                    'template' => $templateName,
                ]);
            }
            return false;
        }

        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::error('WhatsApp: faltan credenciales (WHATSAPP_ACCESS_TOKEN o WHATSAPP_PHONE_NUMBER_ID)');
            $this->lastError = 'Faltan credenciales de WhatsApp.';
            return false;
        }

        // Meta API requiere el número sin el signo '+'
        $phone = ltrim($to, '+');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        $components = [];

        if (!empty($bodyParams)) {
            $parameters = [];
            foreach ($bodyParams as $key => $value) {
                $param = ['type' => 'text', 'text' => (string) $value];
                // Array asociativo → variable nombrada (ej: {{customer_name}})
                if (is_string($key)) {
                    $param['parameter_name'] = $key;
                }
                $parameters[] = $param;
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        // Botón URL con sufijo dinámico ({{token}} en Meta)
        if (!empty($buttonParams)) {
            foreach ($buttonParams as $index => $value) {
                $components[] = [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => (string) $index,
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $value],
                    ],
                ];
            }
        }

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post("{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id') ?? '';
                if (config('whatsapp.logging')) {
                    Log::info('WhatsApp: template enviado correctamente', [
                        'to' => $to,
                        'template' => $templateName,
                        'message_id' => $messageId,
                    ]);
                }
                return $messageId ?: 'sent';
            }

            Log::error('WhatsApp: error al enviar template', [
                'to' => $to,
                'template' => $templateName,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            $this->lastError = $this->describeError($response);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp: excepción al enviar template', [
                'to' => $to,
                'template' => $templateName,
                'message' => $e->getMessage(),
            ]);
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Enviar un mensaje de texto libre (solo válido dentro de la ventana de 24h
     * tras una interacción del usuario).
     *
     * @return string|false  El message_id de Meta si se envió, false en caso contrario
     */
    public function sendText(string $to, string $message): string|false
    {
        $this->lastError = null;

        if (!$this->enabled) {
            $this->lastError = 'El envío de WhatsApp está deshabilitado (WHATSAPP_ENABLED).';
            return false;
        }

        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::error('WhatsApp: faltan credenciales para sendText');
            $this->lastError = 'Faltan credenciales de WhatsApp.';
            return false;
        }

        $phone = ltrim($to, '+');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post("{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                return $response->json('messages.0.id') ?? 'sent';
            }

            Log::error('WhatsApp: error al enviar texto', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            $this->lastError = $this->describeError($response);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp: excepción al enviar texto', [
                'message' => $e->getMessage(),
            ]);
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Motivo del último sendTemplate()/sendText() fallido, o null si salió bien.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Marca un mensaje recibido como leído (el usuario ve los dos tildes azules).
     */
    public function markAsRead(string $messageId): bool
    {
        if (!$this->enabled || empty($this->accessToken) || empty($this->phoneNumberId)) {
            return false;
        }

        try {
            return Http::withToken($this->accessToken)
                ->timeout(10)
                ->post("{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ])
                ->successful();
        } catch (\Exception $e) {
            Log::warning('WhatsApp: no se pudo marcar como leído', ['message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Datos de un adjunto recibido (la URL de descarga vence a los pocos minutos).
     *
     * @return array{url: string, mime_type: ?string, file_size: ?int}|null
     */
    public function getMedia(string $mediaId): ?array
    {
        if (empty($this->accessToken)) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->get("{$this->apiUrl}/{$this->apiVersion}/{$mediaId}");
        } catch (\Exception $e) {
            Log::warning('WhatsApp: error al consultar un adjunto', ['media_id' => $mediaId, 'message' => $e->getMessage()]);

            return null;
        }

        if (!$response->successful() || blank($response->json('url'))) {
            Log::warning('WhatsApp: adjunto no disponible', ['media_id' => $mediaId, 'status' => $response->status()]);

            return null;
        }

        return [
            'url' => (string) $response->json('url'),
            'mime_type' => $response->json('mime_type'),
            'file_size' => $response->json('file_size') !== null ? (int) $response->json('file_size') : null,
        ];
    }

    /**
     * Descarga el contenido de un adjunto (la URL de Meta exige el token).
     */
    public function downloadMedia(string $url): ?string
    {
        if (empty($this->accessToken)) {
            return null;
        }

        try {
            $response = Http::withToken($this->accessToken)->timeout(60)->get($url);
        } catch (\Exception $e) {
            Log::warning('WhatsApp: error al descargar un adjunto', ['message' => $e->getMessage()]);

            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * Templates aprobados de la cuenta de WhatsApp Business (para escribir fuera de la ventana de 24 h).
     *
     * @return array<int, array{name: string, language: string, category: ?string, components: array<int, array<string, mixed>>}>
     */
    public function getApprovedTemplates(): array
    {
        $businessAccountId = config('whatsapp.business_account_id');

        if (empty($this->accessToken) || empty($businessAccountId)) {
            return [];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->get("{$this->apiUrl}/{$this->apiVersion}/{$businessAccountId}/message_templates", [
                    'status' => 'APPROVED',
                    'fields' => 'name,language,status,category,components',
                    'limit' => 200,
                ]);
        } catch (\Exception $e) {
            Log::warning('WhatsApp: error al obtener templates', ['message' => $e->getMessage()]);

            return [];
        }

        if (!$response->successful()) {
            Log::warning('WhatsApp: error al obtener templates', ['status' => $response->status(), 'error' => $response->json('error')]);

            return [];
        }

        return collect($response->json('data', []))
            ->filter(fn ($template): bool => is_array($template) && ($template['status'] ?? 'APPROVED') === 'APPROVED')
            ->map(fn (array $template): array => [
                'name' => (string) $template['name'],
                'language' => (string) $template['language'],
                'category' => $template['category'] ?? null,
                'components' => $template['components'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Texto legible del error que devolvió Meta.
     */
    private function describeError(\Illuminate\Http\Client\Response $response): string
    {
        $details = $response->json('error.error_data.details');
        $message = $response->json('error.message');
        $code = $response->json('error.code');

        $text = $details ?: ($message ?: 'Error HTTP '.$response->status());

        return $code ? "({$code}) {$text}" : $text;
    }
}
