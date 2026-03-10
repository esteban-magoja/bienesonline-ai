<?php

namespace App\Filament\Resources\States;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\States\Pages\ListStates;
use App\Filament\Resources\States\Pages\CreateState;
use App\Filament\Resources\States\Pages\EditState;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

class StatesResource extends Resource
{
    protected static ?string $model = State::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-map-trifold-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Geografía';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Estados / Provincias';

    protected static ?string $modelLabel = 'Estado / Provincia';

    protected static ?string $pluralModelLabel = 'Estados / Provincias';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ubicación')
                ->schema([
                    Select::make('country_code')
                        ->label('País')
                        ->options(
                            Country::orderBy('name')
                                ->get()
                                ->pluck('name', 'iso2')
                        )
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $country = Country::where('iso2', $state)->first();
                                $set('country_id', $country?->id);
                            }
                        }),
                    TextInput::make('country_id')
                        ->label('ID de País')
                        ->numeric()
                        ->readOnly()
                        ->helperText('Se completa automáticamente al seleccionar el país.'),
                ])->columns(2),
            Section::make('Datos del Estado / Provincia')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('state_code')
                        ->label('Código (abreviatura)')
                        ->maxLength(10)
                        ->helperText('Ej: BA, CBA, CDMX'),
                    TextInput::make('type')
                        ->label('Tipo')
                        ->maxLength(100)
                        ->helperText('Ej: province, state, region, department'),
                ])->columns(3),
            Section::make('Coordenadas (opcional)')
                ->schema([
                    TextInput::make('latitude')
                        ->label('Latitud')
                        ->maxLength(20),
                    TextInput::make('longitude')
                        ->label('Longitud')
                        ->maxLength(20),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country_code')
                    ->label('País')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Estado / Provincia')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state_code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cities_count')
                    ->label('Ciudades')
                    ->counts('cities')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('country_code')
            ->filters([
                SelectFilter::make('country_code')
                    ->label('País')
                    ->options(
                        Country::orderBy('name')
                            ->get()
                            ->pluck('name', 'iso2')
                    )
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStates::route('/'),
            'create' => CreateState::route('/create'),
            'edit'   => EditState::route('/{record}/edit'),
        ];
    }
}
