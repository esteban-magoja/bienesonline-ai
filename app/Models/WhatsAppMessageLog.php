<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessageLog extends Model
{
    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'phone',
        'notification_class',
        'event_type',
        'template_name',
        'language_code',
        'property_listing_id',
        'property_request_id',
        'status',
        'whatsapp_message_id',
        'error_message',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function propertyListing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class);
    }

    public function propertyRequest(): BelongsTo
    {
        return $this->belongsTo(PropertyRequest::class);
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
