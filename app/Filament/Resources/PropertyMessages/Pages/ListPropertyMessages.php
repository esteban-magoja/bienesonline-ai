<?php

namespace App\Filament\Resources\PropertyMessages\Pages;

use App\Filament\Resources\PropertyMessages\PropertyMessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPropertyMessages extends ListRecords
{
    protected static string $resource = PropertyMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
