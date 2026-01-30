<?php

namespace App\Filament\Resources\PropertyMessages;

use App\Filament\Resources\PropertyMessages\Pages\EditPropertyMessage;
use App\Filament\Resources\PropertyMessages\Pages\ListPropertyMessages;
use App\Filament\Resources\PropertyMessages\Schemas\PropertyMessageForm;
use App\Filament\Resources\PropertyMessages\Tables\PropertyMessagesTable;
use App\Models\PropertyMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertyMessageResource extends Resource
{
    protected static ?string $model = PropertyMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;
    
    protected static ?string $navigationLabel = 'Mensajes';
    
    protected static ?string $modelLabel = 'Mensaje';
    
    protected static ?string $pluralModelLabel = 'Mensajes';

    public static function form(Schema $schema): Schema
    {
        return PropertyMessageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertyMessagesTable::configure($table);
    }
    
    public static function getEloquentQuery(): Builder
    {
        // Solo mostrar mensajes de propiedades del usuario autenticado
        return parent::getEloquentQuery()
            ->whereHas('propertyListing', function (Builder $query) {
                $query->where('user_id', auth()->id());
            })
            ->with(['propertyListing', 'user'])
            ->latest();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropertyMessages::route('/'),
            'edit' => EditPropertyMessage::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        // Mostrar contador de mensajes no leídos
        return static::getModel()::whereHas('propertyListing', function (Builder $query) {
            $query->where('user_id', auth()->id());
        })->where('is_read', false)->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
