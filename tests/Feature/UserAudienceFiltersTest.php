<?php

declare(strict_types=1);

use App\Filament\Resources\UserManagement\Pages\ListUserManagement;
use App\Models\EmailContact;
use App\Models\EmailTemplate;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use App\Services\AdminEmail\AdminEmailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Wave\Plan;
use Wave\Subscription;

uses(DatabaseTransactions::class);

/**
 * Filtros de Gestión de Usuarios para elegir destinatarios de un mensaje.
 */
beforeEach(function (): void {
    Queue::fake();
    config(['admin_email.mailer' => 'array']);

    $admin = audienceUser();
    $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
    $this->actingAs($admin);
});

function audienceUser(array $attributes = []): User
{
    return User::factory()->create($attributes + ['avatar' => 'demo/default.png']);
}

/**
 * Sin eventos: el observer generaría el embedding y dispararía notificaciones.
 */
function audienceListing(User $user, array $attributes = []): PropertyListing
{
    return PropertyListing::withoutEvents(fn () => PropertyListing::factory()->create($attributes + ['user_id' => $user->id, 'is_active' => true]));
}

it('filtra por usuarios con y sin anuncios activos, con mínimo', function (): void {
    $withTwo = audienceUser();
    audienceListing($withTwo);
    audienceListing($withTwo);
    $withOne = audienceUser();
    audienceListing($withOne);
    $onlyInactive = audienceUser();
    audienceListing($onlyInactive, ['is_active' => false]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('active_listings', ['presence' => 'with'])
        ->assertCanSeeTableRecords([$withTwo, $withOne])
        ->assertCanNotSeeTableRecords([$onlyInactive]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('active_listings', ['presence' => 'with', 'min' => 2])
        ->assertCanSeeTableRecords([$withTwo])
        ->assertCanNotSeeTableRecords([$withOne]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('active_listings', ['presence' => 'without'])
        ->assertCanSeeTableRecords([$onlyInactive])
        ->assertCanNotSeeTableRecords([$withTwo, $withOne]);
});

it('filtra por acceso premium con la misma regla que el sitio (rol premium/admin o suscripción activa)', function (): void {
    $byRole = audienceUser();
    $byRole->assignRole(Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'web']));
    $bySubscription = audienceUser();
    Subscription::query()->create([
        'billable_type' => 'user',
        'billable_id' => $bySubscription->id,
        'plan_id' => Plan::query()->value('id'),
        'vendor_slug' => 'stripe',
        'status' => Subscription::STATUS_ACTIVE,
        'cycle' => 'month',
        'seats' => 1,
    ]);
    $canceled = audienceUser();
    Subscription::query()->create([
        'billable_type' => 'user',
        'billable_id' => $canceled->id,
        'plan_id' => Plan::query()->value('id'),
        'vendor_slug' => 'stripe',
        'status' => 'canceled',
        'cycle' => 'month',
        'seats' => 1,
    ]);
    $free = audienceUser();

    Livewire::test(ListUserManagement::class)
        ->filterTable('premium_access', false)
        ->assertCanSeeTableRecords([$canceled, $free])
        ->assertCanNotSeeTableRecords([$byRole, $bySubscription, auth()->user()]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('premium_access', true)
        ->assertCanSeeTableRecords([$byRole, $bySubscription, auth()->user()])
        ->assertCanNotSeeTableRecords([$canceled, $free]);
});

it('excluye roles', function (): void {
    $premium = audienceUser();
    $premium->assignRole(Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'web']));
    $registered = audienceUser();
    $registered->assignRole(Role::firstOrCreate(['name' => 'registered', 'guard_name' => 'web']));

    Livewire::test(ListUserManagement::class)
        ->filterTable('exclude_roles', ['premium', 'admin'])
        ->assertCanSeeTableRecords([$registered])
        ->assertCanNotSeeTableRecords([$premium, auth()->user()]);
});

