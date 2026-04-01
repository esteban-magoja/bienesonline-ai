<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\CountrySetting;
use App\Models\PropertyType;
use App\Models\TransactionType;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Nnjeim\World\Models\Country;

class CountryTypes extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'phosphor-globe-duotone';

    protected static string|UnitEnum|null $navigationGroup = 'Inmobiliario';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Configurar por País';

    protected string $view = 'filament.pages.country-types';

    // Country selection
    public ?string $selectedCountry = null;

    // Existing types loaded from DB
    public array $propertyTypes = [];
    public array $transactionTypes = [];

    // Form state: new property type
    public string $newPtValue = '';
    public string $newPtLabel = '';
    public string $newPtLabelPlural = '';
    public string $newPtValueEn = '';
    public int $newPtOrder = 99;
    public bool $showAddPtForm = false;

    // Form state: new transaction type
    public string $newTtValue = '';
    public string $newTtLabel = '';
    public string $newTtValueEn = '';
    public int $newTtOrder = 99;
    public bool $showAddTtForm = false;

    public array $countries = [];

    // ── Country Enable/Disable ────────────────────────────────────
    public string $countrySearch = '';
    public bool $showCountryPanel = false;
    public array $enabledCountries = [];   // [iso2 => display_order]
    public array $allCountries = [];       // [iso2 => name] for the full picker
    public array $filteredCountries = [];  // updated on countrySearch change

    public array $propertyValueEnOptions = [
        'house'      => 'house — Casa',
        'apartment'  => 'apartment — Departamento / Piso / Apartamento',
        'townhouse'  => 'townhouse — PH / Casa adosada',
        'condo'      => 'condo — Condominio',
        'penthouse'  => 'penthouse — Ático / Penthouse',
        'villa'      => 'villa — Chalet / Villa',
        'commercial' => 'commercial — Local Comercial',
        'office'     => 'office — Oficina',
        'land'       => 'land — Terreno / Lote',
        'farm'       => 'farm — Campo / Finca / Rancho',
        'warehouse'  => 'warehouse — Galpón / Bodega / Nave',
        'parking'    => 'parking — Cochera / Estacionamiento / Garaje',
    ];

    public array $transactionValueEnOptions = [
        'sale'           => 'sale — Venta',
        'rent'           => 'rent — Alquiler / Arriendo / Renta',
        'temporary_rent' => 'temporary_rent — Alquiler Temporal / Vacacional',
    ];

    public function mount(): void
    {
        $this->countries = Country::orderBy('name')->get()
            ->mapWithKeys(fn($c) => [$c->iso2 => $c->iso2 . ' — ' . $c->name])
            ->prepend('INTL — Internacional (Fallback)', 'INTL')
            ->toArray();

        $this->allCountries = Country::orderBy('name')->get()
            ->mapWithKeys(fn($c) => [$c->iso2 => $c->iso2 . ' — ' . $c->name])
            ->toArray();

        $this->filteredCountries = $this->allCountries;
        $this->loadEnabledCountries();
    }

    private function loadEnabledCountries(): void
    {
        $this->enabledCountries = CountrySetting::where('is_enabled', true)
            ->orderBy('display_order')
            ->pluck('display_order', 'iso2')
            ->toArray();
    }

    public function updatedSelectedCountry(): void
    {
        $this->loadTypes();
        $this->showAddPtForm = false;
        $this->showAddTtForm = false;
        $this->resetNewForms();
    }

    private function loadTypes(): void
    {
        if (!$this->selectedCountry) {
            $this->propertyTypes = [];
            $this->transactionTypes = [];
            return;
        }

        $this->propertyTypes = PropertyType::where('country_code', $this->selectedCountry)
            ->orderBy('order')
            ->orderBy('label')
            ->get()
            ->toArray();

        $this->transactionTypes = TransactionType::where('country_code', $this->selectedCountry)
            ->orderBy('order')
            ->orderBy('label')
            ->get()
            ->toArray();
    }

    // ── Property Types ────────────────────────────────────────────

    public function addPropertyType(): void
    {
        $this->validate([
            'newPtValue'       => 'required|string|max:50',
            'newPtLabel'       => 'required|string|max:100',
            'newPtLabelPlural' => 'nullable|string|max:100',
            'newPtValueEn'     => 'required|string',
            'newPtOrder'       => 'required|integer|min:1',
        ]);

        $exists = PropertyType::where('country_code', $this->selectedCountry)
            ->where('value', $this->newPtValue)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Ya existe un tipo con ese valor para este país.')
                ->warning()
                ->send();
            return;
        }

        PropertyType::create([
            'country_code' => $this->selectedCountry,
            'value'        => $this->newPtValue,
            'label'        => $this->newPtLabel,
            'label_plural' => $this->newPtLabelPlural ?: ($this->newPtLabel . 's'),
            'value_en'     => $this->newPtValueEn,
            'order'        => $this->newPtOrder,
            'is_active'    => true,
        ]);

        PropertyType::clearCache($this->selectedCountry);
        $this->showAddPtForm = false;
        $this->resetNewForms();
        $this->loadTypes();

        Notification::make()->title('Tipo de inmueble agregado.')->success()->send();
    }

    public function updateLabelPlural(int $id, string $plural): void
    {
        $type = PropertyType::findOrFail($id);
        $type->label_plural = $plural;
        $type->save();
        PropertyType::clearCache($this->selectedCountry);
        $this->loadTypes();

        Notification::make()->title('Plural actualizado.')->success()->send();
    }

    public function togglePropertyType(int $id): void
    {
        $type = PropertyType::findOrFail($id);
        $type->is_active = !$type->is_active;
        $type->save();
        PropertyType::clearCache($this->selectedCountry);
        $this->loadTypes();
    }

    public function removePropertyType(int $id): void
    {
        PropertyType::findOrFail($id)->delete();
        PropertyType::clearCache($this->selectedCountry);
        $this->loadTypes();

        Notification::make()->title('Tipo de inmueble eliminado.')->success()->send();
    }

    // ── Transaction Types ─────────────────────────────────────────

    public function addTransactionType(): void
    {
        $this->validate([
            'newTtValue'   => 'required|string|max:50',
            'newTtLabel'   => 'required|string|max:100',
            'newTtValueEn' => 'required|string',
            'newTtOrder'   => 'required|integer|min:1',
        ]);

        $exists = TransactionType::where('country_code', $this->selectedCountry)
            ->where('value', $this->newTtValue)
            ->exists();

        if ($exists) {
            Notification::make()
                ->title('Ya existe un tipo con ese valor para este país.')
                ->warning()
                ->send();
            return;
        }

        TransactionType::create([
            'country_code' => $this->selectedCountry,
            'value'        => $this->newTtValue,
            'label'        => $this->newTtLabel,
            'value_en'     => $this->newTtValueEn,
            'order'        => $this->newTtOrder,
            'is_active'    => true,
        ]);

        TransactionType::clearCache($this->selectedCountry);
        $this->showAddTtForm = false;
        $this->resetNewForms();
        $this->loadTypes();

        Notification::make()->title('Tipo de operación agregado.')->success()->send();
    }

    public function toggleTransactionType(int $id): void
    {
        $type = TransactionType::findOrFail($id);
        $type->is_active = !$type->is_active;
        $type->save();
        TransactionType::clearCache($this->selectedCountry);
        $this->loadTypes();
    }

    public function removeTransactionType(int $id): void
    {
        TransactionType::findOrFail($id)->delete();
        TransactionType::clearCache($this->selectedCountry);
        $this->loadTypes();

        Notification::make()->title('Tipo de operación eliminado.')->success()->send();
    }

    // ── Copy from INTL ────────────────────────────────────────────

    public function copyFromIntl(): void
    {
        if (!$this->selectedCountry || $this->selectedCountry === 'INTL') return;

        $intlPropertyTypes = PropertyType::where('country_code', 'INTL')->get();
        foreach ($intlPropertyTypes as $type) {
            PropertyType::firstOrCreate(
                ['country_code' => $this->selectedCountry, 'value' => $type->value],
                ['label' => $type->label, 'value_en' => $type->value_en, 'order' => $type->order, 'is_active' => true]
            );
        }

        $intlTransactionTypes = TransactionType::where('country_code', 'INTL')->get();
        foreach ($intlTransactionTypes as $type) {
            TransactionType::firstOrCreate(
                ['country_code' => $this->selectedCountry, 'value' => $type->value],
                ['label' => $type->label, 'value_en' => $type->value_en, 'order' => $type->order, 'is_active' => true]
            );
        }

        PropertyType::clearCache($this->selectedCountry);
        TransactionType::clearCache($this->selectedCountry);
        $this->loadTypes();

        Notification::make()->title('Tipos copiados desde INTL. Ahora podés personalizar cada uno.')->success()->send();
    }

    // ── Country Enable/Disable ────────────────────────────────────

    public function enableCountry(string $iso2): void
    {
        $order = count($this->enabledCountries) + 1;
        CountrySetting::enable($iso2, $order);
        $this->loadEnabledCountries();

        Notification::make()->title("País {$iso2} habilitado.")->success()->send();
    }

    public function disableCountry(string $iso2): void
    {
        CountrySetting::disable($iso2);
        $this->loadEnabledCountries();

        Notification::make()->title("País {$iso2} deshabilitado.")->success()->send();
    }

    public function moveCountryUp(string $iso2): void
    {
        $this->reorderCountry($iso2, -1);
    }

    public function moveCountryDown(string $iso2): void
    {
        $this->reorderCountry($iso2, +1);
    }

    private function reorderCountry(string $iso2, int $delta): void
    {
        $settings = CountrySetting::where('is_enabled', true)
            ->orderBy('display_order')
            ->get();

        $index = $settings->search(fn($s) => $s->iso2 === $iso2);
        if ($index === false) return;

        $swapIndex = $index + $delta;
        if ($swapIndex < 0 || $swapIndex >= $settings->count()) return;

        $current = $settings[$index];
        $swap    = $settings[$swapIndex];

        [$current->display_order, $swap->display_order] = [$swap->display_order, $current->display_order];
        $current->save();
        $swap->save();

        CountrySetting::clearCache();
        $this->loadEnabledCountries();
    }

    public function updatedCountrySearch(): void
    {
        if (!$this->countrySearch) {
            $this->filteredCountries = $this->allCountries;
            return;
        }

        $q = mb_strtolower($this->countrySearch);
        $this->filteredCountries = array_filter(
            $this->allCountries,
            fn($name) => str_contains(mb_strtolower($name), $q)
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    public function getFilteredCountriesProperty(): array
    {
        return $this->filteredCountries;
    }

    private function resetNewForms(): void
    {
        $this->newPtValue = '';
        $this->newPtLabel = '';
        $this->newPtLabelPlural = '';
        $this->newPtValueEn = '';
        $this->newPtOrder = 99;
        $this->newTtValue = '';
        $this->newTtLabel = '';
        $this->newTtValueEn = '';
        $this->newTtOrder = 99;
    }
}
