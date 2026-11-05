<?php

namespace App\Filament\Resources\EmailContacts\Actions;

use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Models\EmailSend;
use App\Models\EmailSendRecipient;
use App\Models\EmailTemplate;
use App\Services\AdminEmail\AdminEmailService;
use App\Services\AdminEmail\EmailContactCsvImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * "Importar CSV" en Contactos: crea/completa contactos y, opcionalmente, envía un mensaje a
 * todos los del archivo (contactos y usuarios registrados). El archivo se borra al terminar.
 */
class ImportContactsCsvAction
{
    private const DISK = 'local';

    public static function make(): Action
    {
        return Action::make('importCsv')
            ->label('Importar CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Importar contactos desde CSV')
            ->modalDescription('Columnas: email (obligatoria), nombre, inmobiliaria, pais, whatsapp, notas. También sirve un archivo con un email por línea. Desde Excel: "Guardar como" → CSV.')
            ->modalSubmitActionLabel('Importar')
            ->schema([
                FileUpload::make('file')
                    ->label('Archivo CSV')
                    ->disk(self::DISK)
                    ->directory('tmp/email-contact-imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'])
                    ->maxSize(5120)
                    ->required(),
                TextInput::make('source')
                    ->label('Origen (opcional)')
                    ->helperText('Se anota en los contactos nuevos, ej: "Feria Córdoba".')
                    ->maxLength(100),
                Select::make('email_template_id')
                    ->label('Enviar un mensaje a todos los del archivo (opcional)')
                    ->placeholder('No enviar')
                    ->options(fn (): array => EmailTemplate::query()->latest()->pluck('name', 'id')->all())
                    ->searchable()
                    ->live(),
                Toggle::make('exclude_already_sent')
                    ->label('Excluir a quienes ya recibieron ese mensaje')
                    ->default(true)
                    ->visible(fn (Get $get): bool => filled($get('email_template_id'))),
            ])
            ->action(function (array $data, EmailContactCsvImporter $importer, AdminEmailService $emailService): void {
                $path = $data['file'];

                try {
                    $result = $importer->import(Storage::disk(self::DISK)->path($path), filled($data['source'] ?? null) ? trim($data['source']) : null);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se pudo importar')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                } finally {
                    Storage::disk(self::DISK)->delete($path);
                }

                $emailSend = null;
                if (filled($data['email_template_id'] ?? null)) {
                    $emailSend = $emailService->send(
                        EmailTemplate::findOrFail($data['email_template_id']),
                        $result['user_ids'],
                        $result['contact_ids'],
                        (bool) ($data['exclude_already_sent'] ?? true),
                        auth()->user(),
                    );
                }

                self::notify($result, filled($data['email_template_id'] ?? null), $emailSend);
            });
    }

    /**
     * @param  array{created: int, updated: int, unchanged: int, users: int, invalid: int, duplicates: int, contact_ids: list<int>, user_ids: list<int>, invalid_lines: list<int>}  $result
     */
    private static function notify(array $result, bool $sendRequested, ?EmailSend $emailSend): void
    {
        $lines = [
            "Contactos nuevos: {$result['created']}",
            "Contactos completados: {$result['updated']}",
            "Ya existían sin cambios: {$result['unchanged']}",
            "Ya son usuarios registrados: {$result['users']}",
        ];

        if ($result['duplicates'] > 0) {
            $lines[] = "Repetidos en el archivo: {$result['duplicates']}";
        }
        if ($result['invalid'] > 0) {
            $lines[] = "Emails inválidos o vacíos: {$result['invalid']} (líneas " . implode(', ', $result['invalid_lines']) . ($result['invalid'] > count($result['invalid_lines']) ? '…' : '') . ')';
        }

        if ($sendRequested) {
            $lines[] = $emailSend
                ? 'Mensaje en cola para ' . $emailSend->recipients()->where('status', EmailSendRecipient::STATUS_PENDING)->count() . ' destinatario(s).'
                : 'Mensaje: no quedó nadie a quien enviarlo (ya lo recibieron o se dieron de baja).';
        }

        $notification = Notification::make()
            ->title('Importación terminada')
            ->body(new HtmlString(implode('<br>', array_map('e', $lines))))
            ->success()
            ->persistent();

        if ($emailSend) {
            $notification->actions([
                Action::make('viewSend')->label('Ver envío')->url(EmailSendResource::getUrl('view', ['record' => $emailSend])),
            ]);
        }

        $notification->send();
    }
}
