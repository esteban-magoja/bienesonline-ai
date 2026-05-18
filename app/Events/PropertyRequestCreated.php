<?php

namespace App\Events;

use App\Models\PropertyRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyRequestCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PropertyRequest $propertyRequest,
        public bool $isLegacyImport = false,
    ) {}
}
