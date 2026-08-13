<?php

namespace App\Observers;

use App\Events\PropertyListingCreated;
use App\Models\PropertyListing;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;

class PropertyListingObserver
{
    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * Handle the PropertyListing "creating" event.
     */
    public function creating(PropertyListing $propertyListing): void
    {
        $this->generateEmbedding($propertyListing);
    }

    /**
     * Handle the PropertyListing "created" event.
     * Dispara el evento para buscar matches automáticamente.
     */
    public function created(PropertyListing $propertyListing): void
    {
        Cache::forget("dashboard_listings_{$propertyListing->user_id}");
        Cache::forget("dashboard_matches_inbound_{$propertyListing->user_id}");
        Cache::forget("matches_summary_{$propertyListing->user_id}");

        if (config('matching.enabled', true)) {
            event(new PropertyListingCreated($propertyListing));
        }
    }

    /**
     * Handle the PropertyListing "updating" event.
     */
    public function updating(PropertyListing $propertyListing): void
    {
        if ($propertyListing->isDirty(['title', 'description', 'address', 'city', 'state'])) {
            $this->generateEmbedding($propertyListing);
        }
    }

    /**
     * Handle the PropertyListing "updated" event.
     * Dispara el evento cuando un anuncio se reactiva (is_active: false → true).
     */
    public function updated(PropertyListing $propertyListing): void
    {
        Cache::forget("matches_listing_count_{$propertyListing->id}");
        Cache::forget("matches_listing_{$propertyListing->id}");
        Cache::forget("matches_summary_{$propertyListing->user_id}");

        if ($propertyListing->wasChanged('is_active')) {
            Cache::forget("dashboard_listings_{$propertyListing->user_id}");
            Cache::forget("dashboard_matches_inbound_{$propertyListing->user_id}");

            if ($propertyListing->is_active && config('matching.enabled', true)) {
                event(new PropertyListingCreated($propertyListing));
            }
        }
    }

    /**
     * Generate embedding for the property listing.
     */
    private function generateEmbedding(PropertyListing $propertyListing): void
    {
        $embedding = $this->embeddingService->generate([
            $propertyListing->title,
            $propertyListing->description,
            $propertyListing->address,
            $propertyListing->city,
            $propertyListing->state,
        ]);

        if ($embedding !== null) {
            $propertyListing->embedding = $embedding;
        }
    }

    /**
     * Handle the PropertyListing "deleted" event.
     */
    public function deleted(PropertyListing $propertyListing): void
    {
        Cache::forget("matches_listing_count_{$propertyListing->id}");
        Cache::forget("matches_listing_{$propertyListing->id}");
        Cache::forget("dashboard_listings_{$propertyListing->user_id}");
        Cache::forget("dashboard_matches_inbound_{$propertyListing->user_id}");
        Cache::forget("matches_summary_{$propertyListing->user_id}");
    }

    /**
     * Handle the PropertyListing "restored" event.
     */
    public function restored(PropertyListing $propertyListing): void
    {
        //
    }

    /**
     * Handle the PropertyListing "force deleted" event.
     */
    public function forceDeleted(PropertyListing $propertyListing): void
    {
        //
    }
}
