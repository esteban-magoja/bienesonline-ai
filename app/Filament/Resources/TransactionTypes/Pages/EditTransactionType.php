<?php

namespace App\Filament\Resources\TransactionTypes\Pages;

use App\Filament\Resources\TransactionTypes\TransactionTypeResource;
use App\Models\TransactionType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransactionType extends EditRecord
{
    protected static string $resource = TransactionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn() => TransactionType::clearCache($this->record->country_code)),
        ];
    }

    protected function afterSave(): void
    {
        TransactionType::clearCache($this->record->country_code);
    }
}
