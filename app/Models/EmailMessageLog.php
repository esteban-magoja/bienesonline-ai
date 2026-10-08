<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailMessageLog extends Model
{
    protected $table = 'email_message_logs';

    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'email',
        'notification_class',
        'event_type',
        'subject',
        'language_code',
        'property_listing_id',
        'property_request_id',
        'status',
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
