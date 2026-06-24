<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteListingImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly array $imagePaths) {}

    public function handle(): void
    {
        try {
            Storage::disk('public')->delete($this->imagePaths);
        } catch (\Exception $e) {
            Log::warning('DeleteListingImages: error deleting files', [
                'paths' => $this->imagePaths,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
