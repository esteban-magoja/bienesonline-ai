<?php

namespace App\Filament\Resources\Cities;

use BackedEnum;
use UnitEnum;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
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
use Nnjeim\World\Models\City;

class CitiesResource extends Resource
{
    protected static ?string $model = City::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-buildings-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Geografía';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Ciudades / Localidades';

    protected static ?string $modelLabel = 'Ciudad / Localidad';

    protected static ?string $pluralModelLabel = 'Ciudades / Localidades';

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
                            $set('state_id', null);
                            if ($state) {
                                $country = Country::where('iso2', $state)->first();
                                $set('country_id', $country?->id);
                            }
                        }),
                    Select::make('state_id')
                        ->label('Estado / Provincia')
                        ->options(fn ($get) => State::where('country_code', $get('country_code'))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabled(fn ($get) => blank($get('country_code')))
                        ->helperText('Seleccioná primero el país.'),
                    TextInput::make('country_id')
                        ->label('ID de País')
                        ->numeric()
                        ->readOnly()
                        ->helperText('Se completa automáticamente.'),
                ])->columns(3),
            Section::make('Datos de la Ciudad / Localidad')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('state_code')
                        ->label('Código de Estado')
                        ->maxLength(10)
                        ->helperText('Ej: BA, CBA'),
                ])->columns(2),
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
                TextColumn::make('state.name')
                    ->label('Estado / Provincia')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Ciudad / Localidad')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state_code')
                    ->label('Cód. Estado')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('latitude')
                    ->label('Lat.')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->label('Lng.')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('state_id')
                    ->label('Estado / Provincia')
                    ->options(
                        State::orderBy('name')
                            ->get()
                            ->pluck('name', 'id')
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
            'index'  => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit'   => EditCity::route('/{record}/edit'),
        ];
    }
}
