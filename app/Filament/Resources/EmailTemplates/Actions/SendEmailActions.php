<?php

namespace App\Filament\Resources\EmailTemplates\Actions;

use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Mail\AdminMessageMail;
use App\Models\EmailContact;
use App\Models\EmailSend;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\AdminEmail\AdminEmailService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Acciones del admin para enviar mensajes (Comunicaciones → Mensajes), compartidas por las
 * tablas de Mensajes, Contactos y Gestión de Usuarios.
 */
class SendEmailActions
{
    public const RECIPIENTS_USERS = 'users';

    public const RECIPIENTS_CONTACTS = 'contacts';

    /**
     * Enviar un mensaje eligiendo usuarios y contactos en el modal.
     */
    public static function send(): Action
    {
        return Action::make('sendEmail')
            ->label('Enviar')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->modalHeading(fn (EmailTemplate $record): string => 'Enviar "' . $record->name . '"')
            ->modalDescription('Cada destinatario recibe su propio correo desde la casilla de contacto.')
            ->modalSubmitActionLabel('Enviar')
            ->schema(fn (EmailTemplate $record): array => [
                self::userSelect(),
                self::contactSelect(),
                self::excludeToggle(),
                self::summaryEntry(fn (Get $get): array => [
                    $record,
                    (array) $get('user_ids'),
                    (array) $get('contact_ids'),
                    (bool) $get('exclude_already_sent'),
                ]),
            ])
            ->action(fn (array $data, EmailTemplate $record) => self::dispatch(
                $record,
                (array) ($data['user_ids'] ?? []),
                (array) ($data['contact_ids'] ?? []),
                (bool) ($data['exclude_already_sent'] ?? true),
            ));
    }

