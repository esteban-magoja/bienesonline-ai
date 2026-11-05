<?php

namespace App\Filament\Resources\EmailSends\Schemas;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailSendInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $count = fn (EmailSend $record, string ...$statuses): int => $record->recipients()->whereIn('status', $statuses)->count();

        return $schema->components([
            Section::make('Envío')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('template.name')
                        ->label('Mensaje')
                        ->placeholder('(mensaje borrado)')
                        ->url(fn (EmailSend $record): ?string => $record->template ? EmailTemplateResource::getUrl('edit', ['record' => $record->template]) : null),
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => $state === EmailSend::STATUS_COMPLETED ? 'Terminado' : 'Enviando')
                        ->color(fn (string $state): string => $state === EmailSend::STATUS_COMPLETED ? 'success' : 'info'),
                    TextEntry::make('created_at')
                        ->label('Iniciado')
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('finished_at')
                        ->label('Terminado')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                    TextEntry::make('sender.name')
                        ->label('Enviado por')
                        ->placeholder('—'),
                    TextEntry::make('sent')
                        ->label('Enviados')
                        ->state(fn (EmailSend $record): int => $count($record, EmailSendRecipient::STATUS_SENT))
                        ->badge()
                        ->color('success'),
                    TextEntry::make('pending')
                        ->label('Pendientes')
                        ->state(fn (EmailSend $record): int => $count($record, EmailSendRecipient::STATUS_PENDING, EmailSendRecipient::STATUS_SENDING))
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('failed')
                        ->label('Fallidos / omitidos')
                        ->state(fn (EmailSend $record): string => $count($record, EmailSendRecipient::STATUS_FAILED) . ' / ' . $count($record, EmailSendRecipient::STATUS_SKIPPED))
                        ->badge()
                        ->color('danger'),
                ]),
            Section::make('Contenido enviado')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('subject')
                        ->label('Asunto'),
                    TextEntry::make('body')
                        ->label('Cuerpo')
                        ->html()
                        ->prose(),
                ]),
        ]);
    }
}
