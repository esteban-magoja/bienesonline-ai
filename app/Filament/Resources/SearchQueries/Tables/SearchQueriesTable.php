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
                SelectFilter::make('country')
                    ->label('País')
                    ->options(fn (): array => SearchQuery::query()
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all()),
            ])
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
