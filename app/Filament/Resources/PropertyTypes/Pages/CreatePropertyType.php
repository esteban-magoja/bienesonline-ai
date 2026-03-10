<?php

namespace App\Filament\Resources\PropertyTypes\Pages;

use App\Filament\Resources\PropertyTypes\PropertyTypeResource;
use App\Models\PropertyType;
use Filament\Resources\Pages\CreateRecord;

class CreatePropertyType extends CreateRecord
{
    protected static string $resource = PropertyTypeResource::class;

    protected function afterCreate(): void
    {
        PropertyType::clearCache($this->record->country_code);
    }
}
