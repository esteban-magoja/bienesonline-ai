<?php

declare(strict_types=1);

use App\Http\Controllers\PropertyMatchController;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate([
        'name' => 'registered',
        'guard_name' => 'web',
    ]);

    View::addNamespace('theme', resource_path('themes/anchor'));
});

it('allows non-premium users to view the matches index', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $view = app(PropertyMatchController::class)->index();

    expect($view->name())->toBe('theme::pages.dashboard.matches.index');
});

it('allows non-premium users to view matches for their own listing', function (): void {
    $user = User::factory()->create();
    $listing = PropertyListing::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
    ]);

    $this->mock(PropertyMatchingService::class, function ($mock): void {
        $mock->shouldReceive('getAllMatchesForListing')
            ->once()
            ->andReturn(collect());
    });

    $this->actingAs($user);

    $view = app(PropertyMatchController::class)->show(
        Request::create('/dashboard/matches/listing/'.$listing->id),
        $listing,
    );

    expect($view->name())->toBe('theme::pages.dashboard.matches.show');
});

it('denies users from viewing matches for another users listing', function (): void {
    $user = User::factory()->create();
    $listingOwner = User::factory()->create();
    $listing = PropertyListing::factory()->create([
        'user_id' => $listingOwner->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.matches.show', $listing))
        ->assertForbidden();
});
