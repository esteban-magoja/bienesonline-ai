<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre interno')
                    ->helperText('Solo lo ves vos, para identificar el mensaje (ej: "Presentación a inmobiliarias de Chile").')
                    ->required()
                    ->maxLength(255),
                TextInput::make('subject')
                    ->label('Asunto')
                    ->required()
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label('Cuerpo')
                    ->helperText('Variables: {nombre}, {inmobiliaria}, {email}. Si el destinatario no tiene nombre, {nombre} usa la inmobiliaria. El pie con el link de baja se agrega solo.')
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'link'],
                        ['h2', 'h3'],
                        ['bulletList', 'orderedList', 'blockquote'],
                        ['undo', 'redo'],
                    ])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
