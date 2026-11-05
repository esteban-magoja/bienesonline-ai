<?php

namespace App\Filament\Resources\EmailSends\RelationManagers;

use App\Filament\Resources\EmailContacts\EmailContactResource;
use App\Filament\Resources\UserManagement\UserManagementResource;
use App\Models\EmailSendRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Destinatarios';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('agency')
                    ->label('Inmobiliaria')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->state(fn (EmailSendRecipient $record): string => $record->user_id ? 'Usuario' : 'Contacto')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Usuario' ? 'primary' : 'gray')
                    ->url(fn (EmailSendRecipient $record): ?string => match (true) {
                        $record->user_id !== null => UserManagementResource::getUrl('view', ['record' => $record->user_id]),
                        $record->email_contact_id !== null => EmailContactResource::getUrl('edit', ['record' => $record->email_contact_id]),
                        default => null,
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EmailSendRecipient::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => EmailSendRecipient::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(80)
                    ->tooltip(fn (EmailSendRecipient $record): ?string => $record->error_message)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(EmailSendRecipient::STATUS_LABELS),
            ])
            ->defaultSort('id')
            ->poll('10s');
    }
}
