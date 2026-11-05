<?php

namespace App\Filament\Resources\EmailContacts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Nnjeim\World\Models\Country;

class EmailContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->maxLength(255)
                    ->requiredWithout('agency')
                    ->validationMessages(['required_without' => 'Completá el nombre o la inmobiliaria.']),
                TextInput::make('agency')
                    ->label('Inmobiliaria')
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : Str::lower(trim($state)))
                    ->unique(ignoreRecord: true),
                Select::make('country')
                    ->label('País')
                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'name')->all())
                    ->searchable(),
                TextInput::make('whatsapp')
                    ->label('WhatsApp')
                    ->tel()
                    ->helperText('Con código de país, ej: +54 9 11 1234 5678')
                    ->maxLength(50),
                TextInput::make('source')
                    ->label('Origen')
                    ->helperText('De dónde salió el contacto (ej: sitio legacy, feria, referido).')
                    ->maxLength(100),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
