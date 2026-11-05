<?php

namespace App\Mail;

use App\Models\EmailSendRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Correo escrito a mano en el admin (Comunicaciones → Mensajes). No se encola: lo envía
 * SendAdminEmailBatchJob, que controla el ritmo de envío.
 *
 * Variables disponibles en asunto y cuerpo: {nombre}, {inmobiliaria}, {email}.
 */
class AdminMessageMail extends Mailable
{
    use Queueable;

    /**
     * @param  array{email: string, name: ?string, agency: ?string}  $recipient
     */
    public function __construct(
        public string $subjectTemplate,
        public string $bodyTemplate,
        public array $recipient,
        public ?string $unsubscribeUrl = null,
        public string $recipientLocale = 'es',
    ) {}

    public static function forRecipient(EmailSendRecipient $recipient): self
    {
        $recipient->loadMissing('emailSend', 'user:id,locale');

        return new self(
            subjectTemplate: $recipient->emailSend->subject,
            bodyTemplate: $recipient->emailSend->body,
            recipient: ['email' => $recipient->email, 'name' => $recipient->name, 'agency' => $recipient->agency],
            unsubscribeUrl: URL::signedRoute('email.unsubscribe', ['recipient' => $recipient->id]),
            recipientLocale: $recipient->user?->locale === 'en' ? 'en' : 'es',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Str::squish($this->replaceVariables($this->subjectTemplate, escape: false)),
        );
    }

    public function content(): Content
    {
        $bodyHtml = (string) str($this->replaceVariables($this->bodyTemplate, escape: true))->sanitizeHtml();

        return new Content(
            view: 'emails.admin-message',
            text: 'emails.admin-message-text',
            with: [
                'bodyHtml' => $bodyHtml,
                'bodyText' => $this->htmlToText($bodyHtml),
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'footerLocale' => $this->recipientLocale,
            ],
        );
    }

    public function headers(): Headers
    {
        if ($this->unsubscribeUrl === null) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => '<' . $this->unsubscribeUrl . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * Reemplaza {nombre}, {inmobiliaria} y {email}. Sin nombre se usa la inmobiliaria (y viceversa).
     */
    public function replaceVariables(string $text, bool $escape): string
    {
        $name = $this->recipient['name'] ?: ($this->recipient['agency'] ?: '');
        $agency = $this->recipient['agency'] ?: ($this->recipient['name'] ?: '');

        $values = [
            '{nombre}' => $name,
            '{inmobiliaria}' => $agency,
            '{email}' => $this->recipient['email'],
        ];

        if ($escape) {
            $values = array_map(fn (string $value): string => e($value), $values);
        }

        return strtr($text, $values);
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|div|h[1-6]|li|blockquote)>/i', '/<li[^>]*>/i'], ["\n", "\n\n", '- '], $html);
        $text = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', (string) $text);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
