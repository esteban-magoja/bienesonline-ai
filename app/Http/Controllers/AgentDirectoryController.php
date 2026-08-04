<?php

namespace App\Http\Controllers;

use App\Helpers\PropertySlugHelper;
use App\Models\PropertyListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class AgentDirectoryController extends Controller
{
    public function index(
        string $locale,
        ?string $country = null,
        ?string $state = null,
        ?string $city = null
    ): View {
        App::setLocale($locale);

        $resolvedCountry = $country ? $this->resolveLocationSegment('country', $country) : null;
        if ($country && !$resolvedCountry) {
            abort(404);
        }

        $resolvedState = $state ? $this->resolveLocationSegment('state', $state, $resolvedCountry) : null;
        if ($state && !$resolvedState) {
            abort(404);
        }

        $resolvedCity = $city
            ? $this->resolveLocationSegment('city', $city, $resolvedCountry, $resolvedState)
            : null;
        if ($city && !$resolvedCity) {
            abort(404);
        }

        $agents = User::query()
            ->whereNotNull('agency')
            ->whereRaw("TRIM(agency) != ''")
            ->withCount([
                'propertyListings as active_listings_count' => function (Builder $query) use ($resolvedCountry, $resolvedState, $resolvedCity): void {
                    $this->applyListingFilters($query, $resolvedCountry, $resolvedState, $resolvedCity);
                },
            ])
            ->whereHas('propertyListings', function (Builder $query) use ($resolvedCountry, $resolvedState, $resolvedCity): void {
                $this->applyListingFilters($query, $resolvedCountry, $resolvedState, $resolvedCity);
            })
            ->orderByDesc('active_listings_count')
            ->orderBy('agency')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        $baseRouteName = $locale === 'es' ? 'agents.directory.es' : 'agents.directory.en';
        $locationRouteName = $locale === 'es'
            ? 'agents.directory.es.location'
            : 'agents.directory.en.location';
        $routeName = $resolvedCountry ? $locationRouteName : $baseRouteName;
        $profileRouteName = $locale === 'es' ? 'user.profile.es' : 'user.profile.en';
        $locationLinks = $this->getLocationLinks($locationRouteName, $resolvedCountry, $resolvedState, $resolvedCity);
        $locationLevel = $resolvedState ? 'cities' : ($resolvedCountry ? 'states' : 'countries');
        $locationLabel = collect([$resolvedCity, $resolvedState, $resolvedCountry])->filter()->join(', ');
        $directoryLabel = __('properties.agents_directory.directory');
        $title = $locationLabel
            ? "{$directoryLabel} - {$locationLabel}"
            : $directoryLabel;
        $description = $locationLabel
            ? __('properties.agents_directory.description_with_location', ['location' => $locationLabel])
            : __('properties.agents_directory.description');

        $seo = [
            'title' => $title,
            'description' => $description,
            'canonical' => route($routeName, array_filter([
                'locale' => $locale,
                'country' => $resolvedCountry ? PropertySlugHelper::normalize($resolvedCountry) : null,
                'state' => $resolvedState ? PropertySlugHelper::normalize($resolvedState) : null,
                'city' => $resolvedCity ? PropertySlugHelper::normalize($resolvedCity) : null,
            ])),
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'hreflang' => [
                'es' => route($resolvedCountry ? 'agents.directory.es.location' : 'agents.directory.es', array_filter([
                    'locale' => 'es',
                    'country' => $resolvedCountry ? PropertySlugHelper::normalize($resolvedCountry) : null,
                    'state' => $resolvedState ? PropertySlugHelper::normalize($resolvedState) : null,
                    'city' => $resolvedCity ? PropertySlugHelper::normalize($resolvedCity) : null,
                ])),
                'en' => route($resolvedCountry ? 'agents.directory.en.location' : 'agents.directory.en', array_filter([
                    'locale' => 'en',
                    'country' => $resolvedCountry ? PropertySlugHelper::normalize($resolvedCountry) : null,
                    'state' => $resolvedState ? PropertySlugHelper::normalize($resolvedState) : null,
                    'city' => $resolvedCity ? PropertySlugHelper::normalize($resolvedCity) : null,
                ])),
            ],
        ];

        $breadcrumbs = $this->buildBreadcrumbs(
            $locale,
            $baseRouteName,
            $locationRouteName,
            $directoryLabel,
            $resolvedCountry,
            $resolvedState,
            $resolvedCity
        );

        return view('agents-directory', compact(
            'agents',
            'seo',
            'breadcrumbs',
            'locationLabel',
            'profileRouteName',
            'locationLinks',
            'locationLevel'
        ));
    }

    private function buildBreadcrumbs(
        string $locale,
        string $baseRouteName,
        string $locationRouteName,
        string $directoryLabel,
        ?string $country,
        ?string $state,
        ?string $city
    ): array {
        $breadcrumbs = [
            [
                'label' => __('messages.home'),
                'url' => route('home', ['locale' => $locale]),
            ],
            [
                'label' => $directoryLabel,
                'url' => route($baseRouteName, ['locale' => $locale]),
            ],
        ];

        if ($country) {
            $breadcrumbs[] = [
                'label' => $country,
                'url' => route($locationRouteName, [
                    'locale' => $locale,
                    'country' => PropertySlugHelper::normalize($country),
                ]),
            ];
        }

        if ($state) {
            $breadcrumbs[] = [
                'label' => $state,
                'url' => route($locationRouteName, [
                    'locale' => $locale,
                    'country' => PropertySlugHelper::normalize((string) $country),
                    'state' => PropertySlugHelper::normalize($state),
                ]),
            ];
        }

        if ($city) {
            $breadcrumbs[] = [
                'label' => $city,
                'url' => null,
            ];
        }

        return $breadcrumbs;
    }

    private function resolveLocationSegment(
        string $column,
        string $slug,
        ?string $country = null,
        ?string $state = null
    ): ?string {
        if (!in_array($column, ['country', 'state', 'city'], true)) {
            return null;
        }

        $normalizedSlug = PropertySlugHelper::normalize($slug);

        $query = PropertyListing::query()
            ->where('is_active', true)
            ->whereHas('user', function (Builder $builder): void {
                $builder->whereNotNull('agency')
                    ->whereRaw("TRIM(agency) != ''");
            });

        if ($country) {
            $query->whereRaw('LOWER(unaccent(TRIM(country))) = LOWER(unaccent(TRIM(?)))', [$country]);
        }

        if ($state) {
            $query->whereRaw('LOWER(unaccent(TRIM(state))) = LOWER(unaccent(TRIM(?)))', [$state]);
        }

        $values = $query
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) != ''")
            ->selectRaw("DISTINCT TRIM({$column}) as value")
            ->pluck('value');

        foreach ($values as $value) {
            if (PropertySlugHelper::normalize($value) === $normalizedSlug) {
                return $value;
            }
        }

        return null;
    }

    private function applyListingFilters(
        Builder $query,
        ?string $country = null,
        ?string $state = null,
        ?string $city = null
    ): void {
        $query->where('is_active', true);

        if ($country) {
            $query->whereRaw('LOWER(unaccent(TRIM(country))) = LOWER(unaccent(TRIM(?)))', [$country]);
        }

        if ($state) {
            $query->whereRaw('LOWER(unaccent(TRIM(state))) = LOWER(unaccent(TRIM(?)))', [$state]);
        }

        if ($city) {
            $query->whereRaw('LOWER(unaccent(TRIM(city))) = LOWER(unaccent(TRIM(?)))', [$city]);
        }
    }

    private function getLocationLinks(
        string $routeName,
        ?string $country = null,
        ?string $state = null,
        ?string $city = null
    ): array {
        if ($city) {
            return [];
        }

        $column = $state ? 'city' : ($country ? 'state' : 'country');
        $query = PropertyListing::query()
            ->where('is_active', true)
            ->whereHas('user', function (Builder $builder): void {
                $builder->whereNotNull('agency')
                    ->whereRaw("TRIM(agency) != ''");
            });

        $this->applyListingFilters($query, $country, $state);

        $locations = $query
            ->whereNotNull($column)
            ->whereRaw("TRIM({$column}) != ''")
            ->selectRaw("MAX(TRIM({$column})) as value, COUNT(*) as listings_count")
            ->groupByRaw("LOWER(unaccent(TRIM({$column})))")
            ->orderByRaw("LOWER(unaccent(TRIM({$column})))")
            ->get();

        return $locations->map(function (PropertyListing $location) use ($routeName, $column, $country, $state): array {
            $parameters = [
                'locale' => app()->getLocale(),
                'country' => $country ? PropertySlugHelper::normalize($country) : null,
                'state' => $state ? PropertySlugHelper::normalize($state) : null,
                'city' => null,
            ];

            $parameters[$column] = PropertySlugHelper::normalize($location->value);

            return [
                'label' => $location->value,
                'url' => route($routeName, array_filter($parameters, fn (?string $value): bool => $value !== null)),
                'listings_count' => (int) $location->listings_count,
            ];
        })->all();
    }
}
