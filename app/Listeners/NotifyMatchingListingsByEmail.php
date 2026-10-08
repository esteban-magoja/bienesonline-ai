<?php

namespace App\Listeners;

use App\Events\PropertyRequestCreated;
use App\Models\EmailMessageLog;
use App\Models\User;
use App\Notifications\PropertyMatchAdEmailNotification;
use App\Services\PropertyMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Avisa por email a los dueños de anuncios que coinciden con una nueva solicitud.
 * Complementa a NotifyMatchingListings (WhatsApp): sin restricción horaria,
 * sin requisito de premium, solo a emails verificados y como máximo 1 email por usuario y día.
 */
class NotifyMatchingListingsByEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public const EVENT_TYPE = 'match_ad';

    public function __construct(private PropertyMatchingService $matchingService) {}

    public function handle(PropertyRequestCreated $event): void
    {
        $propertyRequest = $event->propertyRequest;
        $minScore        = config('matching.min_score_to_notify', 70);

        try {
            $matches = $this->matchingService->findMatchesForRequest($propertyRequest, 50);

            $ownerIds = $matches
                ->filter(fn ($listing) => ($listing->match_score ?? 0) >= $minScore)
                ->pluck('user_id')
                ->unique();

            foreach ($ownerIds as $ownerId) {
                $user = User::find($ownerId);

                if (!$user || empty($user->email) || $user->email_verified_at === null) {
                    Log::debug("NotifyMatchingListingsByEmail: user #{$ownerId} sin email verificado, saltando.");
                    continue;
                }

                // Gate atómico (jobs concurrentes) + respaldo en DB (cache vaciado).
                if (!$this->acquireDailyLock($user->id) || $this->wasNotifiedToday($user)) {
                    Log::debug("NotifyMatchingListingsByEmail: user #{$ownerId} ya recibió el email de hoy, saltando.");
                    continue;
                }

                $this->sendAndLog($user, $propertyRequest->id);
            }
        } catch (\Exception $e) {
            Log::error("NotifyMatchingListingsByEmail: error procesando PropertyRequest #{$propertyRequest->id}: " . $e->getMessage());
        }
    }

    private function sendAndLog(User $user, int $propertyRequestId): void
    {
        $locale = $user->preferredLocale();
        $status = 'sent';
        $error  = null;

        try {
            $user->notifyNow(new PropertyMatchAdEmailNotification($propertyRequestId));
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = $e->getMessage();

            // Liberar el lock para que una próxima solicitud del día pueda reintentar.
            Cache::forget($this->lockKey($user->id));

            Log::warning("NotifyMatchingListingsByEmail: falló el envío a user #{$user->id}: {$error}");
        }

        try {
            EmailMessageLog::create([
                'notifiable_type'     => User::class,
                'notifiable_id'       => $user->id,
                'email'               => $user->email,
                'notification_class'  => PropertyMatchAdEmailNotification::class,
                'event_type'          => self::EVENT_TYPE,
                'subject'             => __('emails.property_match_ad.subject', [], $locale),
                'language_code'       => $locale,
                'property_request_id' => $propertyRequestId,
                'status'              => $status,
                'error_message'       => $error,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotifyMatchingListingsByEmail: no se pudo registrar el log', ['error' => $e->getMessage()]);
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
        return "email_notify_throttle_{$userId}_" . now()->format('Y-m-d');
    }

    private function wasNotifiedToday(User $user): bool
    {
        return EmailMessageLog::where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->where('event_type', self::EVENT_TYPE)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();
    }

    public function failed(PropertyRequestCreated $event, \Throwable $exception): void
    {
        Log::error("NotifyMatchingListingsByEmail: falló para PropertyRequest #{$event->propertyRequest->id}: " . $exception->getMessage());
    }
}
