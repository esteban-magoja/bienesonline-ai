<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Vector;

class EmbeddingService
{
    private const EMBEDDING_DIMENSIONS = 1536;

    /**
     * Generate an embedding for the supplied text parts.
     *
     * @param array<int, mixed> $parts
     */
    public function generate(array $parts): ?Vector
    {
        $text = collect($parts)
            ->filter(fn (mixed $part): bool => filled($part))
            ->map(fn (mixed $part): string => trim((string) $part))
            ->filter()
            ->implode(' ');

        if ($text === '') {
            return null;
        }

        $apiKey = config('openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('Embedding generation skipped because OPENAI_API_KEY is not configured.');

            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->retry([100, 200], throw: false)
                ->timeout(30)
                ->connectTimeout(10)
                ->post('https://api.openai.com/v1/embeddings', [
                    'input' => $text,
                    'model' => config('openai.embeddings_model', 'text-embedding-3-small'),
                ]);

            $embedding = $response->json('data.0.embedding');

            if (! $response->successful() || ! is_array($embedding)) {
                Log::error('Embedding generation failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            if (count($embedding) !== self::EMBEDDING_DIMENSIONS) {
                Log::error('Embedding generation returned an unexpected vector size.', [
                    'dimensions' => count($embedding),
                    'expected' => self::EMBEDDING_DIMENSIONS,
                ]);

                return null;
            }

            return new Vector($embedding);
        } catch (\Throwable $exception) {
            Log::error('Exception generating embedding.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
