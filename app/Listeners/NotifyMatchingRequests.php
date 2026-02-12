<?php

namespace App\Listeners;

use App\Events\PropertyListingCreated;
use App\Models\User;
use App\Notifications\PropertyMatchFoundNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyMatchingRequests implements ShouldQueue
{
    use InteractsWithQueue;

    protected PropertyMatchingService $matchingService;

    /**
     * Create the event listener.
     */
    public function __construct(PropertyMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    /**
     * Handle the event.
     */
    public function handle(PropertyListingCreated $event): void
    {
        $listing = $event->listing;

        try {
            // Buscar solicitudes que coincidan con este anuncio
            $matches = $this->matchingService->findMatchesForListing($listing, 20);

            // Obtener el score mínimo de configuración (default: 70)
            $minScore = config('matching.min_score_to_notify', 70);

            // Filtrar solo matches de calidad
            $qualityMatches = $matches->filter(function ($request) use ($minScore) {
                return $request->match_score >= $minScore;
            });

            Log::info("PropertyListing #{$listing->id} created. Found {$qualityMatches->count()} quality matches (score >= {$minScore})");

            // Notificar a cada solicitante
            foreach ($qualityMatches as $request) {
                // Obtener el usuario de la solicitud
                $user = User::find($request->user_id);
                
                if ($user) {
                    // Enviar notificación
                    $user->notify(new PropertyMatchFoundNotification(
                        $request,
                        $listing,
                        (int) $request->match_score,
                        $request->match_details ?? []
                    ));

                    Log::info("Notified user #{$user->id} about match: PropertyRequest #{$request->id} ↔ PropertyListing #{$listing->id} (score: {$request->match_score})");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error processing matches for PropertyListing #{$listing->id}: " . $e->getMessage());
            // No lanzar excepción para no bloquear la creación del anuncio
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(PropertyListingCreated $event, \Throwable $exception): void
    {
        Log::error("Failed to process matches for PropertyListing #{$event->listing->id}: " . $exception->getMessage());
    }
}
