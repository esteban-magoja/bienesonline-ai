<?php

namespace App\Filament\Resources\SearchQueries\Schemas;

use App\Models\SearchQuery;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class SearchQueryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country')
                    ->label('País')
                    ->required()
                    ->maxLength(100),
                TextInput::make('query')
                    ->label('Texto de búsqueda')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    // Mismo formato que acepta la ruta de búsquedas indexables: minúsculas, sin acentos
                    ->regex('/^[a-z0-9]+(-[a-z0-9]+)*$/')
                    ->validationMessages(['regex' => 'Solo minúsculas sin acentos, números y guiones (ej: casa-en-venta-cordoba).'])
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state)))
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('country', $get('country')),
                    ),
                TextInput::make('results_count')
                    ->label('Anuncios encontrados')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Toggle::make('active')
                    ->label('Activa')
                    ->default(false),
                Select::make('redirect_to_id')
                    ->label('Redirigir a')
                    ->helperText('Unifica búsquedas similares: la URL de esta búsqueda redirige (301) a la elegida y deja de mostrarse en "Otras búsquedas".')
                    ->placeholder('No redirige')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get, ?SearchQuery $record): array => self::redirectTargetsQuery($get('country'), $record)
                        ->where('query', 'ilike', '%' . $search . '%')
                        ->orderBy('query')
                        ->limit(50)
                        ->pluck('query', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => SearchQuery::find($value)?->query)
                    // Solo búsquedas principales del mismo país (sin cadenas de redirecciones).
                    // No usar ->exists(): dentro del panel Filament le pasa $query en vez de $rule
                    ->rule(fn (Get $get, ?SearchQuery $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        if (filled($value) && ! self::redirectTargetsQuery($get('country'), $record)->whereKey($value)->exists()) {
                            $fail('Elegí una búsqueda principal del mismo país (que no redirija a otra).');
                        }
                    })
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Búsquedas que pueden ser destino de una redirección: mismo país, que no redirigen y distintas de la actual.
     *
     * @return Builder<SearchQuery>
     */
    private static function redirectTargetsQuery(?string $country, ?SearchQuery $record): Builder
    {
        return SearchQuery::query()
            ->where('country', (string) $country)
            ->whereNull('redirect_to_id')
            ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()));
    }
}
