<?php

namespace App\Observers;

use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use Illuminate\Support\Facades\Cache;

class PropertyRequestObserver
{
    /**
     * Handle the PropertyRequest "created" event.
     */
    public function created(PropertyRequest $propertyRequest): void
    {
        $this->clearAffectedListingCaches($propertyRequest);
    }

    /**
     * Handle the PropertyRequest "updated" event.
     */
    public function updated(PropertyRequest $propertyRequest): void
    {
        $this->clearAffectedListingCaches($propertyRequest);
    }

    /**
     * Handle the PropertyRequest "deleted" event.
     */
    public function deleted(PropertyRequest $propertyRequest): void
    {
        $this->clearAffectedListingCaches($propertyRequest);
    }

    /**
     * Clear match count caches for all listings that could match this request.
     * Uses lightweight query to get only IDs and user_ids of potentially affected listings.
     */
    private function clearAffectedListingCaches(PropertyRequest $propertyRequest): void
    {
        $affectedListings = PropertyListing::query()
            ->where('country', $propertyRequest->country)
            ->where('property_type', $propertyRequest->property_type)
            ->where('transaction_type', $propertyRequest->transaction_type)
            ->pluck('user_id', 'id');

        $affectedListings->each(function (int $userId, int $listingId): void {
            Cache::forget("matches_listing_count_{$listingId}");
            Cache::forget("matches_listing_{$listingId}");
            Cache::forget("matches_index_{$userId}");
        });
    }
}
