<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Http;

/**
 * Descarga feeds remotos indicados por el usuario, evitando SSRF y errores de caché del origen.
 */
class FeedFetcher
{
    private const MAX_REDIRECTS = 3;

    /**
     * @throws ImportSourceException
     */
    public function fetch(string $url, ?callable $validator = null): string
    {
        $attempts = (int) config('import.feed_attempts', 3);
        $lastError = __('import.feed_unreachable');

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $body = $this->download($this->withCacheBuster($url));

                if ($validator === null || $validator($body)) {
                    return $body;
                }

                $lastError = __('import.feed_invalid');
            } catch (ImportSourceException $e) {
                if ($e->getCode() === 422) {
                    throw $e; // URL bloqueada: no tiene sentido reintentar
                }
                $lastError = $e->getMessage();
            } catch (\Throwable $e) {
                $lastError = __('import.feed_unreachable');
            }

            if ($i < $attempts) {
                usleep((int) config('import.feed_retry_delay_ms', 1500) * 1000);
            }
        }

        throw new ImportSourceException($lastError);
    }

    /**
     * Valida que la URL sea http(s) y apunte a un host público.
     *
     * @throws ImportSourceException
     */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new ImportSourceException(__('import.url_not_allowed'), 422);
        }

        if (! config('import.block_private_hosts', true)) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveHost($host);

        if ($ips === []) {
            throw new ImportSourceException(__('import.feed_unreachable'));
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new ImportSourceException(__('import.url_not_allowed'), 422);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveHost(string $host): array
    {
        $ips = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    /**
     * Agrega un parámetro aleatorio: algunos feeds (Wasi) fallan por caché del origen.
     */
    private function withCacheBuster(string $url): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . random_int(100000, 999999);
    }

    private function download(string $url): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertSafeUrl($url);

            $response = Http::timeout((int) config('import.feed_timeout', 30))
                ->withoutRedirecting()
                ->withHeaders([
                    'User-Agent' => 'BienesOnline-Import/1.0',
                    'Accept'     => 'application/xml, text/xml, application/json, */*',
                    'Cache-Control' => 'no-cache',
                ])
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    break;
                }
                $url = $this->absoluteUrl($url, $location);
                continue;
            }

            if (! $response->successful()) {
                throw new ImportSourceException(__('import.feed_unreachable'));
            }

            $body = $response->body();

            if (strlen($body) > (int) config('import.feed_max_bytes', 20 * 1024 * 1024)) {
                throw new ImportSourceException(__('import.feed_too_large'));
            }

            return $body;
        }

        throw new ImportSourceException(__('import.feed_unreachable'));
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $p = parse_url($base);
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        return $origin . rtrim(dirname($p['path'] ?? '/'), '/') . '/' . $location;
    }
}
