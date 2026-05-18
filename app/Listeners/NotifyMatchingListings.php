<?php

namespace App\Listeners;

use App\Events\PropertyRequestCreated;
use App\Models\User;
use App\Models\WhatsAppMessageLog;
use App\Notifications\PropertyMatchAdNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyMatchingListings implements ShouldQueue
{
    use InteractsWithQueue;

    /** Hora de inicio del rango permitido de envío (inclusive). */
    private const SEND_HOUR_START = 10;

    /** Hora de fin del rango permitido de envío (exclusive, 24 = medianoche). */
    private const SEND_HOUR_END = 24;

    /** Mínimo de minutos entre mensajes al mismo usuario (flujo normal). */
    private const THROTTLE_MINUTES = 60;

    /** Mínimo de minutos entre mensajes al mismo usuario durante importación legacy (1 día). */
    private const THROTTLE_MINUTES_LEGACY = 1440;

    public function __construct(private PropertyMatchingService $matchingService) {}

    public function handle(PropertyRequestCreated $event): void
    {
        if ($this->isOutsideTimeWindow()) {
            return;
        }

        $propertyRequest = $event->propertyRequest;
        $minScore        = config('matching.min_score_to_notify', 70);
        $throttleMinutes = $event->isLegacyImport ? self::THROTTLE_MINUTES_LEGACY : self::THROTTLE_MINUTES;

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

                // Gate atómico via cache: previene duplicados en jobs concurrentes.
                // Cache::add() es atómica — solo retorna true la primera vez que se llama
                // con esa key, incluso con múltiples workers corriendo en paralelo.
                if (!$this->acquireThrottleLock($user->id, $throttleMinutes)) {
                    Log::debug("NotifyMatchingListings: user #{$ownerId} ya tiene lock de throttle ({$throttleMinutes}min), saltando.");
                    continue;
                }

                // Respaldo DB: cubre el caso de que el cache se haya vaciado (restart, etc.)
                if ($this->wasRecentlyNotified($user, $throttleMinutes)) {
                    Log::debug("NotifyMatchingListings: user #{$ownerId} notificado hace menos de {$throttleMinutes}min (DB check), saltando.");
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
     * Intenta adquirir el lock de throttle para el usuario.
     * Usa Cache::add() que es atómica — solo retorna true la primera vez
     * que se llama con esa key, previniendo duplicados en jobs concurrentes.
     */
    private function acquireThrottleLock(int $userId, int $minutes): bool
    {
        if ($minutes >= 1440) {
            // Throttle diario: key por día, TTL hasta fin del día
            $cacheKey = "wa_notify_throttle_{$userId}_" . now()->format('Y-m-d');
            $ttl      = now()->secondsUntilEndOfDay() + 1;
        } else {
            // Throttle por minutos: TTL exacto en segundos
            $cacheKey = "wa_notify_throttle_{$userId}_" . now()->format('Y-m-d-H');
            $ttl      = $minutes * 60;
        }

        return Cache::add($cacheKey, 1, $ttl);
    }

    /**
     * Devuelve true si el usuario recibió un mensaje WhatsApp
     * en los últimos $minutes minutos.
     */
    private function wasRecentlyNotified(User $user, int $minutes): bool
    {
        return WhatsAppMessageLog::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->exists();
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

        $nextWindowStart = $now->copy()->setTime(self::SEND_HOUR_START, 0, 0);
        if ($now->gte($nextWindowStart)) {
            $nextWindowStart->addDay();
        }

        $delaySeconds = max(1, $nextWindowStart->getTimestamp() - $now->getTimestamp());

        Log::info("NotifyMatchingListings: fuera de horario ({$hour}h), reagendando en {$delaySeconds}s hasta las 10:00.");

        $this->release($delaySeconds);

        return true;
    }

    public function failed(PropertyRequestCreated $event, \Throwable $exception): void
    {
        Log::error("Failed to notify listing owners for PropertyRequest #{$event->propertyRequest->id}: " . $exception->getMessage());
    }
}
