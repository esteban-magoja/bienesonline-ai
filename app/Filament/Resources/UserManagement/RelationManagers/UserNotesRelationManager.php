<?php

namespace App\Filament\Resources\UserManagement\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'userNotes';

    protected static ?string $title = 'Notas administrativas';

    protected static ?string $modelLabel = 'nota';

    protected static ?string $pluralModelLabel = 'notas';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('content')
                ->label('Nota')
                ->placeholder('Escriba aquí la nota sobre este usuario...')
                ->rows(4)
                ->required()
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')
                    ->label('Nota')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('admin.name')
                    ->label('Escrita por')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar nota')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['admin_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin notas')
            ->emptyStateDescription('Agregue la primera nota sobre este usuario.')
            ->emptyStateIcon('phosphor-note-duotone');
    }
}
