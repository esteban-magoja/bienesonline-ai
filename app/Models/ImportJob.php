<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJob extends Model
{
    protected $fillable = [
        'user_id',
        'import_source_id',
        'status',
        'type',
        'total_listings',
        'imported_listings',
        'updated_listings',
        'skipped_listings',
        'failed_listings',
        'deactivated_listings',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function importSource()
    {
        return $this->belongsTo(ImportSource::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function progressPercent(): int
    {
        if ($this->total_listings === 0) {
            return 0;
        }

        $processed = $this->imported_listings + $this->updated_listings + $this->skipped_listings + $this->failed_listings;
        return (int) round(($processed / $this->total_listings) * 100);
    }
}
