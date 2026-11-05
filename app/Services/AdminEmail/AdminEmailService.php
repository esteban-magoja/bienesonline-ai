<?php

namespace App\Services\AdminEmail;

use App\Jobs\SendAdminEmailBatchJob;
use App\Mail\AdminMessageMail;
use App\Models\EmailContact;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Arma y lanza los envíos de mensajes del admin a usuarios registrados y contactos.
 *
 * Cada destinatario recibe su propio correo; el envío real lo hace SendAdminEmailBatchJob de a
 * config('admin_email.per_minute') por minuto.
 */
class AdminEmailService
{
    private const CHUNK = 1000;

    /** Minutos tras los que un destinatario en 'sending' se considera trabado (job caído) */
    private const STUCK_SENDING_MINUTES = 10;

    /**
     * Resume a quiénes se enviaría el mensaje, sin crear nada.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $contactIds
     * @return array{to_send: int, already_sent: int, suppressed: int, invalid: int}
     */
    public function summarize(?EmailTemplate $template, array $userIds, array $contactIds, bool $excludeAlreadySent = true): array
    {
        $plan = $this->plan($template, $userIds, $contactIds, $excludeAlreadySent);

        return [
            'to_send' => $plan['to_send']->count(),
            'already_sent' => $plan['already_sent'],
            'suppressed' => $plan['skipped']->where('reason', 'suppressed')->count(),
            'invalid' => $plan['skipped']->where('reason', 'invalid')->count(),
        ];
    }

    /**
     * Crea el envío con sus destinatarios y encola el primer lote. Devuelve null si no hay a quién enviar.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $contactIds
     */
    public function send(EmailTemplate $template, array $userIds, array $contactIds, bool $excludeAlreadySent = true, ?User $sender = null): ?EmailSend
    {
        $plan = $this->plan($template, $userIds, $contactIds, $excludeAlreadySent);

        if ($plan['to_send']->isEmpty()) {
            return null;
        }

        $emailSend = DB::transaction(function () use ($template, $plan, $sender): EmailSend {
            $emailSend = EmailSend::create([
                'email_template_id' => $template->id,
                'subject' => $template->subject,
                'body' => $template->body,
                'sent_by' => $sender?->id,
            ]);

            $now = now();
            $rows = $plan['to_send']->map(fn (array $recipient): array => $this->recipientRow($emailSend, $recipient, EmailSendRecipient::STATUS_PENDING, null, $now))
                ->merge($plan['skipped']->map(fn (array $recipient): array => $this->recipientRow(
                    $emailSend,
                    $recipient,
                    EmailSendRecipient::STATUS_SKIPPED,
                    $recipient['reason'] === 'suppressed' ? 'Dado de baja' : 'Email inválido',
                    $now,
                )));

            foreach ($rows->chunk(500) as $chunk) {
                EmailSendRecipient::insert($chunk->values()->all());
            }

            return $emailSend;
        });

        SendAdminEmailBatchJob::dispatch($emailSend->id);

        return $emailSend;
    }

    /**
     * Vuelve a poner en cola los destinatarios fallidos (y los trabados en 'sending') de un envío.
     */
    public function retryFailed(EmailSend $emailSend): int
    {
        $count = $emailSend->recipients()
            ->where(fn ($query) => $query
                ->where('status', EmailSendRecipient::STATUS_FAILED)
                ->orWhere(fn ($query) => $query
                    ->where('status', EmailSendRecipient::STATUS_SENDING)
                    ->where('updated_at', '<', now()->subMinutes(self::STUCK_SENDING_MINUTES))))
            ->update(['status' => EmailSendRecipient::STATUS_PENDING, 'error_message' => null]);

        if ($count > 0) {
            // refresh(): si el modelo en memoria quedó desactualizado, update() no detectaría el cambio
            $emailSend->refresh()->update(['status' => EmailSend::STATUS_SENDING, 'finished_at' => null]);
            SendAdminEmailBatchJob::dispatch($emailSend->id);
        }

        return $count;
    }

    /**
     * Envía el mensaje a un solo email (prueba), sin registrarlo en ningún envío.
     */
    public function sendTest(EmailTemplate $template, User $to): void
    {
        Mail::mailer(config('admin_email.mailer'))
            ->to($to->email, $to->name)
            ->send(new AdminMessageMail(
                subjectTemplate: '[Prueba] ' . $template->subject,
                bodyTemplate: $template->body,
                recipient: ['email' => $to->email, 'name' => $to->name, 'agency' => $to->agency],
            ));
    }

