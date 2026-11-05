<?php

namespace App\Filament\Resources\EmailContacts\Pages;

use App\Filament\Resources\EmailContacts\EmailContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailContact extends CreateRecord
{
    protected static string $resource = EmailContactResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
