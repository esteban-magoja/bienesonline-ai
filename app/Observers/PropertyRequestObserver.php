<?php

namespace App\Observers;

use App\Events\PropertyRequestCreated;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Cache;

class PropertyRequestObserver
{
    private const EMBEDDING_FIELDS = [
        'title',
        'description',
        'property_type',
        'transaction_type',
        'city',
        'state',
        'country',
    ];

    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * Handle the PropertyRequest "creating" event.
     */
    public function creating(PropertyRequest $propertyRequest): void
    {
        if ($propertyRequest->embedding !== null) {
            return;
        }

        $embedding = $this->embeddingService->generate([
            $propertyRequest->title,
            $propertyRequest->description,
            $propertyRequest->property_type,
            $propertyRequest->transaction_type,
            $propertyRequest->city,
            $propertyRequest->state,
            $propertyRequest->country,
        ]);

        if ($embedding !== null) {
            $propertyRequest->embedding = $embedding;
        }
    }

    /**
     * Handle the PropertyRequest "created" event.
     */
    public function created(PropertyRequest $propertyRequest): void
    {
        Cache::forget("dashboard_requests_{$propertyRequest->user_id}");
        Cache::forget("dashboard_matches_outbound_{$propertyRequest->user_id}");
        $this->clearAffectedListingCaches($propertyRequest);

        event(new PropertyRequestCreated($propertyRequest));
    }

    /**
     * Handle the PropertyRequest "updating" event.
     */
    public function updating(PropertyRequest $propertyRequest): void
    {
        if (! $propertyRequest->isDirty(self::EMBEDDING_FIELDS)) {
            return;
        }

        $embedding = $this->embeddingService->generate([
            $propertyRequest->title,
            $propertyRequest->description,
            $propertyRequest->property_type,
            $propertyRequest->transaction_type,
            $propertyRequest->city,
            $propertyRequest->state,
            $propertyRequest->country,
        ]);

        if ($embedding !== null) {
            $propertyRequest->embedding = $embedding;
        }
    }

    /**
     * Handle the PropertyRequest "updated" event.
     */
    public function updated(PropertyRequest $propertyRequest): void
    {
        Cache::forget("dashboard_requests_{$propertyRequest->user_id}");
        Cache::forget("dashboard_matches_outbound_{$propertyRequest->user_id}");
        Cache::forget("request_matches_{$propertyRequest->id}");
        Cache::forget("request_match_count_{$propertyRequest->id}");
        $this->clearAffectedListingCaches($propertyRequest);
    }

    /**
     * Handle the PropertyRequest "deleted" event.
     */
    public function deleted(PropertyRequest $propertyRequest): void
    {
        Cache::forget("dashboard_requests_{$propertyRequest->user_id}");
        Cache::forget("dashboard_matches_outbound_{$propertyRequest->user_id}");
        Cache::forget("request_matches_{$propertyRequest->id}");
        Cache::forget("request_match_count_{$propertyRequest->id}");
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

        $clearedUserIds = [];

        $affectedListings->each(function (int $userId, int $listingId) use (&$clearedUserIds): void {
            Cache::forget("matches_listing_count_{$listingId}");
            Cache::forget("matches_listing_{$listingId}");
            Cache::forget("matches_index_{$userId}");

            if (! in_array($userId, $clearedUserIds, true)) {
                Cache::forget("matches_summary_{$userId}");
                $clearedUserIds[] = $userId;
            }
        });
    }
}
