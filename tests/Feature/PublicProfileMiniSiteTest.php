<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\PropertyListing;
use App\Models\UserProfileMember;
use App\Models\UserProfileService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function createMiniSiteUser(array $overrides = []): User
{
    $uniqueId = str_replace('.', '', uniqid('', true));
    $userId = DB::table('users')->insertGetId(array_merge([
        'name' => 'Mini Site ' . $uniqueId,
        'email' => "mini-site-{$uniqueId}@example.com",
        'username' => "mini-site-{$uniqueId}",
        'password' => bcrypt('password'),
        'agency' => 'Mini Site Realty',
        'movil' => '+593999999999',
        'address' => 'Main Street 100',
        'country' => 'Ecuador',
        'terms_accepted' => true,
        'terms_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return User::findOrFail($userId);
}

it('renders the configured mini site sections and team content', function () {
    $user = createMiniSiteUser();
    $user->setProfileKeyValue('about', 'Una inmobiliaria enfocada en servicio personalizado.');
    $user->profileSetting()->create([
        'headline' => 'Tu próximo hogar empieza aquí',
        'show_email' => true,
        'show_phone' => true,
        'show_address' => true,
    ]);
    UserProfileService::create([
        'user_id' => $user->id,
        'name_i18n' => ['es' => 'Tasaciones', 'en' => 'Valuations'],
        'description_i18n' => ['es' => 'Valoramos tu propiedad.', 'en' => 'We value your property.'],
        'is_active' => true,
    ]);
    UserProfileMember::create([
        'user_id' => $user->id,
        'name' => 'Ana Asesora',
        'role' => 'Asesora inmobiliaria',
        'bio_i18n' => ['es' => 'Especialista en ventas.', 'en' => 'Sales specialist.'],
        'is_visible' => true,
    ]);
    PropertyListing::create([
        'user_id' => $user->id,
        'title' => 'Casa destacada de prueba',
        'description' => 'Una casa destacada para probar la tarjeta.',
        'property_type' => 'casa',
        'transaction_type' => 'venta',
        'price' => 250000,
        'currency' => 'USD',
        'bedrooms' => 3,
        'bathrooms' => 2,
        'area' => 145,
        'city' => 'Cuenca',
        'state' => 'Azuay',
        'country' => 'Ecuador',
        'is_featured' => true,
        'is_active' => true,
    ]);

    $this->get("/es/inmobiliaria/{$user->username}")
        ->assertSuccessful()
        ->assertSee('Tu próximo hogar empieza aquí')
        ->assertSee('Tasaciones')
        ->assertSee('Ana Asesora')
        ->assertSee('Casa destacada de prueba')
        ->assertSee('Casa - Venta')
        ->assertSee('Cuenca, Azuay, Ecuador')
        ->assertSee('3 hab.')
        ->assertSee('2 baños')
        ->assertSee('145 m²')
        ->assertSee('Nuestro equipo')
        ->assertDontSee('id="nosotros"', false)
        ->assertDontSee('href="#nosotros"', false);
});

it('respects public contact visibility settings', function () {
    $user = createMiniSiteUser();
    $user->profileSetting()->create([
        'show_email' => false,
        'show_phone' => false,
        'show_address' => false,
    ]);

    $this->get("/es/inmobiliaria/{$user->username}")
        ->assertSuccessful()
        ->assertDontSee($user->email)
        ->assertDontSee($user->movil)
        ->assertDontSee($user->address);
});

it('allows the owner to access mini site settings pages', function () {
    $user = createMiniSiteUser();

    $this->actingAs($user)
        ->get('/settings/public-profile')
        ->assertSuccessful()
        ->assertSee('Public Site');

    $this->actingAs($user)
        ->get('/settings/services')
        ->assertSuccessful()
        ->assertSee('Services');

    $this->actingAs($user)
        ->get('/settings/team')
        ->assertSuccessful()
        ->assertSee('Team');

});

it('translates all mini site settings pages in Spanish', function () {
    $user = createMiniSiteUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->get('/settings/public-profile')
        ->assertSuccessful()
        ->assertSee('Sitio público')
        ->assertDontSee('settings.public_profile.title');

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->get('/settings/services')
        ->assertSuccessful()
        ->assertSee('Servicios')
        ->assertDontSee('settings.public_profile.services_title');

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->get('/settings/team')
        ->assertSuccessful()
        ->assertSee('Equipo')
        ->assertDontSee('settings.public_profile.team_title');
});

it('separates configuration and billing navigation', function () {
    $user = createMiniSiteUser();

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->get('/settings/public-profile')
        ->assertSuccessful()
        ->assertSee('Configuración')
        ->assertSee('Sitio público')
        ->assertSee('Facturación')
        ->assertDontSee('Suscripción');

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->get('/settings/subscription')
        ->assertSuccessful()
        ->assertSee('Facturación')
        ->assertSee('Suscripción')
        ->assertSee('Facturas')
        ->assertDontSee('Sitio público');
});

it('does not allow a visitor to access mini site settings', function () {
    $this->get('/settings/public-profile')->assertRedirect();
});

it('does not expose the deferred profile contact module', function () {
    $this->get('/settings/leads')->assertNotFound();
});
