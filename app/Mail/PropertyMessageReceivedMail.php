<?php

namespace App\Mail;

use App\Models\PropertyListing;
use App\Models\PropertyMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropertyMessageReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public PropertyListing $property,
        public PropertyMessage $message
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $locale = $this->property->user->locale ?? 'es';
        app()->setLocale($locale);

        return new Envelope(
            subject: __('emails.message_received.subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $locale = $this->property->user->locale ?? 'es';
        app()->setLocale($locale);

        return new Content(
            markdown: "emails.{$locale}.message-received",
            with: [
                'property' => $this->property,
                'message' => $this->message,
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
