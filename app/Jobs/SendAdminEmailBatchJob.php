<?php

namespace App\Jobs;

use App\Mail\AdminMessageMail;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use App\Models\EmailSuppression;
use App\Services\AdminEmail\AdminEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envía un lote de config('admin_email.per_minute') destinatarios de un envío del admin y, si
 * quedan pendientes, se vuelve a encolar con un minuto de demora (respeta el límite del hosting).
 */
class SendAdminEmailBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $emailSendId) {}

    public function handle(AdminEmailService $service): void
    {
        $emailSend = EmailSend::find($this->emailSendId);

        if (! $emailSend) {
            return;
        }

        $recipients = $this->claimBatch((int) config('admin_email.per_minute'));

        foreach ($recipients as $recipient) {
            $this->sendTo($recipient);
        }

        $hasPending = $emailSend->recipients()->where('status', EmailSendRecipient::STATUS_PENDING)->exists();

        if ($hasPending) {
            static::dispatch($this->emailSendId)->delay(now()->addMinute());

            return;
        }

        $service->completeIfDone($emailSend);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendAdminEmailBatchJob: falló el lote', [
            'email_send_id' => $this->emailSendId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Toma el siguiente lote de pendientes y lo marca como 'sending' (evita envíos dobles si
     * hubiera dos workers a la vez).
     *
     * @return Collection<int, EmailSendRecipient>
     */
    private function claimBatch(int $size): Collection
    {
        return DB::transaction(function () use ($size): Collection {
            $recipients = EmailSendRecipient::query()
                ->where('email_send_id', $this->emailSendId)
                ->where('status', EmailSendRecipient::STATUS_PENDING)
                ->orderBy('id')
                ->limit($size)
                ->lock('for update skip locked')
                ->get();

            if ($recipients->isNotEmpty()) {
                EmailSendRecipient::whereKey($recipients->modelKeys())
                    ->update(['status' => EmailSendRecipient::STATUS_SENDING, 'updated_at' => now()]);
            }

            return $recipients;
        });
    }

    private function sendTo(EmailSendRecipient $recipient): void
    {
        // Pudo darse de baja después de armado el envío
        if (EmailSuppression::isSuppressed($recipient->email)) {
            $recipient->update(['status' => EmailSendRecipient::STATUS_SKIPPED, 'error_message' => 'Dado de baja']);

            return;
        }

        try {
            Mail::mailer(config('admin_email.mailer'))
                ->to($recipient->email, $recipient->name)
                ->send(AdminMessageMail::forRecipient($recipient));

            $recipient->update([
                'status' => EmailSendRecipient::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('SendAdminEmailBatchJob: error al enviar', [
                'email_send_recipient_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);

            $recipient->update([
                'status' => EmailSendRecipient::STATUS_FAILED,
                'error_message' => Str::limit($exception->getMessage(), 1000),
            ]);
        }
    }
}
