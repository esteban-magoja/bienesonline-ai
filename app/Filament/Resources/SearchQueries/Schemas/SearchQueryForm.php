<?php

namespace App\Filament\Resources\SearchQueries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
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
                    ->alphaDash()
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
            ]);
    }
}
