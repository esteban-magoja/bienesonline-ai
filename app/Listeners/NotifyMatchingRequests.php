<?php

namespace App\Listeners;

use App\Events\PropertyListingCreated;
use App\Models\User;
use App\Notifications\PropertyMatchFoundNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Avisa a los dueños de solicitudes que coinciden con un anuncio nuevo o reactivado.
 * Excluye las solicitudes legacy (asociadas al usuario de import:legacy-requests),
 * envía un solo aviso por usuario aunque tenga varias solicitudes compatibles
 * y como máximo 1 aviso por usuario y día.
 */
class NotifyMatchingRequests implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(protected PropertyMatchingService $matchingService) {}

    public function handle(PropertyListingCreated $event): void
    {
        $listing      = $event->listing;
        $minScore     = config('matching.min_score_to_notify', 70);
        $legacyUserId = (int) config('import.legacy_requests_user_id', 1);

        try {
            // Sin límite: las solicitudes legacy son mayoría y un take() previo dejaría afuera a las reales.
            $qualityMatches = $this->matchingService->getAllMatchesForListing($listing)
                ->filter(fn ($request) => $request->match_score >= $minScore)
                ->reject(fn ($request) => (int) $request->user_id === $legacyUserId);

            Log::info("PropertyListing #{$listing->id} created. Found {$qualityMatches->count()} quality matches (score >= {$minScore}, sin legacy)");

            // Un aviso por usuario: la solicitud de mayor score
            $bestRequestByUser = $qualityMatches
                ->sortByDesc('match_score')
                ->unique('user_id');

            foreach ($bestRequestByUser as $request) {
                $user = User::find($request->user_id);

                if (!$user) {
                    continue;
                }

                // Gate atómico (jobs concurrentes) + respaldo en DB (cache vaciado).
                if (!$this->acquireDailyLock($user->id) || $this->wasNotifiedToday($user)) {
                    Log::debug("NotifyMatchingRequests: user #{$user->id} ya recibió el aviso de hoy, saltando.");
                    continue;
                }

                try {
                    $user->notifyNow(new PropertyMatchFoundNotification(
                        $request,
                        $listing,
                        (int) $request->match_score,
                        $request->match_details ?? []
                    ));

                    Log::info("Notified user #{$user->id} about match: PropertyRequest #{$request->id} ↔ PropertyListing #{$listing->id} (score: {$request->match_score})");
                } catch (\Throwable $e) {
                    // Liberar el lock para que un próximo anuncio del día pueda reintentar.
                    Cache::forget($this->lockKey($user->id));

                    Log::warning("NotifyMatchingRequests: falló el envío a user #{$user->id}: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error processing matches for PropertyListing #{$listing->id}: " . $e->getMessage());
            // No lanzar excepción para no bloquear la creación del anuncio
        }
    }

    /**
     * Cache::add() es atómica: solo retorna true la primera vez en el día para ese usuario.
     */
    private function acquireDailyLock(int $userId): bool
    {
        return Cache::add($this->lockKey($userId), 1, now()->secondsUntilEndOfDay() + 1);
    }

    private function lockKey(int $userId): string
    {
        return "match_found_notify_throttle_{$userId}_" . now()->format('Y-m-d');
    }

    /**
     * El canal database de PropertyMatchFoundNotification deja registro en `notifications`.
     */
    private function wasNotifiedToday(User $user): bool
    {
        return $user->notifications()
            ->where('type', PropertyMatchFoundNotification::class)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }

    public function failed(PropertyListingCreated $event, \Throwable $exception): void
    {
        Log::error("Failed to process matches for PropertyListing #{$event->listing->id}: " . $exception->getMessage());
    }
}
