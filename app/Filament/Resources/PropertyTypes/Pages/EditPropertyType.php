<?php

namespace App\Filament\Resources\PropertyTypes\Pages;

use App\Filament\Resources\PropertyTypes\PropertyTypeResource;
use App\Models\PropertyType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPropertyType extends EditRecord
{
    protected static string $resource = PropertyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn() => PropertyType::clearCache($this->record->country_code)),
        ];
    }

    protected function afterSave(): void
    {
        PropertyType::clearCache($this->record->country_code);
    }
}