    /**
     * Acción masiva "Enviar mensaje" sobre usuarios o contactos seleccionados en una tabla.
     */
    public static function bulk(string $recipientType): BulkAction
    {
        // Solo los IDs: con "Seleccionar todos" pueden ser miles de registros (no cargar modelos)
        // reorder(): la tabla puede estar ordenada por una columna calculada (ej. cantidad de anuncios)
        $ids = fn (Builder $query): array => $query->toBase()->reorder()->pluck($query->getModel()->getQualifiedKeyName())->all();

        return BulkAction::make('sendEmail')
            ->label('Enviar mensaje')
            ->icon('heroicon-o-paper-airplane')
            ->modalHeading('Enviar mensaje')
            ->modalDescription(fn (Builder $selectedRecordsQuery): string => 'Seleccionados: ' . (clone $selectedRecordsQuery)->toBase()->reorder()->getCountForPagination() . '. Cada destinatario recibe su propio correo desde la casilla de contacto.')
            ->modalSubmitActionLabel('Enviar')
            ->schema(fn (Builder $selectedRecordsQuery): array => [
                Select::make('email_template_id')
                    ->label('Mensaje')
                    ->options(fn (): array => EmailTemplate::query()->latest()->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live(),
                self::excludeToggle(),
                self::summaryEntry(fn (Get $get): array => [
                    EmailTemplate::find($get('email_template_id')),
                    $recipientType === self::RECIPIENTS_USERS ? $ids(clone $selectedRecordsQuery) : [],
                    $recipientType === self::RECIPIENTS_CONTACTS ? $ids(clone $selectedRecordsQuery) : [],
                    (bool) $get('exclude_already_sent'),
                ]),
            ])
            ->action(function (array $data, Builder $selectedRecordsQuery) use ($recipientType, $ids): void {
                $template = EmailTemplate::findOrFail($data['email_template_id']);
                $selectedIds = $ids($selectedRecordsQuery);

                self::dispatch(
                    $template,
                    $recipientType === self::RECIPIENTS_USERS ? $selectedIds : [],
                    $recipientType === self::RECIPIENTS_CONTACTS ? $selectedIds : [],
                    (bool) ($data['exclude_already_sent'] ?? true),
                );
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Envía el mensaje al admin que está logueado, sin registrarlo.
     */
    public static function test(): Action
    {
        return Action::make('sendTestEmail')
            ->label('Enviarme una prueba')
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Enviar una prueba')
            ->modalDescription(fn (): string => 'Se envía a ' . auth()->user()->email . ' con tus datos en las variables. No queda registrada.')
            ->action(function (EmailTemplate $record): void {
                try {
                    app(AdminEmailService::class)->sendTest($record, auth()->user());
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo enviar la prueba')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Prueba enviada a ' . auth()->user()->email)->success()->send();
            });
    }

    /**
     * Muestra el correo como lo vería un destinatario (con los datos del admin en las variables).
     */
    public static function preview(): Action
    {
        return Action::make('previewEmail')
            ->label('Vista previa')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn (EmailTemplate $record): string => $record->subject)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalWidth('3xl')
            ->modalContent(function (EmailTemplate $record) {
                $admin = auth()->user();
                $mail = new AdminMessageMail(
                    subjectTemplate: $record->subject,
                    bodyTemplate: $record->body,
                    recipient: ['email' => $admin->email, 'name' => $admin->name, 'agency' => $admin->agency],
                    unsubscribeUrl: '#',
                );

                return view('filament.admin-email-preview', [
                    'subject' => $mail->envelope()->subject,
                    'html' => $mail->render(),
                ]);
            });
    }

    private static function userSelect(): Select
    {
        return Select::make('user_ids')
            ->label('Usuarios registrados')
            ->multiple()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => User::query()
                ->where(fn (Builder $query) => $query
                    ->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%')
                    ->orWhere('agency', 'ilike', '%' . $search . '%'))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email'])
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name . ' <' . $user->email . '>'])
                ->all())
            ->getOptionLabelsUsing(fn (array $values): array => User::whereIn('id', $values)
                ->get(['id', 'name', 'email'])
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name . ' <' . $user->email . '>'])
                ->all())
            ->live();
    }

    private static function contactSelect(): Select
    {
        return Select::make('contact_ids')
            ->label('Contactos')
            ->multiple()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => EmailContact::query()
                ->where(fn (Builder $query) => $query
                    ->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('agency', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%'))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'agency', 'email'])
                ->mapWithKeys(fn (EmailContact $contact): array => [$contact->id => self::contactLabel($contact)])
                ->all())
            ->getOptionLabelsUsing(fn (array $values): array => EmailContact::whereIn('id', $values)
                ->get(['id', 'name', 'agency', 'email'])
                ->mapWithKeys(fn (EmailContact $contact): array => [$contact->id => self::contactLabel($contact)])
                ->all())
            ->live();
    }

    private static function excludeToggle(): Toggle
    {
        return Toggle::make('exclude_already_sent')
            ->label('Excluir a quienes ya recibieron este mensaje')
            ->default(true)
            ->live();
    }

    /**
     * @param  callable(Get): array{0: ?EmailTemplate, 1: array<int, int|string>, 2: array<int, int|string>, 3: bool}  $arguments
     */
    private static function summaryEntry(callable $arguments): TextEntry
    {
        return TextEntry::make('summary')
            ->label('Resumen')
            ->state(function (Get $get) use ($arguments): string {
                [$template, $userIds, $contactIds, $exclude] = $arguments($get);

                if ($userIds === [] && $contactIds === []) {
                    return 'Elegí al menos un destinatario.';
                }

                $summary = app(AdminEmailService::class)->summarize($template, $userIds, $contactIds, $exclude);

                return self::summaryText($summary);
            });
    }

    /**
     * @param  array{to_send: int, already_sent: int, suppressed: int, invalid: int}  $summary
     */
    private static function summaryText(array $summary): string
    {
        $parts = ['Se enviará a ' . $summary['to_send'] . ' destinatario(s).'];

        if ($summary['already_sent'] > 0) {
            $parts[] = $summary['already_sent'] . ' ya lo recibieron.';
        }
        if ($summary['suppressed'] > 0) {
            $parts[] = $summary['suppressed'] . ' se dieron de baja.';
        }
        if ($summary['invalid'] > 0) {
            $parts[] = $summary['invalid'] . ' con email inválido.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $contactIds
     */
    private static function dispatch(EmailTemplate $template, array $userIds, array $contactIds, bool $excludeAlreadySent): void
    {
        $service = app(AdminEmailService::class);
        $summary = $service->summarize($template, $userIds, $contactIds, $excludeAlreadySent);
        $emailSend = $service->send($template, $userIds, $contactIds, $excludeAlreadySent, auth()->user());

        if (! $emailSend instanceof EmailSend) {
            Notification::make()
                ->title('No hay destinatarios para enviar')
                ->body(self::summaryText($summary))
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Envío en cola')
            ->body(self::summaryText($summary) . ' Salen de a ' . config('admin_email.per_minute') . ' por minuto.')
            ->success()
            ->actions([
                Action::make('viewSend')
                    ->label('Ver envío')
                    ->url(EmailSendResource::getUrl('view', ['record' => $emailSend])),
            ])
            ->send();
    }

    private static function contactLabel(EmailContact $contact): string
    {
        $name = collect([$contact->name, $contact->agency])->filter()->implode(' · ');

        return ($name !== '' ? $name . ' ' : '') . '<' . $contact->email . '>';
    }
}
