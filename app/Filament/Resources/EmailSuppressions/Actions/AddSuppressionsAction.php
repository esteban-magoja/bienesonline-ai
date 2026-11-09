<?php

namespace App\Filament\Resources\EmailSuppressions\Actions;

use App\Models\EmailSuppression;
use App\Services\AdminEmail\SuppressionListParser;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * "Agregar bajas": pegar emails o el texto de los rebotes y darlos de baja de una vez.
 */
class AddSuppressionsAction
{
    private const MAX_LISTED = 10;

    public static function make(): Action
    {
        return Action::make('addSuppressions')
            ->label('Agregar bajas')
            ->icon('heroicon-o-plus')
            ->modalHeading('Agregar direcciones de baja')
            ->modalDescription('No se les enviarán más mensajes desde el admin. Pega emails (uno por línea, o separados por comas o espacios) o el texto completo de uno o varios rebotes: se toman solo las direcciones que fallaron.')
            ->modalSubmitActionLabel('Dar de baja')
            ->schema([
                Textarea::make('text')
                    ->label('Emails o texto del rebote')
                    ->rows(10)
                    ->required(),
                Select::make('reason')
                    ->label('Motivo')
                    ->options(EmailSuppression::REASON_LABELS)
                    ->default(EmailSuppression::REASON_BOUNCED)
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data, SuppressionListParser $parser): void {
                $parsed = $parser->parse((string) $data['text']);
                $reason = array_key_exists($data['reason'] ?? '', EmailSuppression::REASON_LABELS) ? $data['reason'] : EmailSuppression::REASON_MANUAL;

                $existing = EmailSuppression::whereIn('email', $parsed['emails'])->pluck('email')->all();
                $added = array_values(array_diff($parsed['emails'], $existing));

                foreach ($added as $email) {
                    EmailSuppression::suppress($email, $reason);
                }

                self::notify($added, $existing, $parsed['temporary'], $parsed['invalid']);
            });
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $existing
     * @param  list<string>  $temporary
     * @param  list<string>  $invalid
     */
    private static function notify(array $added, array $existing, array $temporary, array $invalid): void
    {
        $lines = [];

        if ($added !== []) {
            $lines[] = 'Dadas de baja: ' . count($added) . ' (' . self::listed($added) . ')';
        }
        if ($existing !== []) {
            $lines[] = 'Ya estaban de baja: ' . count($existing);
        }
        if ($temporary !== []) {
            $lines[] = 'Rebote temporal, no se dieron de baja: ' . self::listed($temporary);
        }
        if ($invalid !== []) {
            $lines[] = 'No parecen emails válidos: ' . self::listed($invalid);
        }
        if ($lines === []) {
            $lines[] = 'No se encontró ninguna dirección de email en el texto.';
        }

        Notification::make()
            ->title($added !== [] ? 'Bajas agregadas' : 'No se agregaron bajas')
            ->body(new HtmlString(implode('<br>', array_map('e', $lines))))
            ->status($added !== [] ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    /**
     * @param  list<string>  $emails
     */
    private static function listed(array $emails): string
    {
        $shown = implode(', ', array_slice($emails, 0, self::MAX_LISTED));

        return count($emails) > self::MAX_LISTED ? Str::finish($shown, '…') : $shown;
    }
}
