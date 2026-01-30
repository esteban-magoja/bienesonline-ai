<?php

namespace App\Mail;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropertyMatchFoundMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public PropertyRequest $request,
        public PropertyListing $property,
        public ?int $matchScore = null,
        public ?array $matchReasons = null
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $locale = $this->user->locale ?? 'es';
        app()->setLocale($locale);

        return new Envelope(
            subject: __('emails.property_match.subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $locale = $this->user->locale ?? 'es';
        app()->setLocale($locale);

        return new Content(
            markdown: "emails.{$locale}.property-match-found",
            with: [
                'user' => $this->user,
                'request' => $this->request,
                'property' => $this->property,
                'matchScore' => $this->matchScore,
                'matchReasons' => $this->matchReasons,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
