<?php

namespace App\Filament\Widgets;

use App\Models\PropertyListing;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Wave\Subscription;
use Wave\User;

class DashboardWidget extends Widget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.dashboard-widget';

    /**
     * @return array{
     *     totalUsers: int,
     *     totalSubscribers: int,
     *     premiumUsers: int,
     *     totalListings: int,
     *     activeListings: int,
     *     listingsByCountry: Collection,
     * }
     */
    protected function getViewData(): array
    {
        $listingsByCountry = PropertyListing::query()
            ->selectRaw('country, COUNT(*) as total')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get();

        return [
            'totalUsers'       => User::count(),
            'totalSubscribers' => Subscription::where('status', 'active')->count(),
            'premiumUsers'     => DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', 'users')
                ->where('roles.name', 'premium')
                ->count(),
            'totalListings'    => PropertyListing::count(),
            'activeListings'   => PropertyListing::where('is_active', true)->count(),
            'listingsByCountry' => $listingsByCountry,
        ];
    }
}
