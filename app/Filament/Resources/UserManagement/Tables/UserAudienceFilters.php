<?php

namespace App\Filament\Resources\UserManagement\Tables;

use App\Models\EmailSendRecipient;
use App\Models\EmailTemplate;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Import\ImportSourceRegistry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Wave\Subscription;

/**
 * Filtros de Gestión de Usuarios para elegir a quién enviar un mensaje (filtrar → seleccionar
 * todos → "Enviar mensaje"). Se combinan entre sí con AND.
 */
class UserAudienceFilters
{
    /** Valor del filtro de origen para los anuncios publicados en el sitio (source NULL) */
    public const SOURCE_OWN = 'own';

    /**
     * @return array<int, Filter|SelectFilter|TernaryFilter>
     */
    public static function all(): array
    {
        return [
            self::activeListings(),
            self::premiumAccess(),
            self::excludeRoles(),
            self::userCountry(),
            self::listingsCountry(),
            self::listingsSource(),
            self::activeRequests(),
            self::registeredBetween(),
            self::receivedMessage(),
        ];
    }

    private static function activeListings(): Filter
    {
        return Filter::make('active_listings')
            ->schema([
                Select::make('presence')
                    ->label('Anuncios activos')
                    ->placeholder('Todos')
                    ->options(['with' => 'Con anuncios activos', 'without' => 'Sin anuncios activos'])
                    ->live(),
                TextInput::make('min')
                    ->label('Mínimo de anuncios activos')
                    ->numeric()
                    ->minValue(1)
                    ->visible(fn ($get): bool => $get('presence') === 'with'),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $active = fn (Builder $listings) => $listings->where('is_active', true);

                return match ($data['presence'] ?? null) {
                    'with' => $query->whereHas('propertyListings', $active, '>=', max(1, (int) ($data['min'] ?? 1))),
                    'without' => $query->whereDoesntHave('propertyListings', $active),
                    default => $query,
                };
            })
            ->indicateUsing(fn (array $data): ?string => match ($data['presence'] ?? null) {
                'with' => 'Con anuncios activos' . ((int) ($data['min'] ?? 0) > 1 ? ' (mín. ' . (int) $data['min'] . ')' : ''),
                'without' => 'Sin anuncios activos',
                default => null,
            });
    }

