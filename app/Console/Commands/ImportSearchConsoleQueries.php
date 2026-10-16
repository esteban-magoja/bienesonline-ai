<?php

namespace App\Console\Commands;

use App\Helpers\PropertySlugHelper;
use App\Models\SearchQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportSearchConsoleQueries extends Command
{
    protected $signature = 'search-queries:import-gsc
                            {files* : CSV "Páginas" exportados de Google Search Console (ej: docs/Chile.csv); varios archivos se suman}
                            {--min-clicks=1 : Activa las búsquedas con al menos estos clics}
                            {--min-impressions=30 : Sin esos clics, activa si tiene al menos estas impresiones (0 desactiva este criterio)}
                            {--min-position=8 : ...y una posición media desde este valor}
                            {--max-position=25 : ...hasta este valor}
                            {--groups=30 : Cantidad de grupos de búsquedas casi iguales a mostrar}
                            {--redirect-duplicates : En cada grupo de casi iguales, redirige (301) las demás a la de más clics}
                            {--dry-run : Muestra el informe sin modificar la base}';

    protected $description = 'Importa métricas de Search Console y activa las búsquedas indexables que traen visitas (nunca desactiva)';

    /** @var array<string, ?string> slug de país en la URL => nombre del país habilitado */
    private array $countryNames = [];

    public function handle(): int
    {
        $paths = array_map(fn (string $file): string => $this->resolvePath($file), (array) $this->argument('files'));

        foreach ($paths as $path) {
            if (! is_file($path)) {
                $this->error("No existe el archivo: {$path}");

                return self::FAILURE;
            }
        }

        ['entries' => $entries, 'ignoredUrls' => $ignoredUrls, 'unknownCountries' => $unknownCountries] = $this->readEntries($paths);

        if ($entries === []) {
            $this->error($unknownCountries !== []
                ? 'Países no habilitados o desconocidos (ignorados): ' . implode(', ', $unknownCountries) . '. Habilitarlos en /admin/country-types.'
                : 'El archivo no tiene URLs de búsquedas indexables (/busqueda/ o /search/).');

            return self::FAILURE;
        }

        $records = $this->loadRecords(array_keys($entries));
        [$entries, $redirected] = $this->mergeRedirectVariants($entries, $records);
        $records = $this->loadRecords(array_keys($entries));

        $classified = $this->classify($entries, $records);
        $groups = $this->nearDuplicateGroups($entries, $classified);
        $redirectDuplicates = (bool) $this->option('redirect-duplicates');
        $variants = $redirectDuplicates
            ? $groups->flatMap(fn (Collection $group): Collection => $group->slice(1))->values()
            : collect();
        $classified = $this->excludeVariants($classified, $variants);

        $this->printSummary($entries, $classified, $variants, $redirected, $ignoredUrls, $unknownCountries);
        $this->printNearDuplicateGroups($groups, $redirectDuplicates);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Modo prueba (--dry-run): no se modificó nada.');

            return self::SUCCESS;
        }

        $redirectedVariants = $this->apply($entries, $records, $classified, $redirectDuplicates ? $groups : collect());

        $this->newLine();
        $this->info(sprintf(
            'Listo: %d activadas, %d creadas activas, %d con métricas actualizadas, %d variantes redirigidas a su principal.',
            count($classified['activate']),
            count($classified['create']),
            count($entries) - count($classified['create']) - count($classified['skip']),
            $redirectedVariants,
        ));

        return self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        return str_starts_with($file, '/') ? $file : base_path($file);
    }

    /**
     * Lee los CSV (columnas: página, clics, impresiones, CTR, posición) y agrupa por país + slug,
     * sumando las versiones es/en de la misma búsqueda (posición ponderada por impresiones).
     * Una URL repetida en varios archivos (exportaciones superpuestas) se cuenta una sola vez.
     *
     * @param  list<string>  $paths
     *
     * @return array{
     *     entries: array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>,
     *     ignoredUrls: int,
     *     unknownCountries: list<string>
     * }
     */
    private function readEntries(array $paths): array
    {
        $entries = [];
        $ignoredUrls = 0;
        $unknownCountries = [];
        $seenUrls = [];

        foreach ($paths as $path) {
            $handle = fopen($path, 'rb');

            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if (count($row) < 5 || ! is_numeric(trim($row[1])) || isset($seenUrls[trim($row[0])])) {
                    continue; // encabezado, fila vacía o URL ya leída en otro archivo
                }

                $seenUrls[trim($row[0])] = true;
                $urlPath = (string) parse_url(trim($row[0]), PHP_URL_PATH);

                if (! preg_match('#^/(?:es|en)/([a-z-]+)/(?:busqueda|search)/([^/]+)/?$#i', $urlPath, $matches)) {
                    $ignoredUrls++;

                    continue;
                }

                $countrySlug = strtolower($matches[1]);
                $country = $this->countryName($countrySlug);

                if ($country === null) {
                    $unknownCountries[$countrySlug] = $countrySlug;

                    continue;
                }

                $slug = Str::slug(str_replace('-', ' ', urldecode($matches[2])));

                if ($slug === '') {
                    $ignoredUrls++;

                    continue;
                }

                $clicks = (int) preg_replace('/\D/', '', $row[1]);
                $impressions = (int) preg_replace('/\D/', '', $row[2]);
                $position = (float) str_replace(',', '.', trim($row[4]));

                $key = "{$country}|{$slug}";
                $entries[$key] ??= ['country' => $country, 'slug' => $slug, 'clicks' => 0, 'impressions' => 0, 'position_sum' => 0.0];
                $entries[$key]['clicks'] += $clicks;
                $entries[$key]['impressions'] += $impressions;
                $entries[$key]['position_sum'] += $position * max($impressions, 1);
            }

            fclose($handle);
        }

        return ['entries' => $entries, 'ignoredUrls' => $ignoredUrls, 'unknownCountries' => array_values($unknownCountries)];
    }

    private function countryName(string $countrySlug): ?string
    {
        if (! array_key_exists($countrySlug, $this->countryNames)) {
            $this->countryNames[$countrySlug] = PropertySlugHelper::validateCountry($countrySlug);
        }

        return $this->countryNames[$countrySlug];
    }

    /**
     * @param  list<string>  $keys  "país|slug"
     * @return array<string, SearchQuery>
     */
    private function loadRecords(array $keys): array
    {
        $records = [];

        collect($keys)
            ->map(fn (string $key): array => explode('|', $key, 2))
            ->groupBy(0)
            ->each(function (Collection $pairs, string $country) use (&$records): void {
                $pairs->pluck(1)->chunk(500)->each(function (Collection $slugs) use ($country, &$records): void {
                    SearchQuery::query()
                        ->where('country', $country)
                        ->whereIn('slug', $slugs->values()->all())
                        ->get()
                        ->each(function (SearchQuery $search) use (&$records): void {
                            $records["{$search->country}|{$search->slug}"] = $search;
                        });
                });
            });

        return $records;
    }

    /**
     * Las variantes que redirigen (301) a otra búsqueda le pasan sus métricas a la principal.
     *
     * @param  array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>  $entries
     * @param  array<string, SearchQuery>  $records
     * @return array{0: array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>, 1: int}
     */
    private function mergeRedirectVariants(array $entries, array $records): array
    {
        $redirected = 0;

        foreach ($entries as $key => $entry) {
            if (! isset($records[$key]) || ! $records[$key]->redirect_to_id) {
                continue;
            }

            $targetSlug = SearchQuery::redirectSlugFor($entry['country'], $entry['slug']);

            if ($targetSlug === null) {
                continue;
            }

            $targetKey = "{$entry['country']}|{$targetSlug}";
            $entries[$targetKey] ??= ['country' => $entry['country'], 'slug' => $targetSlug, 'clicks' => 0, 'impressions' => 0, 'position_sum' => 0.0];
            $entries[$targetKey]['clicks'] += $entry['clicks'];
            $entries[$targetKey]['impressions'] += $entry['impressions'];
            $entries[$targetKey]['position_sum'] += $entry['position_sum'];

            unset($entries[$key]);
            $redirected++;
        }

        return [$entries, $redirected];
    }

    /**
     * @param  array{clicks: int, impressions: int, position_sum: float}  $entry
     */
    private function position(array $entry): float
    {
        return round($entry['position_sum'] / max($entry['impressions'], 1), 2);
    }

    /**
     * @param  array{clicks: int, impressions: int, position_sum: float}  $entry
     */
    private function qualifies(array $entry): bool
    {
        if ($entry['clicks'] >= (int) $this->option('min-clicks')) {
            return true;
        }

        $minImpressions = (int) $this->option('min-impressions');
        $position = $this->position($entry);

        return $minImpressions > 0
            && $entry['impressions'] >= $minImpressions
            && $position >= (float) $this->option('min-position')
            && $position <= (float) $this->option('max-position');
    }

    /**
     * @param  array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>  $entries
     * @param  array<string, SearchQuery>  $records
     * @return array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}
     */
    private function classify(array $entries, array $records): array
    {
        $classified = ['active' => [], 'activate' => [], 'create' => [], 'createVariant' => [], 'metrics' => [], 'skip' => []];

        foreach ($entries as $key => $entry) {
            $record = $records[$key] ?? null;
            $group = match (true) {
                ! $this->qualifies($entry) => $record ? 'metrics' : 'skip',
                $record === null => 'create',
                $record->active => 'active',
                default => 'activate',
            };

            $classified[$group][] = $key;
        }

        return $classified;
    }

    /**
     * Las variantes que van a redirigir no se activan: las existentes solo actualizan métricas
     * y las que no estaban guardadas se crean inactivas (para que su URL responda 301).
     *
     * @param  array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}  $classified
     * @param  Collection<int, array{key: string, status: string}>  $variants
     * @return array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}
     */
    private function excludeVariants(array $classified, Collection $variants): array
    {
        $variantKeys = $variants->pluck('key')->all();

        foreach (['activate' => 'metrics', 'create' => 'createVariant'] as $from => $to) {
            $moved = array_values(array_intersect($classified[$from], $variantKeys));
            $classified[$from] = array_values(array_diff($classified[$from], $moved));
            $classified[$to] = [...$classified[$to], ...$moved];
        }

        return $classified;
    }

    /**
     * @param  array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>  $entries
     * @param  array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}  $classified
     * @param  Collection<int, array{key: string, country: string, clicks: int, status: string}>  $variants
     * @param  list<string>  $unknownCountries
     */
    private function printSummary(array $entries, array $classified, Collection $variants, int $redirected, int $ignoredUrls, array $unknownCountries): void
    {
        $clicks = fn (array $keys): int => array_sum(array_map(fn (string $key): int => $entries[$key]['clicks'], $keys));

        $this->info('Criterio: ' . sprintf(
            '%d+ clics, o %s',
            (int) $this->option('min-clicks'),
            (int) $this->option('min-impressions') > 0
                ? sprintf('%d+ impresiones con posición %s–%s', (int) $this->option('min-impressions'), $this->option('min-position'), $this->option('max-position'))
                : 'nada más (criterio de impresiones desactivado)',
        ));

        $this->table(['', 'Búsquedas', 'Clics'], [
            ['Ya activas (solo métricas)', count($classified['active']), $clicks($classified['active'])],
            ['A activar (existen inactivas)', count($classified['activate']), $clicks($classified['activate'])],
            ['A crear activas (no estaban guardadas)', count($classified['create']), $clicks($classified['create'])],
            ['No califican, existen (solo métricas)', count($classified['metrics']), $clicks($classified['metrics'])],
            ['No califican, no existen (se ignoran)', count($classified['skip']), $clicks($classified['skip'])],
            ...($variants->isNotEmpty() ? [['Casi iguales que pasan a redirigir (301), no se activan', $variants->count(), $variants->sum('clicks')]] : []),
        ]);

        $this->line(sprintf('Búsquedas distintas en el archivo: %d (versiones es/en sumadas).', count($entries)));

        if ($redirected > 0) {
            $this->line("Variantes redirigidas que sumaron sus métricas a la principal: {$redirected}.");
        }

        if ($ignoredUrls > 0) {
            $this->warn("URLs ignoradas (no son búsquedas indexables): {$ignoredUrls}.");
        }

        if ($unknownCountries !== []) {
            $this->warn('Países no habilitados o desconocidos (ignorados): ' . implode(', ', $unknownCountries) . '.');
        }

        foreach (collect($entries)->pluck('country')->unique() as $country) {
            $total = SearchQuery::where('country', $country)->count();
            $active = SearchQuery::where('country', $country)->where('active', true)->count();
            $added = collect([...$classified['activate'], ...$classified['create']])
                ->filter(fn (string $key): bool => str_starts_with($key, "{$country}|"))
                ->count();
            $created = collect([...$classified['create'], ...$classified['createVariant']])
                ->filter(fn (string $key): bool => str_starts_with($key, "{$country}|"))
                ->count();
            $activeVariants = $variants->where('country', $country)->where('status', 'activa')->count();
            $indexable = $active + $added - $activeVariants;

            $this->line(sprintf(
                '%s: activas %d → %d de %d guardadas. Con noindex quedarían %d sin indexar (inactivas o redirigidas).',
                $country,
                $active,
                $indexable,
                $total + $created,
                $total + $created - $indexable,
            ));
        }
    }

    /**
     * Grupos de búsquedas que quedarían activas con las mismas palabras (en cualquier orden,
     * sin stopwords ni plurales). Cada grupo viene ordenado: primero la principal (más clics,
     * luego más impresiones, ya activa antes que nueva, slug más corto).
     *
     * @param  array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>  $entries
     * @param  array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}  $classified
     * @return Collection<int, Collection<int, array{key: string, country: string, slug: string, clicks: int, impressions: int, status: string, rank: int}>>
     */
    private function nearDuplicateGroups(array $entries, array $classified): Collection
    {
        $candidates = collect();

        foreach (collect($entries)->pluck('country')->unique() as $country) {
            SearchQuery::query()
                ->where('country', $country)
                ->where('active', true)
                ->whereNull('redirect_to_id')
                ->get(['id', 'country', 'slug', 'gsc_clicks', 'gsc_impressions'])
                ->each(function (SearchQuery $search) use ($candidates, $entries): void {
                    $key = "{$search->country}|{$search->slug}";
                    $candidates->put($key, [
                        'key' => $key,
                        'country' => $search->country,
                        'slug' => $search->slug,
                        'clicks' => $entries[$key]['clicks'] ?? $search->gsc_clicks ?? 0,
                        'impressions' => $entries[$key]['impressions'] ?? $search->gsc_impressions ?? 0,
                        'status' => 'activa',
                        'rank' => 0,
                    ]);
                });
        }

        foreach (['activate' => ['a activar', 1], 'create' => ['a crear', 2]] as $group => [$status, $rank]) {
            foreach ($classified[$group] as $key) {
                $candidates->put($key, [
                    'key' => $key,
                    'country' => $entries[$key]['country'],
                    'slug' => $entries[$key]['slug'],
                    'clicks' => $entries[$key]['clicks'],
                    'impressions' => $entries[$key]['impressions'],
                    'status' => $status,
                    'rank' => $rank,
                ]);
            }
        }

        return $candidates
            ->groupBy(fn (array $search): string => $search['country'] . '|' . SearchQuery::normalizedWords($search['slug'])->sort()->implode(' '))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->map(fn (Collection $group): Collection => $group
                ->sort(fn (array $a, array $b): int => [$b['clicks'], $b['impressions'], $a['rank'], strlen($a['slug'])]
                    <=> [$a['clicks'], $a['impressions'], $b['rank'], strlen($b['slug'])])
                ->values())
            ->sortByDesc(fn (Collection $group): int => $group->sum('clicks'))
            ->values();
    }

    /**
     * @param  Collection<int, Collection<int, array{key: string, country: string, slug: string, clicks: int, impressions: int, status: string, rank: int}>>  $groups
     */
    private function printNearDuplicateGroups(Collection $groups, bool $redirectDuplicates): void
    {
        $this->newLine();

        if ($groups->isEmpty()) {
            $this->line('No se encontraron búsquedas casi iguales entre las que quedarían activas.');

            return;
        }

        $limit = max(0, (int) $this->option('groups'));
        $this->info(sprintf(
            $redirectDuplicates
                ? 'Búsquedas casi iguales: %d grupos (se muestran %d). Las marcadas con → redirigirán (301) a la principal (●):'
                : 'Búsquedas casi iguales entre las que quedarían activas: %d grupos (se muestran %d). Unificar con --redirect-duplicates o con "Redirigir a" en el admin:',
            $groups->count(),
            min($limit, $groups->count()),
        ));

        $rows = [];

        foreach ($groups->take($limit) as $group) {
            foreach ($group as $index => $search) {
                $rows[] = [$index === 0 ? '●' : ($redirectDuplicates ? '→' : ''), $search['slug'], $search['clicks'], $search['impressions'], $search['status']];
            }
        }

        $this->table(['', 'Slug', 'Clics', 'Impresiones', 'Estado'], $rows);
    }

    /**
     * @param  array<string, array{country: string, slug: string, clicks: int, impressions: int, position_sum: float}>  $entries
     * @param  array<string, SearchQuery>  $records
     * @param  array{active: list<string>, activate: list<string>, create: list<string>, createVariant: list<string>, metrics: list<string>, skip: list<string>}  $classified
     * @param  Collection<int, Collection<int, array{key: string, country: string, slug: string}>>  $groupsToRedirect
     * @return int Variantes redirigidas
     */
    private function apply(array $entries, array $records, array $classified, Collection $groupsToRedirect): int
    {
        $importedAt = now();
        $metrics = fn (array $entry): array => [
            'gsc_clicks' => $entry['clicks'],
            'gsc_impressions' => $entry['impressions'],
            'gsc_position' => $this->position($entry),
            'gsc_imported_at' => $importedAt,
        ];

        return DB::transaction(function () use ($entries, $records, $classified, $metrics, $groupsToRedirect): int {
            foreach ([...$classified['active'], ...$classified['metrics']] as $key) {
                $records[$key]->update($metrics($entries[$key]));
            }

            foreach ($classified['activate'] as $key) {
                $records[$key]->update(['active' => true, ...$metrics($entries[$key])]);
            }

            foreach (['create' => true, 'createVariant' => false] as $group => $active) {
                foreach ($classified[$group] as $key) {
                    $entry = $entries[$key];

                    SearchQuery::create([
                        'country' => $entry['country'],
                        'slug' => Str::limit($entry['slug'], 255, ''),
                        'query' => Str::limit(str_replace('-', ' ', $entry['slug']), 255, ''),
                        'results_count' => 0,
                        'active' => $active,
                        ...$metrics($entry),
                    ]);
                }
            }

            $redirected = 0;

            foreach ($groupsToRedirect as $group) {
                $principal = $this->findSearch($group->first());

                foreach ($group->slice(1) as $variant) {
                    $search = $this->findSearch($variant);

                    if ($principal && $search && $search->id !== $principal->id) {
                        $search->update(['redirect_to_id' => $principal->id]);
                        $redirected++;
                    }
                }
            }

            return $redirected;
        });
    }

    /**
     * @param  array{country: string, slug: string}  $search
     */
    private function findSearch(array $search): ?SearchQuery
    {
        return SearchQuery::where('country', $search['country'])->where('slug', $search['slug'])->first();
    }
}
