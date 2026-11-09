<?php

namespace App\Filament\Resources\EmailSuppressions\Pages;

use App\Filament\Resources\EmailSuppressions\Actions\AddSuppressionsAction;
use App\Filament\Resources\EmailSuppressions\EmailSuppressionResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailSuppressions extends ListRecords
{
    protected static string $resource = EmailSuppressionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AddSuppressionsAction::make(),
        ];
    }
}
