<?php

namespace App\Filament\Resources\EmailSuppressions\Tables;

use App\Models\EmailContact;
use App\Models\EmailSuppression;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailSuppressionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->addSelect(['is_user' => User::selectRaw('1')
                    ->whereRaw('LOWER(users.email) = email_suppressions.email')
                    ->limit(1)])
                ->addSelect(['is_contact' => EmailContact::selectRaw('1')
                    ->whereColumn('email_contacts.email', 'email_suppressions.email')
                    ->limit(1)]))
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => EmailSuppression::reasonLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        EmailSuppression::REASON_BOUNCED => 'danger',
                        EmailSuppression::REASON_UNSUBSCRIBED => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('belongs_to')
                    ->label('Es')
                    ->state(fn (EmailSuppression $record): ?string => match (true) {
                        (bool) $record->is_user => 'Usuario',
                        (bool) $record->is_contact => 'Contacto',
                        default => null,
                    })
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options(EmailSuppression::REASON_LABELS),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Quitar baja')
                    ->modalHeading('Quitar la baja')
                    ->modalDescription('Esta dirección volverá a recibir los mensajes que le envíes desde el admin.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Quitar bajas seleccionadas'),
                ]),
            ])
            ->emptyStateHeading('No hay bajas')
            ->emptyStateDescription('Aquí aparecen quienes se dieron de baja con el link del correo y las direcciones que agregues a mano, por ejemplo las que rebotaron.')
            ->emptyStateIcon('heroicon-o-no-symbol');
    }
}
