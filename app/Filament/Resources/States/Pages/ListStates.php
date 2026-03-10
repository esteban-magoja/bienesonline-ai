<?php

namespace App\Filament\Resources\States\Pages;

use App\Filament\Resources\States\StatesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStates extends ListRecords
{
    protected static string $resource = StatesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
