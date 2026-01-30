<?php

namespace App\Filament\Resources\PropertyMessages\Pages;

use App\Filament\Resources\PropertyMessages\PropertyMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPropertyMessage extends EditRecord
{
    protected static string $resource = PropertyMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
