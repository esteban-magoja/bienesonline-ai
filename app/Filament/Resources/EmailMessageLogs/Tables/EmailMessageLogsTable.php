<?php

namespace App\Filament\Resources\EmailMessageLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmailMessageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('status')
                    ->label('Estado')
                    ->icon(fn (string $state): string => match ($state) {
                        'sent'   => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default  => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent'   => 'success',
                        'failed' => 'danger',
                        default  => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'sent'   => 'Enviado',
                        'failed' => 'Fallido',
                        default  => $state,
                    })
                    ->sortable(),
                TextColumn::make('notifiable.name')
                    ->label('Destinatario')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copiado')
                    ->icon('heroicon-o-envelope'),
                TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'match_ad' => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'match_ad' => 'Match Anuncio',
                        default    => $state ?? '—',
                    }),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('language_code')
                    ->label('Idioma')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('propertyRequest.title')
                    ->label('Solicitud')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'sent'   => 'Enviado',
                        'failed' => 'Fallido',
                    ]),
                SelectFilter::make('event_type')
                    ->label('Evento')
                    ->options([
                        'match_ad' => 'Match Anuncio',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Aquí aparecerán los emails enviados por matches.')
            ->emptyStateIcon('heroicon-o-envelope');
    }
}
