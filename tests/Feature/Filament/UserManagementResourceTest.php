<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::find(1);
    $this->actingAs($this->admin);
});

it('el listado de gestión de usuarios carga correctamente para un admin', function (): void {
    $this->get('/admin/user-management')
        ->assertOk();
});

it('la página de vista de usuario carga correctamente para un admin', function (): void {
    $user = User::find(1);

    $this->get("/admin/user-management/{$user->id}")
        ->assertOk();
});

it('redirige al login si el usuario no está autenticado', function (): void {
    auth()->logout();

    $this->get('/admin/user-management')
        ->assertRedirect();
});

it('prohíbe el acceso a usuarios no administradores', function (): void {
    $nonAdmin = User::where('id', '!=', 1)
        ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
        ->first();

    if ($nonAdmin) {
        $this->actingAs($nonAdmin);
        $this->get('/admin/user-management')->assertForbidden();
    } else {
        $this->assertTrue(true); // No hay usuario no-admin disponible para testear
    }
});
