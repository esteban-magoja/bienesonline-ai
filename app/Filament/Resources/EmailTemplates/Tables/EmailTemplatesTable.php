<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Filament\Resources\EmailTemplates\Actions\SendEmailActions;
use App\Models\EmailSendRecipient;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Mensaje')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('sends_count')
                    ->label('Envíos')
                    ->counts('sends')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('recipients_count')
                    ->label('Enviados')
                    ->counts(['recipients' => fn (Builder $query) => $query->where('email_send_recipients.status', EmailSendRecipient::STATUS_SENT)])
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('sends_max_created_at')
                    ->label('Último envío')
                    ->max('sends', 'created_at')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Modificado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                SendEmailActions::send(),
                ActionGroup::make([
                    SendEmailActions::preview(),
                    SendEmailActions::test(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('Los envíos ya hechos quedan registrados en Envíos.'),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay mensajes')
            ->emptyStateDescription('Creá un mensaje para enviarlo a usuarios y contactos.')
            ->emptyStateIcon('heroicon-o-pencil-square');
    }
}
