<?php

namespace App\Filament\Resources\PropertyTypes\Pages;

use App\Filament\Resources\PropertyTypes\PropertyTypeResource;
use App\Models\PropertyType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPropertyTypes extends ListRecords
{
    protected static string $resource = PropertyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
