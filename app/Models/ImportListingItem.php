<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportListingItem extends Model
{
    protected $fillable = [
        'import_job_id',
        'data',
        'status',
        'error_message',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDone($query)
    {
        return $query->where('status', 'done');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
