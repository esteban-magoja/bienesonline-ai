<?php

namespace App\Events;

use App\Models\PropertyListing;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyListingCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PropertyListing $listing;

    /**
     * Create a new event instance.
     */
    public function __construct(PropertyListing $listing)
    {
        $this->listing = $listing;
    }
}
