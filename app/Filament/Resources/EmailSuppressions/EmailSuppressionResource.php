<?php

namespace App\Filament\Resources\EmailSuppressions;

use App\Filament\Resources\EmailSuppressions\Pages\ListEmailSuppressions;
use App\Filament\Resources\EmailSuppressions\Tables\EmailSuppressionsTable;
use App\Models\EmailSuppression;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Bajas: direcciones a las que no se envían mensajes del admin (bajas por link, rebotes y
 * las que se agregan a mano). Se cargan desde "Agregar bajas" y se quitan borrando el registro.
 */
class EmailSuppressionResource extends Resource
{
    protected static ?string $model = EmailSuppression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Bajas';

    protected static ?string $modelLabel = 'Baja';

    protected static ?string $pluralModelLabel = 'Bajas';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'email';

    public static function table(Table $table): Table
    {
        return EmailSuppressionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailSuppressions::route('/'),
        ];
    }
}
