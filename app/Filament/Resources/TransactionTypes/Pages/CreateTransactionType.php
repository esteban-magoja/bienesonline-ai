<?php

namespace App\Filament\Resources\TransactionTypes\Pages;

use App\Filament\Resources\TransactionTypes\TransactionTypeResource;
use App\Models\TransactionType;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionType extends CreateRecord
{
    protected static string $resource = TransactionTypeResource::class;

    protected function afterCreate(): void
    {
        TransactionType::clearCache($this->record->country_code);
    }
}
