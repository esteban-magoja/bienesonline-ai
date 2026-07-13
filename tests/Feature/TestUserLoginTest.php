<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    $this->testUserEmail = 'usertest@test.mail';
    $this->testUserPassword = 'test1234';
    $this->testUser = User::where('email', $this->testUserEmail)->firstOrFail();
});

test('test user exists with id 8', function (): void {
    expect($this->testUser->id)->toBe(8)
        ->and($this->testUser->name)->toBe('Casas2 Inmobiliaria');
});

test('test user can authenticate with provided credentials', function (): void {
    expect(Auth::attempt([
        'email' => $this->testUserEmail,
        'password' => $this->testUserPassword,
    ]))->toBeTrue();
});

test('authenticated test user can access the dashboard', function (): void {
    Auth::attempt([
        'email' => $this->testUserEmail,
        'password' => $this->testUserPassword,
    ]);

    $this->actingAs($this->testUser);

    $this->get('/dashboard')->assertOk();
});

test('dashboard shows expected stats for test user', function (): void {
    $this->actingAs($this->testUser);

    $response = $this->get('/dashboard');
    $response->assertOk();

    $userListings = \App\Models\PropertyListing::where('user_id', $this->testUser->id)->active()->count();
    $userRequests = \App\Models\PropertyRequest::where('user_id', $this->testUser->id)->active()->count();

    expect($userListings)->toBeGreaterThan(40)
        ->and($userRequests)->toBeGreaterThanOrEqual(1);
});