    /**
     * Misma regla que User::hasPremiumAccess(): rol admin o premium, o suscripción activa.
     */
    private static function premiumAccess(): TernaryFilter
    {
        $premium = fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'premium']))
            ->orWhereHas('subscriptions', fn (Builder $subscriptions) => $subscriptions->where('status', Subscription::STATUS_ACTIVE)));

        return TernaryFilter::make('premium_access')
            ->label('Acceso premium')
            ->placeholder('Todos')
            ->trueLabel('Con acceso premium')
            ->falseLabel('Sin acceso premium')
            ->queries(
                true: $premium,
                false: fn (Builder $query): Builder => $query
                    ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'premium']))
                    ->whereDoesntHave('subscriptions', fn (Builder $subscriptions) => $subscriptions->where('status', Subscription::STATUS_ACTIVE)),
            );
    }

    private static function excludeRoles(): SelectFilter
    {
        return SelectFilter::make('exclude_roles')
            ->label('Excluir rol')
            ->multiple()
            ->options(fn (): array => \Spatie\Permission\Models\Role::query()->orderBy('name')->pluck('name', 'name')->all())
            ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                ? $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', $data['values']))
                : $query);
    }

    private static function userCountry(): SelectFilter
    {
        return SelectFilter::make('country')
            ->label('País del usuario')
            ->multiple()
            ->options(fn (): array => Cache::remember('admin_user_filter_user_countries', 600, fn (): array => User::query()
                ->whereNotNull('country')
                ->where('country', '<>', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country', 'country')
                ->all()));
    }

    private static function listingsCountry(): SelectFilter
    {
        return SelectFilter::make('listings_country')
            ->label('País de sus anuncios activos')
            ->multiple()
            // Cache corto: recorre todos los anuncios activos en cada carga de la tabla
            ->options(fn (): array => Cache::remember('admin_user_filter_listing_countries', 600, fn (): array => PropertyListing::query()
                ->where('is_active', true)
                ->whereNotNull('country')
                ->distinct()
                ->orderBy('country')
                ->pluck('country', 'country')
                ->all()))
            ->query(fn (Builder $query, array $data): Builder => filled($data['values'] ?? null)
                ? $query->whereHas('propertyListings', fn (Builder $listings) => $listings
                    ->where('is_active', true)
                    ->whereIn('country', $data['values']))
                : $query);
    }

    private static function listingsSource(): SelectFilter
    {
        return SelectFilter::make('listings_source')
            ->label('Origen de sus anuncios')
            ->multiple()
            ->options(fn (): array => [self::SOURCE_OWN => 'Publicados en el sitio', config('import.source_name', 'legacy') => 'Sitio legacy']
                + collect(app(ImportSourceRegistry::class)->all())->map(fn ($driver): string => $driver->label())->all())
            ->query(function (Builder $query, array $data): Builder {
                $values = $data['values'] ?? [];

                if (blank($values)) {
                    return $query;
                }

                $sources = array_values(array_diff($values, [self::SOURCE_OWN]));

                return $query->whereHas('propertyListings', fn (Builder $listings) => $listings->where(function (Builder $listings) use ($values, $sources): void {
                    if (in_array(self::SOURCE_OWN, $values, true)) {
                        $listings->orWhereNull('source');
                    }
                    if ($sources !== []) {
                        $listings->orWhereIn('source', $sources);
                    }
                }));
            });
    }

    private static function activeRequests(): TernaryFilter
    {
        $active = fn (Builder $requests) => $requests->where('is_active', true);

        return TernaryFilter::make('active_requests')
            ->label('Solicitudes activas')
            ->placeholder('Todos')
            ->trueLabel('Con solicitudes activas')
            ->falseLabel('Sin solicitudes activas')
            ->queries(
                true: fn (Builder $query): Builder => $query->whereHas('propertyRequests', $active),
                false: fn (Builder $query): Builder => $query->whereDoesntHave('propertyRequests', $active),
            );
    }

    private static function registeredBetween(): Filter
    {
        return Filter::make('registered_between')
            ->schema([
                DatePicker::make('from')->label('Registrado desde'),
                DatePicker::make('until')->label('Registrado hasta'),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->where('users.created_at', '>=', Carbon::parse($date)->startOfDay()))
                ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->where('users.created_at', '<=', Carbon::parse($date)->endOfDay())))
            ->indicateUsing(function (array $data): array {
                $indicators = [];
                if ($data['from'] ?? null) {
                    $indicators[] = 'Registrado desde ' . Carbon::parse($data['from'])->format('d/m/Y');
                }
                if ($data['until'] ?? null) {
                    $indicators[] = 'Registrado hasta ' . Carbon::parse($data['until'])->format('d/m/Y');
                }

                return $indicators;
            });
    }

    /**
     * Recibió (o tiene en cola) un mensaje dado. Al enviar ya se excluye solo a quien lo recibió;
     * este filtro sirve para ver quiénes faltan.
     */
    private static function receivedMessage(): Filter
    {
        return Filter::make('received_message')
            ->schema([
                Select::make('email_template_id')
                    ->label('Mensaje')
                    ->placeholder('Cualquiera')
                    ->options(fn (): array => EmailTemplate::query()->latest()->pluck('name', 'id')->all())
                    ->searchable(),
                Select::make('received')
                    ->label('¿Lo recibió?')
                    ->placeholder('Todos')
                    ->options(['yes' => 'Lo recibió', 'no' => 'No lo recibió']),
            ])
            ->columns(2)
            ->query(function (Builder $query, array $data): Builder {
                $templateId = $data['email_template_id'] ?? null;
                $received = $data['received'] ?? null;

                if (blank($templateId) || blank($received)) {
                    return $query;
                }

                // Por email (igual que la exclusión al enviar): cuenta también lo recibido como contacto antes de registrarse
                $delivered = fn ($recipients) => $recipients->selectRaw('1')
                    ->from('email_send_recipients')
                    ->join('email_sends', 'email_sends.id', '=', 'email_send_recipients.email_send_id')
                    ->where('email_sends.email_template_id', $templateId)
                    ->whereIn('email_send_recipients.status', EmailSendRecipient::DELIVERED_OR_QUEUED)
                    ->whereRaw('email_send_recipients.email = LOWER(users.email)');

                return $received === 'yes'
                    ? $query->whereExists($delivered)
                    : $query->whereNotExists($delivered);
            })
            ->indicateUsing(function (array $data): ?string {
                if (blank($data['email_template_id'] ?? null) || blank($data['received'] ?? null)) {
                    return null;
                }

                $name = EmailTemplate::whereKey($data['email_template_id'])->value('name');

                return ($data['received'] === 'yes' ? 'Recibió "' : 'No recibió "') . $name . '"';
            });
    }
}
