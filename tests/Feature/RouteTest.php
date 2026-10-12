<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

/**
 * Smoke test: las páginas principales del sitio cargan sin errores.
 */
it('responde 200 en las páginas públicas', function (string $url): void {
    $this->get($url)->assertOk();
})->with([
    '/es',
    '/en',
    '/es/argentina',
    '/es/argentina/venta',
    '/es/inmobiliarias',
    '/en/agents',
    '/es/search-properties',
    '/pricing',
    '/blog',
    '/auth/login',
    '/signup',
    '/auth/password/reset',
    '/sitemap.xml',
]);

it('responde 200 en el dashboard, la configuración y el panel admin', function (string $url): void {
    $admin = User::factory()->create(['avatar' => 'demo/default.png', 'terms_accepted' => true]);
    $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

    $this->actingAs($admin)->get($url)->assertOk();
})->with([
    '/dashboard',
    '/dashboard/requests',
    '/dashboard/requests/create',
    '/dashboard/matches',
    '/dashboard/contacts',
    '/dashboard/messages',
    '/dashboard/imports',
    '/property-listings',
    '/property-listings/create',
    '/notifications',
    '/settings/profile',
    '/settings/security',
    '/settings/subscription',
    '/settings/invoices',
    '/admin',
    '/admin/users',
    '/admin/roles',
    '/admin/plans',
    '/admin/search-queries',
    '/admin/country-types',
    '/admin/property-types',
    '/admin/transaction-types',
    '/admin/email-message-logs',
    '/admin/whats-app-message-logs',
]);
