<?php

namespace App\Filament\Resources\EmailContacts\Tables;

use App\Filament\Resources\EmailTemplates\Actions\SendEmailActions;
use App\Filament\Resources\UserManagement\UserManagementResource;
use App\Models\EmailContact;
use App\Models\EmailSendRecipient;
use App\Models\EmailSuppression;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailContactsTable
{
    public static function configure(Table $table): Table
    {
        $sent = fn (Builder $query) => $query->where('status', EmailSendRecipient::STATUS_SENT);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['emailRecipients as emails_sent_count' => $sent])
                ->withMax(['emailRecipients as last_email_at' => $sent], 'sent_at')
                ->addSelect(['is_unsubscribed' => EmailSuppression::selectRaw('1')
                    ->whereColumn('email_suppressions.email', 'email_contacts.email')
                    ->limit(1)]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('agency')
                    ->label('Inmobiliaria')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('country')
                    ->label('País')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->url(fn (EmailContact $record): ?string => $record->whatsapp
                        ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $record->whatsapp)
                        : null)
                    ->openUrlInNewTab()
                    ->color('success')
                    ->placeholder('—'),
                TextColumn::make('emails_sent_count')
                    ->label('Correos')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('last_email_at')
                    ->label('Último correo')
                    ->dateTime('d/m/Y')
                    ->placeholder('Nunca')
                    ->sortable(),
                IconColumn::make('user_id')
                    ->label('Registrado')
                    ->boolean()
                    ->state(fn (EmailContact $record): bool => $record->user_id !== null)
                    ->url(fn (EmailContact $record): ?string => $record->user_id ? UserManagementResource::getUrl('view', ['record' => $record->user_id]) : null)
                    ->toggleable(),
                IconColumn::make('is_unsubscribed')
                    ->label('De baja')
                    ->boolean()
                    ->state(fn (EmailContact $record): bool => (bool) $record->is_unsubscribed)
                    ->trueColor('danger')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Origen')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('País')
                    ->options(fn (): array => EmailContact::query()
                        ->whereNotNull('country')
                        ->distinct()
                        ->orderBy('country')
                        ->pluck('country', 'country')
                        ->all()),
                TernaryFilter::make('contacted')
                    ->label('Contactado')
                    ->placeholder('Todos')
                    ->trueLabel('Recibió algún correo')
                    ->falseLabel('Nunca recibió correos')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('emailRecipients', $sent),
                        false: fn (Builder $query) => $query->whereDoesntHave('emailRecipients', $sent),
                    ),
                TernaryFilter::make('registered')
                    ->label('Registrado')
                    ->placeholder('Todos')
                    ->trueLabel('Ya es usuario')
                    ->falseLabel('No registrado')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('user_id'),
                        false: fn (Builder $query) => $query->whereNull('user_id'),
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                SendEmailActions::bulk(SendEmailActions::RECIPIENTS_CONTACTS),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay contactos')
            ->emptyStateDescription('Cargá posibles usuarios (inmobiliarias, particulares) para enviarles mensajes.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}
