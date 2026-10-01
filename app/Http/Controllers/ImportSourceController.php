<?php

namespace App\Http\Controllers;

use App\Models\CountrySetting;
use Closure;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Models\ImportSource;
use App\Services\Import\ImportSourceException;
use App\Services\Import\ImportSourceRegistry;
use App\Services\Import\ImportSourceRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sección "Importar anuncios": conexión y sincronización con fuentes externas (Wasi, etc.).
 * Independiente de la importación legacy (ImportController).
 */
class ImportSourceController extends Controller implements HasMiddleware
{
    /**
     * Sección reservada a usuarios premium: el índice muestra el aviso de suscripción
     * y las acciones que modifican datos redirigen a él.
     */
    public static function middleware(): array
    {
        return [
            function (Request $request, Closure $next) {
                if (! $request->user()?->hasPremiumAccess() && ! $request->routeIs('dashboard.imports.index')) {
                    return redirect()->route('dashboard.imports.index');
                }

                return $next($request);
            },
        ];
    }

    public function __construct(
        private readonly ImportSourceRegistry $registry,
        private readonly ImportSourceRunner $runner,
    ) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPremiumAccess()) {
            return view('theme::pages.dashboard.imports.index', ['canImport' => false]);
        }

        $sources = ImportSource::where('user_id', $request->user()->id)
            ->with(['importJobs' => fn ($q) => $q->latest('id')->limit(1)])
            ->withCount('listings')
            ->latest()
            ->get();

        return view('theme::pages.dashboard.imports.index', [
            'canImport' => true,
            'drivers'   => $this->registry->all(),
            'sources'   => $sources,
            'countries' => CountrySetting::getEnabledCountries(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['driver' => ['required', 'string', Rule::in(array_keys($this->registry->all()))]]);
        $driver = $this->registry->get($request->string('driver')->toString());

        $config = $request->validate($driver->rules());
        $config = array_intersect_key($config, $driver->fields());

        $enabledCountries = CountrySetting::getEnabledCountries()->pluck('name')->all();
        foreach ($driver->fields() as $name => $field) {
            if ($field['type'] === 'country' && ! in_array($config[$name] ?? null, $enabledCountries, true)) {
                return back()->withInput()->withErrors([$name => __('import.invalid_country')]);
            }
        }

        if (ImportSource::where('user_id', $user->id)->count() >= config('import.max_sources_per_user', 5)) {
            return back()->withInput()->withErrors(['driver' => __('import.max_sources_reached')]);
        }

        $isDuplicate = ImportSource::where('user_id', $user->id)
            ->where('driver', $driver->key())
            ->get()
            ->contains(fn (ImportSource $s) => $s->config === $config);

        if ($isDuplicate) {
            return back()->withInput()->withErrors(['driver' => __('import.already_connected')]);
        }

        // Validación previa: la URL debe responder con un feed legible antes de guardar la conexión
        try {
            $ads = $driver->fetch($config);
        } catch (ImportSourceException $e) {
            return back()->withInput()->withErrors(['feed_url' => $e->getMessage()]);
        }

        if ($ads === []) {
            return back()->withInput()->withErrors(['feed_url' => __('import.no_listings_in_feed')]);
        }

        $source = ImportSource::create([
            'user_id'             => $user->id,
            'driver'              => $driver->key(),
            'name'                => $driver->suggestName($config),
            'config'              => $config,
            'sync_interval_hours' => config('import.sync_interval_hours', 24),
        ]);

        $this->runner->start($source, 'initial');

        return redirect()->route('dashboard.imports.index')
            ->with('success', __('import.source_connected', ['count' => count($ads)]));
    }

    public function sync(Request $request, ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($request, $source);

        if ($source->status === ImportSource::STATUS_ERROR) {
            // Un sync manual es un reintento explícito del usuario
            $source->update(['status' => ImportSource::STATUS_ACTIVE, 'consecutive_failures' => 0]);
        }

        if (! $this->runner->start($source->refresh(), 'sync')) {
            return back()->with('error', __('import.already_running'));
        }

        return back()->with('success', __('import.sync_started'));
    }

    public function toggle(Request $request, ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($request, $source);

        $enabled = ! $source->sync_enabled;
        $source->update(['sync_enabled' => $enabled]);

        return back()->with('success', $enabled ? __('import.sync_enabled') : __('import.sync_paused'));
    }

    /**
     * Elimina la conexión. Los anuncios ya importados se conservan (quedan desvinculados).
     */
    public function destroy(Request $request, ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($request, $source);

        $source->delete();

        return redirect()->route('dashboard.imports.index')->with('success', __('import.source_deleted'));
    }

    private function authorizeSource(Request $request, ImportSource $source): void
    {
        abort_unless($source->user_id === $request->user()->id, 403);
    }
}
