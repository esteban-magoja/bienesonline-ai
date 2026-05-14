<?php

namespace App\Filament\Resources\WhatsAppMessageLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsAppMessageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('status')
                    ->label('Estado')
                    ->icon(fn (string $state): string => match ($state) {
                        'sent'     => 'heroicon-o-check-circle',
                        'failed'   => 'heroicon-o-x-circle',
                        'disabled' => 'heroicon-o-pause-circle',
                        'no_phone' => 'heroicon-o-phone-x-mark',
                        default    => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent'     => 'success',
                        'failed'   => 'danger',
                        'disabled' => 'gray',
                        'no_phone' => 'warning',
                        default    => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'sent'     => 'Enviado',
                        'failed'   => 'Fallido',
                        'disabled' => 'WhatsApp deshabilitado',
                        'no_phone' => 'Sin teléfono',
                        default    => $state,
                    })
                    ->sortable(),
                TextColumn::make('notifiable.name')
                    ->label('Destinatario')
                    ->searchable()
                    ->sortable()
                    ->limit(25),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon('heroicon-o-phone'),
                TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'match_ad' => 'info',
                        'welcome'  => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'match_ad' => 'Match Anuncio',
                        'welcome'  => 'Bienvenida',
                        default    => $state ?? '—',
                    }),
                TextColumn::make('template_name')
                    ->label('Template')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('propertyRequest.title')
                    ->label('Solicitud')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('propertyListing.title')
                    ->label('Anuncio')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('whatsapp_message_id')
                    ->label('ID Meta')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('ID copiado')
                    ->limit(20)
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
                        'sent'     => 'Enviado',
                        'failed'   => 'Fallido',
                        'disabled' => 'Deshabilitado',
                        'no_phone' => 'Sin teléfono',
                    ]),
                SelectFilter::make('event_type')
                    ->label('Evento')
                    ->options([
                        'match_ad' => 'Match Anuncio',
                        'welcome'  => 'Bienvenida',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin registros')
            ->emptyStateDescription('Aquí aparecerán los mensajes de WhatsApp enviados por matches.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}
