<?php

namespace App\Filament\Resources\EmailContacts\RelationManagers;

use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Models\EmailSendRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de correos enviados desde el admin a un contacto o a un usuario
 * (ambos modelos tienen la relación emailRecipients).
 */
class EmailRecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'emailRecipients';

    protected static ?string $title = 'Correos enviados';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('emailSend.template:id,name'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('emailSend.template.name')
                    ->label('Mensaje')
                    ->placeholder('(mensaje borrado)'),
                TextColumn::make('emailSend.subject')
                    ->label('Asunto')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EmailSendRecipient::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => EmailSendRecipient::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (EmailSendRecipient $record): string => EmailSendResource::getUrl('view', ['record' => $record->email_send_id]))
            ->emptyStateHeading('Sin correos enviados');
    }
}
