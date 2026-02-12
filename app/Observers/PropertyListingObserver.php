<?php

namespace App\Observers;

use App\Events\PropertyListingCreated;
use App\Models\PropertyListing;
use OpenAI;
use Pgvector\Laravel\Vector;
use Illuminate\Support\Facades\Log;

class PropertyListingObserver
{
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
        // Solo disparar si el matching automático está habilitado
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
     * Generate embedding for the property listing.
     */
    private function generateEmbedding(PropertyListing $propertyListing): void
    {
        try {
            $client = OpenAI::client(config('openai.api_key'));
            $model = config('openai.embeddings_model');

            $text = $propertyListing->title . ' ' .
                    $propertyListing->description . ' ' .
                    $propertyListing->address . ' ' .
                    $propertyListing->city . ' ' .
                    $propertyListing->state;

            $response = $client->embeddings()->create([
                'model' => $model,
                'input' => $text,
            ]);

            $propertyListing->embedding = new Vector($response->embeddings[0]->embedding);
        } catch (\Exception $e) {
            Log::error('Error generating embedding for PropertyListing ID ' . $propertyListing->id . ': ' . $e->getMessage());
            // Optionally, you might want to throw the exception or handle it differently
            // For now, we'll just log and continue without setting the embedding
        }
    }

    /**
     * Handle the PropertyListing "deleted" event.
     */
    public function deleted(PropertyListing $propertyListing): void
    {
        //
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
