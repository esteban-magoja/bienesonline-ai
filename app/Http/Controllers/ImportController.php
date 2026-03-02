<?php

namespace App\Http\Controllers;

use App\Jobs\ImportListingsJob;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ImportController extends Controller
{
    /**
     * Dispara la importación de anuncios desde el proyecto legacy.
     */
    public function trigger(Request $request)
    {
        $user = $request->user();

        $legacyUrls = config('import.legacy_urls', []);

        if (empty($legacyUrls)) {
            return response()->json(['message' => __('import.not_configured')], 503);
        }

        // Validar país seleccionado
        $country = $request->input('country');
        if (empty($country) || !isset($legacyUrls[$country])) {
            return response()->json(['message' => __('import.invalid_country')], 422);
        }

        // Evitar doble importación si ya hay un job en curso
        $running = ImportJob::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($running) {
            return response()->json([
                'message' => __('import.already_running'),
                'job'     => $running,
            ], 409);
        }

        $legacyUrl = rtrim($legacyUrls[$country], '/');

        // Llamar al API del proyecto viejo
        try {
            $response = Http::timeout(config('import.api_timeout', 30))
                ->get($legacyUrl . '/app/export-listings.php', ['email' => $user->email]);
        } catch (\Exception $e) {
            return response()->json(['message' => __('import.connection_error')], 503);
        }

        if (!$response->successful()) {
            return response()->json(['message' => __('import.api_error')], 502);
        }

        $listings = $response->json('listings', []);

        if (empty($listings)) {
            return response()->json(['message' => __('import.no_listings')], 200);
        }

        // Crear registro de seguimiento
        $importJob = ImportJob::create([
            'user_id'        => $user->id,
            'status'         => 'pending',
            'total_listings' => count($listings),
        ]);

        // Despachar job a la cola
        ImportListingsJob::dispatch($importJob->id, $user->id, $listings);

        return response()->json([
            'message' => __('import.started'),
            'job'     => $importJob,
        ], 202);
    }

    /**
     * Devuelve el estado actual del import job (para polling desde el frontend).
     */
    public function status(Request $request, int $jobId)
    {
        $job = ImportJob::where('id', $jobId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'status'             => $job->status,
            'total_listings'     => $job->total_listings,
            'imported_listings'  => $job->imported_listings,
            'skipped_listings'   => $job->skipped_listings,
            'failed_listings'    => $job->failed_listings,
            'progress_percent'   => $job->progressPercent(),
            'error_message'      => $job->error_message,
        ]);
    }

    /**
     * Devuelve el último import job del usuario (para saber si hay uno en curso al cargar el dashboard).
     */
    public function latest(Request $request)
    {
        $job = ImportJob::where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$job) {
            return response()->json(null);
        }

        return response()->json([
            'id'                 => $job->id,
            'status'             => $job->status,
            'total_listings'     => $job->total_listings,
            'imported_listings'  => $job->imported_listings,
            'skipped_listings'   => $job->skipped_listings,
            'failed_listings'    => $job->failed_listings,
            'progress_percent'   => $job->progressPercent(),
            'error_message'      => $job->error_message,
        ]);
    }
}
