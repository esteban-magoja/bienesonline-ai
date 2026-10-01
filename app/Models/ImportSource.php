<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conexión de un usuario con una fuente externa de anuncios (feed de Wasi, etc.).
 */
class ImportSource extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'driver',
        'name',
        'config',
        'status',
        'sync_enabled',
        'sync_interval_hours',
        'last_synced_at',
        'last_sync_status',
        'last_error',
        'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'config'         => 'array',
            'sync_enabled'   => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importJobs(): HasMany
    {
        return $this->hasMany(ImportJob::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(PropertyListing::class);
    }

    public function latestJob(): ?ImportJob
    {
        return $this->importJobs()->latest('id')->first();
    }

    public function hasRunningJob(): bool
    {
        return $this->importJobs()->whereIn('status', ['pending', 'processing'])->exists();
    }

    /**
     * Fuentes cuyo próximo sync ya venció.
     */
    public function scopeDueForSync($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('sync_enabled', true)
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    // now() de PHP (misma zona horaria con la que se guarda last_synced_at), no NOW() de la BD
                    ->orWhereRaw("last_synced_at <= ?::timestamp - (sync_interval_hours * INTERVAL '1 hour')", [now()->toDateTimeString()]);
            });
    }
}
