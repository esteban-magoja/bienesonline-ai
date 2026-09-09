<?php

namespace App\Services;

use App\Models\PropertyListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RelatedPropertyService
{
    /**
     * Find relevant active listings for a property.
     *
     * Candidates are selected in stages so the same country, regional
     * property type, and transaction type are always preferred before
     * broader same-country fallbacks are considered.
     */
    public function findRelatedProperties(PropertyListing $property, int $limit = 4): Collection
    {
        $limit = max(1, $limit);
        $relatedProperties = collect();

        $stages = [
            ['property_type' => true, 'transaction_type' => true],
            ['property_type' => false, 'transaction_type' => true],
            ['property_type' => true, 'transaction_type' => false],
            ['property_type' => false, 'transaction_type' => false],
        ];

        foreach ($stages as $stage) {
            $remaining = $limit - $relatedProperties->count();

            if ($remaining <= 0) {
                break;
            }

            $query = $this->baseQuery($property);

            if ($relatedProperties->isNotEmpty()) {
                $query->whereNotIn('id', $relatedProperties->pluck('id')->all());
            }

            if ($stage['property_type']) {
                $query->whereRaw('LOWER(property_type) = LOWER(?)', [$property->property_type]);
            }

            if ($stage['transaction_type']) {
                $query->whereRaw('LOWER(transaction_type) = LOWER(?)', [$property->transaction_type]);
            }

            $relatedProperties = $relatedProperties->merge(
                $this->applyRanking($query, $property)
                    ->with(['primaryImage', 'firstImage'])
                    ->limit($remaining)
                    ->get()
            );
        }

        return $relatedProperties;
    }

    /**
     * @return Builder<PropertyListing>
     */
    private function baseQuery(PropertyListing $property): Builder
    {
        return PropertyListing::query()
            ->active()
            ->where('id', '!=', $property->getKey())
            ->where('country', $property->country);
    }

    /**
     * Apply deterministic relevance ordering to a candidate query.
     *
     * @param  Builder<PropertyListing>  $query
     * @return Builder<PropertyListing>
     */
    private function applyRanking(Builder $query, PropertyListing $property): Builder
    {
        $query
            ->orderByRaw('CASE WHEN LOWER(city) = LOWER(?) THEN 1 ELSE 0 END DESC', [$property->city])
            ->orderByRaw('CASE WHEN LOWER(state) = LOWER(?) THEN 1 ELSE 0 END DESC', [$property->state]);

        if ($property->price !== null) {
            $query->orderByRaw(
                "CASE
                    WHEN COALESCE(currency, '') = COALESCE(?, '') AND price IS NOT NULL
                    THEN ABS(price - ?)
                    ELSE 999999999999
                END ASC",
                [$property->currency, $property->price]
            );
        }

        if ($property->bedrooms !== null) {
            $query->orderByRaw(
                'CASE WHEN bedrooms IS NULL THEN 999999 ELSE ABS(bedrooms - ?) END ASC',
                [$property->bedrooms]
            );
        }

        if ($property->area !== null) {
            $query->orderByRaw(
                'CASE WHEN area IS NULL THEN 999999999 ELSE ABS(area - ?) END ASC',
                [$property->area]
            );
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }
}
