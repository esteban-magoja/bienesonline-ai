<?php

namespace App\Filament\Resources\EmailContacts\Pages;

use App\Filament\Resources\EmailContacts\Actions\ImportContactsCsvAction;
use App\Filament\Resources\EmailContacts\EmailContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailContacts extends ListRecords
{
    protected static string $resource = EmailContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportContactsCsvAction::make(),
            CreateAction::make()->label('Nuevo contacto'),
        ];
    }
}
