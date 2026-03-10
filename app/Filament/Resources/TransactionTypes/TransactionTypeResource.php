<?php

namespace App\Filament\Resources\TransactionTypes;

use BackedEnum;
use UnitEnum;
use App\Models\TransactionType;
use App\Filament\Resources\TransactionTypes\Pages\ListTransactionTypes;
use App\Filament\Resources\TransactionTypes\Pages\CreateTransactionType;
use App\Filament\Resources\TransactionTypes\Pages\EditTransactionType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Nnjeim\World\Models\Country;

class TransactionTypeResource extends Resource
{
    protected static ?string $model = TransactionType::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-arrows-left-right-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Inmobiliario';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tipos de Operación';

    protected static ?string $modelLabel = 'Tipo de Operación';

    protected static ?string $pluralModelLabel = 'Tipos de Operación';

    public static function form(Schema $schema): Schema
    {
        $countryOptions = collect([['INTL', 'INTL — Internacional (Fallback)']])
            ->concat(
                Country::orderBy('name')
                    ->get()
                    ->map(fn($c) => [$c->iso2, $c->iso2 . ' — ' . $c->name])
            )
            ->pluck(1, 0)
            ->toArray();

        $valueEnOptions = [
            'sale'           => 'sale — Venta',
            'rent'           => 'rent — Alquiler / Arriendo / Renta',
            'temporary_rent' => 'temporary_rent — Alquiler Temporal / Vacacional',
        ];

        return $schema->components([
            Section::make('Identificación')
                ->description('País al que aplica este tipo y sus valores internos.')
                ->schema([
                    Select::make('country_code')
                        ->label('País')
                        ->options($countryOptions)
                        ->searchable()
                        ->required()
                        ->helperText('Usa INTL como fallback para países sin configuración propia.'),
                    TextInput::make('value')
                        ->label('Valor (slug)')
                        ->required()
                        ->maxLength(50)
                        ->helperText('Identificador interno. Ej: alquiler, arriendo, renta'),
                    TextInput::make('label')
                        ->label('Etiqueta visible')
                        ->required()
                        ->maxLength(100)
                        ->helperText('Texto que ve el usuario. Ej: Alquiler, Arriendo, Renta'),
                    Select::make('value_en')
                        ->label('Equivalente en inglés (matching)')
                        ->options($valueEnOptions)
                        ->searchable()
                        ->required()
                        ->helperText('Clave universal para el sistema de matching entre países.'),
                ])->columns(2),
            Section::make('Orden y Estado')
                ->schema([
                    TextInput::make('order')
                        ->label('Orden')
                        ->numeric()
                        ->default(99)
                        ->required(),
                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true),
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
                    ->sortable()
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Etiqueta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Valor (slug)')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('value_en')
                    ->label('Equivalente EN')
                    ->badge()
                    ->color('info'),
                TextColumn::make('order')
                    ->label('Orden')
                    ->sortable(),
                BooleanColumn::make('is_active')
                    ->label('Activo')
                    ->sortable(),
            ])
            ->defaultSort('country_code')
            ->filters([
                SelectFilter::make('country_code')
                    ->label('País')
                    ->options(
                        TransactionType::distinct('country_code')
                            ->orderBy('country_code')
                            ->pluck('country_code', 'country_code')
                    ),
                SelectFilter::make('is_active')
                    ->label('Estado')
                    ->options([
                        '1' => 'Activo',
                        '0' => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->after(fn($record) => TransactionType::clearCache($record->country_code)),
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
            'index'  => ListTransactionTypes::route('/'),
            'create' => CreateTransactionType::route('/create'),
            'edit'   => EditTransactionType::route('/{record}/edit'),
        ];
    }
}