it('filtra por país del usuario y por país de sus anuncios activos', function (): void {
    $chileanUser = audienceUser(['country' => 'Chile']);
    $publishesInUruguay = audienceUser(['country' => 'Argentina']);
    audienceListing($publishesInUruguay, ['country' => 'Uruguay']);
    $inactiveInUruguay = audienceUser(['country' => 'Argentina']);
    audienceListing($inactiveInUruguay, ['country' => 'Uruguay', 'is_active' => false]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('country', ['Chile'])
        ->assertCanSeeTableRecords([$chileanUser])
        ->assertCanNotSeeTableRecords([$publishesInUruguay]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('listings_country', ['Uruguay'])
        ->assertCanSeeTableRecords([$publishesInUruguay])
        ->assertCanNotSeeTableRecords([$chileanUser, $inactiveInUruguay]);
});

it('filtra por origen de los anuncios', function (): void {
    $own = audienceUser();
    audienceListing($own, ['source' => null]);
    $legacy = audienceUser();
    audienceListing($legacy, ['source' => 'legacy']);
    $wasi = audienceUser();
    audienceListing($wasi, ['source' => 'wasi']);

    Livewire::test(ListUserManagement::class)
        ->filterTable('listings_source', ['legacy', 'wasi'])
        ->assertCanSeeTableRecords([$legacy, $wasi])
        ->assertCanNotSeeTableRecords([$own]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('listings_source', ['own'])
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$legacy, $wasi]);
});

it('filtra por solicitudes activas', function (): void {
    $withRequest = audienceUser();
    PropertyRequest::withoutEvents(fn () => PropertyRequest::factory()->create(['user_id' => $withRequest->id, 'is_active' => true]));
    $withoutRequest = audienceUser();

    Livewire::test(ListUserManagement::class)
        ->filterTable('active_requests', true)
        ->assertCanSeeTableRecords([$withRequest])
        ->assertCanNotSeeTableRecords([$withoutRequest]);
});

it('filtra por fecha de registro', function (): void {
    $old = audienceUser(['created_at' => now()->subYear()]);
    $recent = audienceUser(['created_at' => now()->subDays(2)]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('registered_between', ['from' => now()->subWeek()->toDateString(), 'until' => now()->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('filtra por quién recibió un mensaje, también si lo recibió como contacto antes de registrarse', function (): void {
    $template = EmailTemplate::factory()->create();
    $received = audienceUser();
    $asContact = EmailContact::factory()->create(['email' => 'antes.contacto@example.com']);
    $notReceived = audienceUser();

    app(AdminEmailService::class)->send($template, [$received->id], [$asContact->id]);
    $registeredLater = audienceUser(['email' => 'Antes.Contacto@example.com']);

    Livewire::test(ListUserManagement::class)
        ->filterTable('received_message', ['email_template_id' => $template->id, 'received' => 'yes'])
        ->assertCanSeeTableRecords([$received, $registeredLater])
        ->assertCanNotSeeTableRecords([$notReceived]);

    Livewire::test(ListUserManagement::class)
        ->filterTable('received_message', ['email_template_id' => $template->id, 'received' => 'no'])
        ->assertCanSeeTableRecords([$notReceived])
        ->assertCanNotSeeTableRecords([$received, $registeredLater]);
});

it('envía a los usuarios filtrados aunque la tabla esté ordenada por cantidad de anuncios', function (): void {
    $template = EmailTemplate::factory()->create();
    $advertiser = audienceUser(['country' => 'Paraguay']);
    audienceListing($advertiser);
    $other = audienceUser(['country' => 'Paraguay']);

    Livewire::test(ListUserManagement::class)
        ->sortTable('property_listings_count', 'desc')
        ->filterTable('country', ['Paraguay'])
        ->filterTable('active_listings', ['presence' => 'with'])
        ->callTableBulkAction('sendEmail', [$advertiser], data: ['email_template_id' => $template->id, 'exclude_already_sent' => true])
        ->assertHasNoTableBulkActionErrors();

    expect($template->recipients()->pluck('user_id')->all())->toBe([$advertiser->id]);
});
