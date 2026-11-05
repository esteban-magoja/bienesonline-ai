<?php

namespace App\Filament\Resources\EmailSends\Pages;

use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Models\EmailSendRecipient;
use App\Services\AdminEmail\AdminEmailService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailSend extends ViewRecord
{
    protected static string $resource = EmailSendResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryFailed')
                ->label('Reintentar fallidos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Los destinatarios fallidos vuelven a la cola y se envían de nuevo.')
                ->visible(fn (): bool => $this->record->recipients()->whereIn('status', [EmailSendRecipient::STATUS_FAILED, EmailSendRecipient::STATUS_SENDING])->exists())
                ->action(function (AdminEmailService $service): void {
                    $count = $service->retryFailed($this->record);

                    Notification::make()
                        ->title($count > 0 ? "{$count} destinatario(s) vuelven a la cola" : 'No hay fallidos para reintentar')
                        ->success()
                        ->send();
                }),
        ];
    }
}
