<?php

namespace App\Filament\Resources\WhatsAppMessageLogs;

use App\Filament\Resources\WhatsAppMessageLogs\Pages\ListWhatsAppMessageLogs;
use App\Filament\Resources\WhatsAppMessageLogs\Tables\WhatsAppMessageLogsTable;
use App\Models\WhatsAppMessageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WhatsAppMessageLogResource extends Resource
{
    protected static ?string $model = WhatsAppMessageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Logs WhatsApp';

    protected static ?string $modelLabel = 'Log WhatsApp';

    protected static ?string $pluralModelLabel = 'Logs WhatsApp';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    public static function table(Table $table): Table
    {
        return WhatsAppMessageLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppMessageLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
