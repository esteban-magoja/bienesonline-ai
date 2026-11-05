<?php

namespace App\Filament\Resources\EmailContacts\Pages;

use App\Filament\Resources\EmailContacts\EmailContactResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailContact extends EditRecord
{
    protected static string $resource = EmailContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
