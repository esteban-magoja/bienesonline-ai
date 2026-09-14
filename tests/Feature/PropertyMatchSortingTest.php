<?php

declare(strict_types=1);

use App\Http\Controllers\PropertyMatchController;
use App\Models\PropertyListing;
use App\Models\PropertyRequest;
use App\Models\User;
use App\Services\PropertyMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::query()->firstOrCreate([
        'name' => 'registered',
        'guard_name' => 'web',
    ]);

    View::addNamespace('theme', resource_path('themes/anchor'));
    Cache::flush();
});

function sortableMatch(int $id, string $createdAt, int $score): PropertyRequest
{
    $match = new PropertyRequest([
        'title' => "Request {$id}",
        'description' => 'Test request',
    ]);
    $match->setAttribute('id', $id);
    $match->setAttribute('created_at', Carbon::parse($createdAt));
    $match->setAttribute('match_score', $score);
    $match->setAttribute('match_level', 'exact');

    return $match;
}

function renderSortableMatches(User $user, PropertyListing $listing, Collection $matches, array $query = []): \Illuminate\Pagination\LengthAwarePaginator
{
    test()->actingAs($user);
    test()->mock(PropertyMatchingService::class, function ($mock) use ($matches): void {
        $mock->shouldReceive('getAllMatchesForListing')
            ->once()
            ->andReturn($matches);
    });

    $view = app(PropertyMatchController::class)->show(
        Request::create('/dashboard/matches/listing/'.$listing->id, 'GET', $query),
        $listing,
    );

    return $view->getData()['matches'];
}

it('orders matches by newest request by default', function (): void {
    $user = User::factory()->create();
    $listing = PropertyListing::factory()->create(['user_id' => $user->id]);
    $matches = collect([
        sortableMatch(1, '2026-01-01 10:00:00', 95),
        sortableMatch(2, '2026-03-01 10:00:00', 55),
        sortableMatch(3, '2026-02-01 10:00:00', 80),
    ]);

    $paginator = renderSortableMatches($user, $listing, $matches, ['sort' => 'invalid']);

    expect($paginator->items())->toHaveCount(3)
        ->and($paginator->items()[0]->id)->toBe(2)
        ->and($paginator->items()[1]->id)->toBe(3)
        ->and($paginator->items()[2]->id)->toBe(1);
});

it('orders matches by score when requested', function (): void {
    $user = User::factory()->create();
    $listing = PropertyListing::factory()->create(['user_id' => $user->id]);
    $matches = collect([
        sortableMatch(1, '2026-03-01 10:00:00', 55),
        sortableMatch(2, '2026-01-01 10:00:00', 95),
        sortableMatch(3, '2026-02-01 10:00:00', 80),
    ]);

    $paginator = renderSortableMatches($user, $listing, $matches, ['sort' => 'score']);

    expect($paginator->items()[0]->id)->toBe(2)
        ->and($paginator->items()[1]->id)->toBe(3)
        ->and($paginator->items()[2]->id)->toBe(1);
});

it('paginates every match instead of limiting the detail view to twenty', function (): void {
    $user = User::factory()->create();
    $listing = PropertyListing::factory()->create(['user_id' => $user->id]);
    $matches = collect(range(1, 25))
        ->map(fn (int $id): PropertyRequest => sortableMatch(
            $id,
            Carbon::create(2026, 1, 1)->addDays($id)->toDateTimeString(),
            $id,
        ));

    $paginator = renderSortableMatches($user, $listing, $matches, [
        'sort' => 'newest',
        'page' => 3,
    ]);

    expect($paginator->total())->toBe(25)
        ->and($paginator->currentPage())->toBe(3)
        ->and($paginator->items())->toHaveCount(5)
        ->and($paginator->items()[0]->id)->toBe(5)
        ->and($paginator->items()[4]->id)->toBe(1);
});
