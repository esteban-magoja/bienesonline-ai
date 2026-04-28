<?php

namespace App\Listeners;

use App\Events\PropertyListingCreated;
use App\Services\IndexNowService;
use App\Services\SeoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SubmitToIndexNow implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private IndexNowService $indexNowService,
        private SeoService $seoService,
    ) { }

    /**
     * Handle the event.
     */
    public function handle(PropertyListingCreated $event): void
    {
        $listing = $event->listing;

        $locales = config('locales.available', ['es', 'en']);

        $urls = [];
        foreach ($locales as $locale) {
            $urls[] = $this->seoService->generatePropertyUrl($listing, $locale);
        }

        $this->indexNowService->submitUrls($urls);
    }

    /**
     * Handle a job failure.
     */
    public function failed(PropertyListingCreated $event, \Throwable $exception): void
    {
        Log::warning('SubmitToIndexNow: falló el envío para PropertyListing #' . $event->listing->id . ': ' . $exception->getMessage());
    }
}
