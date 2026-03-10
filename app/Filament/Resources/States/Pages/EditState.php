<?php

namespace App\Filament\Resources\States\Pages;

use App\Filament\Resources\States\StatesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditState extends EditRecord
{
    protected static string $resource = StatesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
