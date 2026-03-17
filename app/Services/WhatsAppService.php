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
     * @param string $to   Número en formato internacional (ej: +5491112345678)
     * @param string $templateName  Nombre del template aprobado
     * @param string $languageCode  Código de idioma (ej: es_AR, en_US)
     * @param array  $bodyParams    Parámetros del cuerpo del template (texto variable)
     * @return bool
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $bodyParams = []): bool
    {
        if (!$this->enabled) {
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
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $parameters,
                ],
            ];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(15)
                ->post("{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                if (config('whatsapp.logging')) {
                    Log::info('WhatsApp: template enviado correctamente', [
                        'to' => $to,
                        'template' => $templateName,
                        'message_id' => $response->json('messages.0.id'),
                    ]);
                }
                return true;
            }

            Log::error('WhatsApp: error al enviar template', [
                'to' => $to,
                'template' => $templateName,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp: excepción al enviar template', [
                'to' => $to,
                'template' => $templateName,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Enviar un mensaje de texto libre (solo válido dentro de la ventana de 24h
     * tras una interacción del usuario).
     */
    public function sendText(string $to, string $message): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::error('WhatsApp: faltan credenciales para sendText');
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
                return true;
            }

            Log::error('WhatsApp: error al enviar texto', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp: excepción al enviar texto', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
