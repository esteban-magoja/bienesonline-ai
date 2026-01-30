<?php

namespace App\Filament\Resources\PropertyMessages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class PropertyMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_read')
                    ->label('Leído')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),
                TextColumn::make('propertyListing.title')
                    ->label('Propiedad')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) > 30) {
                            return $state;
                        }
                        return null;
                    }),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copiado')
                    ->icon('heroicon-o-envelope'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon('heroicon-o-phone'),
                TextColumn::make('message')
                    ->label('Mensaje')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) > 50) {
                            return $state;
                        }
                        return null;
                    })
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_read')
                    ->label('Leído')
                    ->placeholder('Todos')
                    ->trueLabel('Leídos')
                    ->falseLabel('No leídos'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn ($record) => 'Mensaje de: ' . $record->name)
                    ->modalContent(fn ($record) => view('filament.resources.property-messages.view-message', ['record' => $record]))
                    ->after(function ($record) {
                        // Marcar como leído al ver
                        if (!$record->is_read) {
                            $record->update(['is_read' => true]);
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay mensajes')
            ->emptyStateDescription('Cuando recibas consultas sobre tus propiedades, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-envelope');
    }
}
