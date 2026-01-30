<?php

namespace App\Notifications;

use App\Mail\PropertyMatchFoundMail;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PropertyMatchFoundNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PropertyRequest $request,
        public PropertyListing $property,
        public ?int $matchScore = null,
        public ?array $matchReasons = null
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable)
    {
        return new PropertyMatchFoundMail(
            $notifiable,
            $this->request,
            $this->property,
            $this->matchScore,
            $this->matchReasons
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable->locale ?? 'es';
        app()->setLocale($locale);

        return [
            'request_id' => $this->request->id,
            'request_title' => $this->request->title,
            'property_id' => $this->property->id,
            'property_title' => $this->property->title,
            'match_score' => $this->matchScore,
            'message' => __('emails.property_match.intro'),
        ];
    }
}
