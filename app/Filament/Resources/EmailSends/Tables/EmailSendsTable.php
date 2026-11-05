<?php

namespace App\Filament\Resources\EmailSends\Tables;

use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailSendsTable
{
    public static function configure(Table $table, bool $showTemplate = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::withStatusCounts($query)->with(['template:id,name', 'sender:id,name']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Mensaje')
                    ->placeholder('(mensaje borrado)')
                    ->wrap()
                    ->visible($showTemplate),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: $showTemplate),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === EmailSend::STATUS_COMPLETED ? 'Terminado' : 'Enviando')
                    ->color(fn (string $state): string => $state === EmailSend::STATUS_COMPLETED ? 'success' : 'info'),
                TextColumn::make('recipients_count')
                    ->label('Destinatarios')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sent_count')
                    ->label('Enviados')
                    ->badge()
                    ->color('success'),
                TextColumn::make('pending_count')
                    ->label('Pendientes')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('failed_count')
                    ->label('Fallidos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('skipped_count')
                    ->label('Omitidos')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),
                TextColumn::make('sender.name')
                    ->label('Enviado por')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        EmailSend::STATUS_SENDING => 'Enviando',
                        EmailSend::STATUS_COMPLETED => 'Terminado',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (EmailSend $record): string => EmailSendResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (EmailSend $record): string => EmailSendResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Todavía no hay envíos')
            ->emptyStateIcon('heroicon-o-paper-airplane');
    }

    /**
     * Agrega los conteos de destinatarios por estado (recipients_count, sent_count, pending_count...).
     *
     * @param  Builder<EmailSend>  $query
     * @return Builder<EmailSend>
     */
    public static function withStatusCounts(Builder $query): Builder
    {
        return $query->withCount([
            'recipients',
            'recipients as sent_count' => fn (Builder $query) => $query->where('status', EmailSendRecipient::STATUS_SENT),
            'recipients as pending_count' => fn (Builder $query) => $query->whereIn('status', [EmailSendRecipient::STATUS_PENDING, EmailSendRecipient::STATUS_SENDING]),
            'recipients as failed_count' => fn (Builder $query) => $query->where('status', EmailSendRecipient::STATUS_FAILED),
            'recipients as skipped_count' => fn (Builder $query) => $query->where('status', EmailSendRecipient::STATUS_SKIPPED),
        ]);
    }
}
