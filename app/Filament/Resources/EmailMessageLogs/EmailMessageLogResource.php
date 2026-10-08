<?php

namespace App\Filament\Resources\EmailMessageLogs;

use App\Filament\Resources\EmailMessageLogs\Pages\ListEmailMessageLogs;
use App\Filament\Resources\EmailMessageLogs\Tables\EmailMessageLogsTable;
use App\Models\EmailMessageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailMessageLogResource extends Resource
{
    protected static ?string $model = EmailMessageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Logs Email';

    protected static ?string $modelLabel = 'Log Email';

    protected static ?string $pluralModelLabel = 'Logs Email';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    public static function table(Table $table): Table
    {
        return EmailMessageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailMessageLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
