<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Nnjeim\World\Models\City;

class GeoManager extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'phosphor-tree-structure-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Geografía';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Gestor Geográfico';

    protected string $view = 'filament.pages.geo-manager';

    // ── Country ───────────────────────────────────────────────────
    public ?string $selectedCountry = null;
    public array $countries = [];

    // Inline edit country
    public bool $editingCountry = false;
    public string $editCountryName = '';

    // ── States ────────────────────────────────────────────────────
    public array $states = [];
    public string $stateSearch = '';
    public ?int $selectedStateId = null;
    public string $selectedStateName = '';

    // Add state form
    public string $newStateName = '';

    // Inline edit state
    public ?int $editingStateId = null;
    public string $editStateName = '';

    // ── Cities ────────────────────────────────────────────────────
    public array $cities = [];
    public string $citySearch = '';

    // Add city form
    public string $newCityName = '';

    // Inline edit city
    public ?int $editingCityId = null;
    public string $editCityName = '';

    // ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->countries = Country::orderBy('name')
            ->get()
            ->mapWithKeys(fn($c) => [$c->iso2 => $c->iso2 . ' — ' . $c->name])
            ->toArray();
    }

    // ── Country selection ─────────────────────────────────────────

    public function updatedSelectedCountry(): void
    {
        $this->selectedStateId = null;
        $this->selectedStateName = '';
        $this->stateSearch = '';
        $this->citySearch = '';
        $this->cities = [];
        $this->editingStateId = null;
        $this->editingCityId = null;
        $this->editingCountry = false;
        $this->loadStates();
    }

    private function loadStates(): void
    {
        if (!$this->selectedCountry) {
            $this->states = [];
            return;
        }

        $query = State::where('country_code', $this->selectedCountry)->orderBy('name');

        if ($this->stateSearch) {
            $query->where('name', 'ilike', '%' . $this->stateSearch . '%');
        }

        $this->states = $query->get()->toArray();
    }

    public function updatedStateSearch(): void
    {
        $this->loadStates();
    }

    // ── Edit Country ──────────────────────────────────────────────

    public function startEditCountry(): void
    {
        $country = Country::where('iso2', $this->selectedCountry)->firstOrFail();
        $this->editCountryName = $country->name;
        $this->editingCountry = true;
    }

    public function saveCountry(): void
    {
        $this->validate([
            'editCountryName' => 'required|string|max:255',
        ]);

        $country = Country::where('iso2', $this->selectedCountry)->firstOrFail();
        $country->update(['name' => $this->editCountryName]);

        // Refresh dropdown
        $this->countries = Country::orderBy('name')
            ->get()
            ->mapWithKeys(fn($c) => [$c->iso2 => $c->iso2 . ' — ' . $c->name])
            ->toArray();

        $this->editingCountry = false;

        Notification::make()->title('País actualizado correctamente.')->success()->send();
    }

    public function cancelEditCountry(): void
    {
        $this->editingCountry = false;
    }

    // ── State selection ───────────────────────────────────────────

    public function selectState(int $id, string $name): void
    {
        $this->selectedStateId = $id;
        $this->selectedStateName = $name;
        $this->citySearch = '';
        $this->editingCityId = null;
        $this->loadCities();
    }

    private function loadCities(): void
    {
        if (!$this->selectedStateId) {
            $this->cities = [];
            return;
        }

        $query = City::where('state_id', $this->selectedStateId)->orderBy('name');

        if ($this->citySearch) {
            $query->where('name', 'ilike', '%' . $this->citySearch . '%');
        }

        $this->cities = $query->get()->toArray();
    }

    public function updatedCitySearch(): void
    {
        $this->loadCities();
    }

    // ── Add State ─────────────────────────────────────────────────

    public function addState(): void
    {
        $this->validate([
            'newStateName' => 'required|string|max:255',
        ]);

        $country = Country::where('iso2', $this->selectedCountry)->firstOrFail();

        State::create([
            'country_id'   => $country->id,
            'country_code' => $this->selectedCountry,
            'name'         => $this->newStateName,
        ]);

        $this->newStateName = '';
        $this->loadStates();

        Notification::make()->title('Estado / Provincia creado correctamente.')->success()->send();
    }

    // ── Edit State ────────────────────────────────────────────────

    public function startEditState(int $id): void
    {
        $state = State::findOrFail($id);
        $this->editingStateId = $id;
        $this->editStateName = $state->name;
    }

    public function saveState(): void
    {
        $this->validate([
            'editStateName' => 'required|string|max:255',
        ]);

        $state = State::findOrFail($this->editingStateId);
        $state->update([
            'name' => $this->editStateName,
        ]);

        // Update selected state name if it was this one
        if ($this->selectedStateId === $this->editingStateId) {
            $this->selectedStateName = $this->editStateName;
        }

        $this->editingStateId = null;
        $this->loadStates();

        Notification::make()->title('Estado / Provincia actualizado.')->success()->send();
    }

    public function cancelEditState(): void
    {
        $this->editingStateId = null;
    }

    // ── Delete State ──────────────────────────────────────────────

    public function deleteState(int $id): void
    {
        $state = State::findOrFail($id);
        $cityCount = City::where('state_id', $id)->count();

        if ($cityCount > 0) {
            Notification::make()
                ->title("No se puede eliminar: tiene {$cityCount} ciudad(es) asociada(s). Eliminá las ciudades primero.")
                ->warning()
                ->send();
            return;
        }

        $state->delete();

        if ($this->selectedStateId === $id) {
            $this->selectedStateId = null;
            $this->selectedStateName = '';
            $this->cities = [];
        }

        $this->loadStates();
        Notification::make()->title('Estado / Provincia eliminado.')->success()->send();
    }

    // ── Add City ──────────────────────────────────────────────────

    public function addCity(): void
    {
        $this->validate([
            'newCityName' => 'required|string|max:255',
        ]);

        $country = Country::where('iso2', $this->selectedCountry)->firstOrFail();

        City::create([
            'country_id'   => $country->id,
            'country_code' => $this->selectedCountry,
            'state_id'     => $this->selectedStateId,
            'name'         => $this->newCityName,
        ]);

        $this->newCityName = '';
        $this->loadCities();

        Notification::make()->title('Ciudad / Localidad creada correctamente.')->success()->send();
    }

    // ── Edit City ─────────────────────────────────────────────────

    public function startEditCity(int $id): void
    {
        $city = City::findOrFail($id);
        $this->editingCityId = $id;
        $this->editCityName = $city->name;
    }

    public function saveCity(): void
    {
        $this->validate([
            'editCityName' => 'required|string|max:255',
        ]);

        $city = City::findOrFail($this->editingCityId);
        $city->update([
            'name' => $this->editCityName,
        ]);

        $this->editingCityId = null;
        $this->loadCities();

        Notification::make()->title('Ciudad / Localidad actualizada.')->success()->send();
    }

    public function cancelEditCity(): void
    {
        $this->editingCityId = null;
    }

    // ── Delete City ───────────────────────────────────────────────

    public function deleteCity(int $id): void
    {
        City::findOrFail($id)->delete();
        $this->loadCities();
        Notification::make()->title('Ciudad / Localidad eliminada.')->success()->send();
    }
}
