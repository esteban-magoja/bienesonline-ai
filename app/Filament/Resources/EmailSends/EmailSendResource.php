<?php

namespace App\Filament\Resources\EmailSends;

use App\Filament\Resources\EmailSends\Pages\ListEmailSends;
use App\Filament\Resources\EmailSends\Pages\ViewEmailSend;
use App\Filament\Resources\EmailSends\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\EmailSends\Schemas\EmailSendInfolist;
use App\Filament\Resources\EmailSends\Tables\EmailSendsTable;
use App\Models\EmailSend;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailSendResource extends Resource
{
    protected static ?string $model = EmailSend::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Envíos';

    protected static ?string $modelLabel = 'Envío';

    protected static ?string $pluralModelLabel = 'Envíos';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function infolist(Schema $schema): Schema
    {
        return EmailSendInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailSendsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailSends::route('/'),
            'view' => ViewEmailSend::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