    /**
     * Marca el envío como completado si no le quedan destinatarios por enviar.
     */
    public function completeIfDone(EmailSend $emailSend): void
    {
        $remaining = $emailSend->recipients()
            ->whereIn('status', [EmailSendRecipient::STATUS_PENDING, EmailSendRecipient::STATUS_SENDING])
            ->exists();

        if (! $remaining && ! $emailSend->isCompleted()) {
            $emailSend->update(['status' => EmailSend::STATUS_COMPLETED, 'finished_at' => now()]);
        }
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $contactIds
     * @return array{to_send: Collection<int, array{email: string, name: ?string, agency: ?string, user_id: ?int, email_contact_id: ?int}>, skipped: Collection<int, array{email: string, name: ?string, agency: ?string, user_id: ?int, email_contact_id: ?int, reason: string}>, already_sent: int}
     */
    private function plan(?EmailTemplate $template, array $userIds, array $contactIds, bool $excludeAlreadySent): array
    {
        $candidates = $this->candidates($userIds, $contactIds);

        $invalid = $candidates->reject(fn (array $recipient): bool => filter_var($recipient['email'], FILTER_VALIDATE_EMAIL) !== false);
        $valid = $candidates->diffKeys($invalid);

        $suppressedEmails = $this->existingEmails($valid->keys(), fn (Collection $emails) => EmailSuppression::whereIn('email', $emails)->pluck('email'));
        $suppressed = $valid->only($suppressedEmails->all());
        $valid = $valid->except($suppressedEmails->all());

        $alreadySent = 0;
        if ($excludeAlreadySent && $template?->exists) {
            $receivedEmails = $this->existingEmails($valid->keys(), fn (Collection $emails) => EmailSendRecipient::query()
                ->whereIn('email', $emails)
                ->whereIn('status', EmailSendRecipient::DELIVERED_OR_QUEUED)
                ->whereHas('emailSend', fn ($query) => $query->where('email_template_id', $template->id))
                ->distinct()
                ->pluck('email'));
            $alreadySent = $receivedEmails->count();
            $valid = $valid->except($receivedEmails->all());
        }

        return [
            'to_send' => $valid->values(),
            'skipped' => $suppressed->map(fn (array $recipient): array => $recipient + ['reason' => 'suppressed'])
                ->merge($invalid->map(fn (array $recipient): array => $recipient + ['reason' => 'invalid']))
                ->values(),
            'already_sent' => $alreadySent,
        ];
    }

    /**
     * Destinatarios únicos por email (en minúsculas). Si un email es a la vez usuario y contacto,
     * queda un solo destinatario con ambos vínculos (los datos del usuario tienen prioridad).
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $contactIds
     * @return Collection<string, array{email: string, name: ?string, agency: ?string, user_id: ?int, email_contact_id: ?int}>
     */
    private function candidates(array $userIds, array $contactIds): Collection
    {
        $recipients = collect();

        foreach (array_chunk(array_values(array_unique($userIds)), self::CHUNK) as $ids) {
            User::whereIn('id', $ids)->get(['id', 'name', 'email', 'agency'])->each(function (User $user) use ($recipients): void {
                $email = Str::lower(trim((string) $user->email));
                $recipients->put($email, [
                    'email' => $email,
                    'name' => $user->name ?: null,
                    'agency' => $user->agency ?: null,
                    'user_id' => $user->id,
                    'email_contact_id' => $recipients->get($email)['email_contact_id'] ?? null,
                ]);
            });
        }

        foreach (array_chunk(array_values(array_unique($contactIds)), self::CHUNK) as $ids) {
            EmailContact::whereIn('id', $ids)->get(['id', 'name', 'agency', 'email', 'user_id'])->each(function (EmailContact $contact) use ($recipients): void {
                $existing = $recipients->get($contact->email);
                $recipients->put($contact->email, [
                    'email' => $contact->email,
                    'name' => $existing['name'] ?? ($contact->name ?: null),
                    'agency' => $existing['agency'] ?? ($contact->agency ?: null),
                    'user_id' => $existing['user_id'] ?? $contact->user_id,
                    'email_contact_id' => $contact->id,
                ]);
            });
        }

        return $recipients->filter(fn (array $recipient): bool => $recipient['email'] !== '');
    }

    /**
     * Ejecuta la consulta por lotes de emails y devuelve los que existen.
     *
     * @param  Collection<int, string>  $emails
     * @param  callable(Collection<int, string>): Collection<int, string>  $query
     * @return Collection<int, string>
     */
    private function existingEmails(Collection $emails, callable $query): Collection
    {
        return $emails->chunk(self::CHUNK)
            ->flatMap(fn (Collection $chunk): Collection => $query($chunk->values()))
            ->unique()
            ->values();
    }

    /**
     * @param  array{email: string, name: ?string, agency: ?string, user_id: ?int, email_contact_id: ?int}  $recipient
     * @return array<string, mixed>
     */
    private function recipientRow(EmailSend $emailSend, array $recipient, string $status, ?string $error, \DateTimeInterface $now): array
    {
        return [
            'email_send_id' => $emailSend->id,
            'email' => $recipient['email'],
            'name' => $recipient['name'] !== null ? Str::limit($recipient['name'], 250, '') : null,
            'agency' => $recipient['agency'] !== null ? Str::limit($recipient['agency'], 250, '') : null,
            'user_id' => $recipient['user_id'],
            'email_contact_id' => $recipient['email_contact_id'],
            'status' => $status,
            'error_message' => $error,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
