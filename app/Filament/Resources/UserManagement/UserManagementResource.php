<?php

namespace App\Filament\Resources\UserManagement;

use App\Filament\Resources\UserManagement\Pages\ListUserManagement;
use App\Filament\Resources\UserManagement\Pages\ViewUserManagement;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserManagementResource extends Resource
{
    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = 'phosphor-user-gear-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Usuarios';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Gestión de Usuarios';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Gestión de Usuarios';

    protected static ?string $slug = 'user-management';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Perfil')
                ->icon('phosphor-user-circle-duotone')
                ->columns(3)
                ->schema([
                    ImageEntry::make('avatar')
                        ->label('Avatar')
                        ->circular()
                        ->defaultImageUrl(url('storage/demo/default.png?v=2'))
                        ->columnSpan(1),
                    Grid::make(2)
                        ->columnSpan(2)
                        ->schema([
                            TextEntry::make('name')
                                ->label('Nombre completo'),
                            TextEntry::make('username')
                                ->label('Usuario (slug)')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('email')
                                ->label('Email')
                                ->copyable(),
                            TextEntry::make('agency')
                                ->label('Agencia / Inmobiliaria')
                                ->placeholder('—'),
                            TextEntry::make('locale')
                                ->label('Idioma preferido')
                                ->badge()
                                ->color('info')
                                ->placeholder('—'),
                            TextEntry::make('created_at')
                                ->label('Miembro desde')
                                ->dateTime('d/m/Y H:i')
                                ->since(),
                        ]),
                ]),

            Section::make('Contacto y Ubicación')
                ->icon('phosphor-map-pin-duotone')
                ->columns(3)
                ->collapsed()
                ->schema([
                    TextEntry::make('movil')
                        ->label('Teléfono / Móvil')
                        ->placeholder('—')
                        ->copyable(),
                    TextEntry::make('address')
                        ->label('Dirección')
                        ->placeholder('—'),
                    TextEntry::make('city')
                        ->label('Ciudad')
                        ->placeholder('—'),
                    TextEntry::make('state')
                        ->label('Provincia / Estado')
                        ->placeholder('—'),
                    TextEntry::make('country')
                        ->label('País')
                        ->placeholder('—'),
                ]),

            Section::make('Verificaciones y Consentimientos')
                ->icon('phosphor-shield-check-duotone')
                ->columns(3)
                ->schema([
                    IconEntry::make('email_verified')
                        ->label('Email verificado')
                        ->state(fn (User $record): bool => $record->email_verified_at !== null)
                        ->boolean()
                        ->trueIcon('phosphor-check-circle-duotone')
                        ->falseIcon('phosphor-x-circle-duotone')
                        ->trueColor('success')
                        ->falseColor('danger'),
                    TextEntry::make('email_verified_at')
                        ->label('Verificado el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('No verificado'),
                    IconEntry::make('verified')
                        ->label('Cuenta verificada (Wave)')
                        ->state(fn (User $record): bool => (bool) $record->verified)
                        ->boolean()
                        ->trueIcon('phosphor-seal-check-duotone')
                        ->falseIcon('phosphor-seal-duotone')
                        ->trueColor('success')
                        ->falseColor('warning'),
                    IconEntry::make('terms_accepted')
                        ->label('Términos aceptados')
                        ->boolean()
                        ->trueIcon('phosphor-check-circle-duotone')
                        ->falseIcon('phosphor-x-circle-duotone')
                        ->trueColor('success')
                        ->falseColor('danger'),
                    TextEntry::make('terms_accepted_at')
                        ->label('Aceptados el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('No aceptados'),
                    TextEntry::make('roles.name')
                        ->label('Rol(es)')
                        ->badge()
                        ->color('primary'),
                    IconEntry::make('whatsapp_opt_in')
                        ->label('Notif. WhatsApp activas')
                        ->boolean()
                        ->trueIcon('phosphor-whatsapp-logo-duotone')
                        ->falseIcon('phosphor-x-circle-duotone')
                        ->trueColor('success')
                        ->falseColor('gray'),
                    TextEntry::make('whatsapp_opt_in_at')
                        ->label('WhatsApp activado el')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—'),
                    IconEntry::make('movil_verified')
                        ->label('Móvil verificado (WhatsApp)')
                        ->state(fn (User $record): bool => $record->movil_verified_at !== null)
                        ->boolean()
                        ->trueIcon('phosphor-device-mobile-duotone')
                        ->falseIcon('phosphor-x-circle-duotone')
                        ->trueColor('success')
                        ->falseColor('gray'),
                ]),

            Section::make('Membresía / Suscripción')
                ->icon('phosphor-credit-card-duotone')
                ->columns(3)
                ->schema([
                    TextEntry::make('subscription_status')
                        ->label('Estado')
                        ->state(function (User $record): string {
                            $sub = $record->subscriptions()->orderByDesc('created_at')->first();
                            if (! $sub) {
                                return 'Sin suscripción';
                            }

                            return match ($sub->status) {
                                'active'   => 'Activa',
                                'canceled' => 'Cancelada',
                                'past_due' => 'Vencida',
                                'trialing' => 'En prueba',
                                default    => ucfirst($sub->status),
                            };
                        })
                        ->badge()
                        ->color(function (User $record): string {
                            $sub = $record->subscriptions()->orderByDesc('created_at')->first();
                            if (! $sub) {
                                return 'gray';
                            }

                            return match ($sub->status) {
                                'active'   => 'success',
                                'trialing' => 'warning',
                                'canceled' => 'danger',
                                default    => 'gray',
                            };
                        }),
                    TextEntry::make('subscription_plan')
                        ->label('Plan')
                        ->state(function (User $record): string {
                            $sub = $record->subscriptions()->with('plan')->orderByDesc('created_at')->first();

                            return $sub?->plan?->name ?? '—';
                        })
                        ->placeholder('—'),
                    TextEntry::make('subscription_cycle')
                        ->label('Ciclo de facturación')
                        ->state(function (User $record): string {
                            $sub = $record->subscriptions()->orderByDesc('created_at')->first();
                            if (! $sub) {
                                return '—';
                            }

                            return match ($sub->cycle) {
                                'month'  => 'Mensual',
                                'year'   => 'Anual',
                                'onetime' => 'Único',
                                default  => ucfirst($sub->cycle ?? '—'),
                            };
                        })
                        ->placeholder('—'),
                    TextEntry::make('trial_ends_at')
                        ->label('Fin del período de prueba')
                        ->dateTime('d/m/Y')
                        ->placeholder('—'),
                    TextEntry::make('subscription_ends_at')
                        ->label('Vence el')
                        ->state(function (User $record): ?string {
                            $sub = $record->subscriptions()->orderByDesc('created_at')->first();

                            return $sub?->ends_at?->format('d/m/Y') ?? null;
                        })
                        ->placeholder('—'),
                    TextEntry::make('subscription_vendor')
                        ->label('Proveedor de pago')
                        ->state(function (User $record): string {
                            $sub = $record->subscriptions()->orderByDesc('created_at')->first();

                            return $sub?->vendor_slug ?? '—';
                        })
                        ->badge()
                        ->color('info')
                        ->placeholder('—'),
                ]),

            Section::make('Actividad en la Plataforma')
                ->icon('phosphor-chart-bar-duotone')
                ->columns(4)
                ->schema([
                    TextEntry::make('listings_total')
                        ->label('Anuncios totales')
                        ->state(fn (User $record): int => $record->propertyListings()->count())
                        ->badge()
                        ->color('info'),
                    TextEntry::make('listings_active')
                        ->label('Anuncios activos')
                        ->state(fn (User $record): int => $record->propertyListings()->where('is_active', true)->count())
                        ->badge()
                        ->color('success'),
                    TextEntry::make('requests_total')
                        ->label('Solicitudes totales')
                        ->state(fn (User $record): int => $record->propertyRequests()->count())
                        ->badge()
                        ->color('info'),
                    TextEntry::make('requests_active')
                        ->label('Solicitudes activas')
                        ->state(fn (User $record): int => $record->propertyRequests()->where('is_active', true)->count())
                        ->badge()
                        ->color('success'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(url('storage/demo/default.png?v=2')),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                IconColumn::make('email_verified_at')
                    ->label('Email')
                    ->boolean()
                    ->trueIcon('phosphor-envelope-simple-open-duotone')
                    ->falseIcon('phosphor-envelope-simple-duotone')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record) => $record->email_verified_at ? 'Email verificado' : 'Email no verificado'),
                IconColumn::make('terms_accepted')
                    ->label('Términos')
                    ->boolean()
                    ->trueIcon('phosphor-check-circle-duotone')
                    ->falseIcon('phosphor-x-circle-duotone')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn ($record) => $record->terms_accepted ? 'Términos aceptados' : 'No aceptó términos'),
                IconColumn::make('whatsapp_opt_in')
                    ->label('WhatsApp')
                    ->boolean()
                    ->trueIcon('phosphor-whatsapp-logo-duotone')
                    ->falseIcon('phosphor-x-circle-duotone')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->whatsapp_opt_in ? 'Notif. WA activas' : 'Sin notif. WA'),
                IconColumn::make('movil_verified_at')
                    ->label('Móvil')
                    ->boolean()
                    ->trueIcon('phosphor-device-mobile-duotone')
                    ->falseIcon('phosphor-x-circle-duotone')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record) => $record->movil_verified_at ? 'Móvil verificado' : 'Móvil no verificado'),
                TextColumn::make('subscription_status')
                    ->label('Membresía')
                    ->state(function (User $record): string {
                        $sub = $record->subscriptions()->orderByDesc('created_at')->first();
                        if (! $sub) {
                            return 'Sin suscripción';
                        }

                        return match ($sub->status) {
                            'active'   => 'Activa',
                            'canceled' => 'Cancelada',
                            'past_due' => 'Vencida',
                            'trialing' => 'En prueba',
                            default    => ucfirst($sub->status),
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Activa'      => 'success',
                        'En prueba'   => 'warning',
                        'Cancelada',
                        'Vencida'     => 'danger',
                        default       => 'gray',
                    }),
                TextColumn::make('property_listings_count')
                    ->label('Anuncios')
                    ->counts('propertyListings')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('property_requests_count')
                    ->label('Solicitudes')
                    ->counts('propertyRequests')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('email_verified')
                    ->label('Email verificado')
                    ->options([
                        'yes' => 'Verificado',
                        'no'  => 'No verificado',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'yes' => $query->whereNotNull('email_verified_at'),
                            'no'  => $query->whereNull('email_verified_at'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('terms_accepted')
                    ->label('Términos aceptados')
                    ->options([
                        '1' => 'Aceptó términos',
                        '0' => 'No aceptó términos',
                    ]),
                SelectFilter::make('whatsapp_opt_in')
                    ->label('WhatsApp activo')
                    ->options([
                        '1' => 'Con WhatsApp',
                        '0' => 'Sin WhatsApp',
                    ]),
                SelectFilter::make('movil_verified')
                    ->label('Móvil verificado')
                    ->options([
                        'yes' => 'Verificado',
                        'no'  => 'No verificado',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'yes' => $query->whereNotNull('movil_verified_at'),
                            'no'  => $query->whereNull('movil_verified_at'),
                            default => $query,
                        };
                    }),
                Filter::make('has_subscription')
                    ->label('Con suscripción activa')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'subscriptions',
                        fn (Builder $q) => $q->where('status', 'active')
                    )),
                Filter::make('no_subscription')
                    ->label('Sin suscripción activa')
                    ->query(fn (Builder $query) => $query->whereDoesntHave(
                        'subscriptions',
                        fn (Builder $q) => $q->where('status', 'active')
                    )),
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name'),
            ])
            ->recordAction('view')
            ->recordActions([
                ViewAction::make(),
                Action::make('impersonate')
                    ->label('Impersonar')
                    ->icon('phosphor-user-switch-duotone')
                    ->url(fn (User $record) => route('impersonate', $record))
                    ->visible(fn (User $record) => auth()->user()->id !== $record->id)
                    ->color('warning'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'subscriptions']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UserNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserManagement::route('/'),
            'view'  => ViewUserManagement::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}
