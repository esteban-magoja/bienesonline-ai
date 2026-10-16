<?php

namespace App\Filament\Resources\SearchQueries\Tables;

use App\Helpers\PropertySlugHelper;
use App\Models\SearchQuery;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SearchQueriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('active')
                    ->label('Activa')
                    ->sortable(),
                TextColumn::make('query')
                    ->label('Búsqueda')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('country')
                    ->label('País')
                    ->badge()
                    ->sortable(),
                TextColumn::make('results_count')
                    ->label('Anuncios')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('gsc_clicks')
                    ->label('Clics GSC')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("gsc_clicks {$direction} NULLS LAST"))
                    ->toggleable(),
                TextColumn::make('gsc_impressions')
                    ->label('Impresiones GSC')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("gsc_impressions {$direction} NULLS LAST"))
                    ->toggleable(),
                TextColumn::make('gsc_position')
                    ->label('Posición GSC')
                    ->numeric(decimalPlaces: 1)
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw("gsc_position {$direction} NULLS LAST"))
                    ->toggleable(),
                // No usar 'redirectTarget.query': Filament toma Model::query() como relación
                TextColumn::make('redirect_to_id')
                    ->label('Redirige a')
                    ->formatStateUsing(fn (SearchQuery $record): ?string => $record->redirectTarget?->query)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Activa')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
                TernaryFilter::make('redirect_to_id')
                    ->label('Redirección')
                    ->placeholder('Todas')
                    ->trueLabel('Redirigen a otra')
                    ->falseLabel('Principales')
                    ->nullable(),
                SelectFilter::make('country')
                    ->label('País')
                    ->options(fn (): array => SearchQuery::query()
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all()),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('redirectTarget:id,query'))
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SearchQuery $record): string => route('property.semantic-search', [
                        'locale' => 'es',
                        'country' => PropertySlugHelper::normalize($record->country),
                        'searchPath' => 'busqueda',
                        'query' => $record->slug,
                    ]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => SearchQuery::whereKey($records->modelKeys())->update(['active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn (Collection $records) => SearchQuery::whereKey($records->modelKeys())->update(['active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay búsquedas registradas')
            ->emptyStateIcon('heroicon-o-magnifying-glass');
    }
}
