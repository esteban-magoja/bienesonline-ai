<?php

namespace App\Filament\Resources\EmailContacts;

use App\Filament\Resources\EmailContacts\Pages\CreateEmailContact;
use App\Filament\Resources\EmailContacts\Pages\EditEmailContact;
use App\Filament\Resources\EmailContacts\Pages\ListEmailContacts;
use App\Filament\Resources\EmailContacts\RelationManagers\EmailRecipientsRelationManager;
use App\Filament\Resources\EmailContacts\Schemas\EmailContactForm;
use App\Filament\Resources\EmailContacts\Tables\EmailContactsTable;
use App\Models\EmailContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EmailContactResource extends Resource
{
    protected static ?string $model = EmailContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Contactos';

    protected static ?string $modelLabel = 'Contacto';

    protected static ?string $pluralModelLabel = 'Contactos';

    protected static string|UnitEnum|null $navigationGroup = 'Comunicaciones';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return EmailContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EmailRecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailContacts::route('/'),
            'create' => CreateEmailContact::route('/create'),
            'edit' => EditEmailContact::route('/{record}/edit'),
        ];
    }
}
