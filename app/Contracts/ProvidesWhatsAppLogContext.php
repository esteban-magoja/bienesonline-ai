<?php

namespace App\Contracts;

interface ProvidesWhatsAppLogContext
{
    /**
     * Return extra context for the WhatsApp message log entry.
     *
     * @return array{event_type: string|null, property_listing_id: int|null, property_request_id: int|null}
     */
    public function getWhatsAppLogContext(): array;
}
