<?php

namespace App\Filament\Resources\SearchQueries\Pages;

use App\Filament\Resources\SearchQueries\SearchQueryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSearchQuery extends EditRecord
{
    protected static string $resource = SearchQueryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
