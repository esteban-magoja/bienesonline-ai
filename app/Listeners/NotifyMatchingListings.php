<?php

namespace App\Listeners;

use App\Events\PropertyRequestCreated;
use App\Models\User;
use App\Notifications\PropertyMatchAdNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotifyMatchingListings implements ShouldQueue
{
    use InteractsWithQueue;

    /** Hora de inicio del rango permitido de envío (inclusive). */
    private const SEND_HOUR_START = 11;

    /** Hora de fin del rango permitido de envío (exclusive). */
    private const SEND_HOUR_END = 18;

    public function __construct(private PropertyMatchingService $matchingService) {}

    public function handle(PropertyRequestCreated $event): void
    {
        if ($this->isOutsideTimeWindow()) {
            return;
        }

        $propertyRequest = $event->propertyRequest;
        $minScore        = config('matching.min_score_to_notify', 70);

        try {
            $matches = $this->matchingService->findMatchesForRequest($propertyRequest, 50);

            $qualityMatches = $matches->filter(
                fn ($listing) => ($listing->match_score ?? 0) >= $minScore
            );

            Log::info("PropertyRequest #{$propertyRequest->id} created. Found {$qualityMatches->count()} quality matches (score >= {$minScore})");

            // Agrupar por user_id: solo un mensaje por usuario aunque tenga varios anuncios
            $listingsByOwner = $qualityMatches->groupBy('user_id');
            $totalMatchesForRequest = $qualityMatches->count();

            foreach ($listingsByOwner as $ownerId => $ownerListings) {
                $user = User::find($ownerId);

                if (!$user || empty($user->movil) || !$user->whatsapp_opt_in) {
                    Log::debug("NotifyMatchingListings: user #{$ownerId} sin móvil o sin opt-in, saltando.");
                    continue;
                }

                $user->notify(new PropertyMatchAdNotification(
                    propertyRequestId: $propertyRequest->id,
                ));

                Log::info("Notified listing owner #{$user->id} about new request #{$propertyRequest->id} ({$ownerListings->count()} matching listings)");
            }
        } catch (\Exception $e) {
            Log::error("Error processing listing matches for PropertyRequest #{$propertyRequest->id}: " . $e->getMessage());
        }
    }

    /**
     * Si el job corre fuera del rango horario configurado (11-18), lo re-encola
     * con retraso hasta las 11:00 del mismo día o del día siguiente.
     * Devuelve true si se re-encoló (ejecución debe detenerse), false si está en horario.
     */
    private function isOutsideTimeWindow(): bool
    {
        $now  = Carbon::now();
        $hour = (int) $now->format('H');

        if ($hour >= self::SEND_HOUR_START && $hour < self::SEND_HOUR_END) {
            return false;
        }

        $next11am = $now->copy()->setTime(self::SEND_HOUR_START, 0, 0);
        if ($now->gte($next11am)) {
            $next11am->addDay();
        }

        $delaySeconds = max(1, $next11am->getTimestamp() - $now->getTimestamp());

        Log::info("NotifyMatchingListings: fuera de horario ({$hour}h), reagendando en {$delaySeconds}s hasta las 11:00.");

        $this->release($delaySeconds);

        return true;
    }

    public function failed(PropertyRequestCreated $event, \Throwable $exception): void
    {
        Log::error("Failed to notify listing owners for PropertyRequest #{$event->propertyRequest->id}: " . $exception->getMessage());
    }
}
