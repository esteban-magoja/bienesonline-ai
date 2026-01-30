<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\PropertyMessage;

class PropertyMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $propertyMessage;

    /**
     * Create a new message instance.
     */
    public function __construct(PropertyMessage $propertyMessage)
    {
        $this->propertyMessage = $propertyMessage;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $locale = $this->propertyMessage->propertyListing->user->locale ?? 'es';
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
        $locale = $this->propertyMessage->propertyListing->user->locale ?? 'es';
        app()->setLocale($locale);

        return new Content(
            markdown: "emails.{$locale}.message-received",
            with: [
                'property' => $this->propertyMessage->propertyListing,
                'message' => $this->propertyMessage,
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
