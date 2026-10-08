<?php

namespace App\Filament\Resources\EmailMessageLogs\Pages;

use App\Filament\Resources\EmailMessageLogs\EmailMessageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailMessageLogs extends ListRecords
{
    protected static string $resource = EmailMessageLogResource::class;
}
