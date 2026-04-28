<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNowService
{
    private bool $enabled;
    private string $apiKey;
    private string $host;
    private string $endpoint;
    private bool $logging;

    public function __construct()
    {
        $this->enabled  = config('indexnow.enabled', false);
        $this->apiKey   = config('indexnow.api_key', '');
        $this->host     = config('indexnow.host', '');
        $this->endpoint = config('indexnow.endpoint', 'https://api.indexnow.org/indexnow');
        $this->logging  = config('indexnow.logging', true);
    }

    /**
     * Envía una lista de URLs a IndexNow para indexación inmediata.
     *
     * @param  array<int, string>  $urls
     */
    public function submitUrls(array $urls): bool
    {
        if (!$this->enabled) {
            if ($this->logging) {
                Log::channel('stack')->info('IndexNow deshabilitado. URLs no enviadas.', ['urls' => $urls]);
            }
            return false;
        }

        if (empty($this->apiKey) || empty($this->host)) {
            Log::warning('IndexNow: faltan credenciales (INDEXNOW_API_KEY o INDEXNOW_HOST)');
            return false;
        }

        if (empty($urls)) {
            return false;
        }

        $keyLocation = "https://{$this->host}/{$this->apiKey}.txt";

        $payload = [
            'host'        => $this->host,
            'key'         => $this->apiKey,
            'keyLocation' => $keyLocation,
            'urlList'     => array_values($urls),
        ];

        try {
            $response = Http::timeout(10)
                ->post($this->endpoint, $payload);

            if ($this->logging) {
                Log::channel('stack')->info('IndexNow: URLs enviadas.', [
                    'status' => $response->status(),
                    'urls'   => $urls,
                ]);
            }

            // IndexNow retorna 200 (OK) o 202 (Accepted) en éxito
            if ($response->successful() || $response->status() === 202) {
                return true;
            }

            Log::warning('IndexNow: respuesta inesperada.', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'urls'   => $urls,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning('IndexNow: error al enviar URLs.', [
                'error' => $e->getMessage(),
                'urls'  => $urls,
            ]);

            return false;
        }
    }
}
