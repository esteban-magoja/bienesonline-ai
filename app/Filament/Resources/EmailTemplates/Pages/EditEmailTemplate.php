<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\Actions\SendEmailActions;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendEmailActions::send(),
            SendEmailActions::preview(),
            SendEmailActions::test(),
            DeleteAction::make()
                ->modalDescription('Los envíos ya hechos quedan registrados en Envíos.'),
        ];
    }
}
