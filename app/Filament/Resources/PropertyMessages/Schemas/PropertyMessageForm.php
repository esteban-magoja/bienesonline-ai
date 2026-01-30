<?php

namespace App\Filament\Resources\PropertyMessages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use App\Models\PropertyListing;

class PropertyMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_listing_id')
                    ->label('Propiedad')
                    ->relationship('propertyListing', 'title')
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->disabled()
                    ->dehydrated(),
                Textarea::make('message')
                    ->label('Mensaje')
                    ->required()
                    ->disabled()
                    ->rows(5)
                    ->columnSpanFull()
                    ->dehydrated(),
                Toggle::make('is_read')
                    ->label('Marcar como leído')
                    ->default(false),
            ]);
    }
}
