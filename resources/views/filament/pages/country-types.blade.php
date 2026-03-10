<x-filament-panels::page>

    {{-- ── PAÍSES HABILITADOS ────────────────────────────────────── --}}
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                <x-phosphor-globe-duotone class="w-5 h-5 text-primary-500" />
                Países Habilitados
                <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 text-xs font-bold text-white bg-primary-500 rounded-full">
                    {{ count($enabledCountries) }}
                </span>
            </h3>
            <button wire:click="$toggle('showCountryPanel')"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white {{ $showCountryPanel ? 'bg-gray-500 hover:bg-gray-600' : 'bg-primary-600 hover:bg-primary-700' }} rounded-lg transition-colors">
                @if($showCountryPanel)
                    <x-phosphor-x-bold class="w-3.5 h-3.5" /> Cerrar
                @else
                    <x-phosphor-plus-bold class="w-3.5 h-3.5" /> Agregar país
                @endif
            </button>
        </div>

        {{-- Lista de países habilitados (ordenables) --}}
        @if(count($enabledCountries) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400 italic mb-3">
                No hay países habilitados aún. Los formularios no mostrarán ningún país.
            </p>
        @else
            <div class="space-y-1 mb-4">
                @php
                    $enabledList = \App\Models\CountrySetting::where('is_enabled', true)->orderBy('display_order')->get();
                    $worldCountries = $allCountries;
                @endphp
                @foreach($enabledList as $i => $cs)
                    <div class="flex items-center gap-3 py-1.5 px-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-400 w-5 text-center">{{ $i + 1 }}</span>
                        <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                            {{ $worldCountries[$cs->iso2] ?? $cs->iso2 }}
                        </span>
                        <div class="flex items-center gap-1">
                            <button wire:click="moveCountryUp('{{ $cs->iso2 }}')"
                                    class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-30"
                                    @if($i === 0) disabled @endif>
                                <x-phosphor-arrow-up-bold class="w-3.5 h-3.5" />
                            </button>
                            <button wire:click="moveCountryDown('{{ $cs->iso2 }}')"
                                    class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    @if($i === count($enabledCountries) - 1) disabled @endif>
                                <x-phosphor-arrow-down-bold class="w-3.5 h-3.5" />
                            </button>
                            <button wire:click="disableCountry('{{ $cs->iso2 }}')"
                                    wire:confirm="¿Deshabilitar {{ $cs->iso2 }}? Ya no aparecerá en los formularios públicos."
                                    class="p-1 text-red-400 hover:text-red-600">
                                <x-phosphor-x-circle-bold class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Panel para agregar países --}}
        @if($showCountryPanel)
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Buscá y habilitá un país:</p>
                <div class="flex gap-2 mb-3">
                    <input type="text"
                           wire:model.live="countrySearch"
                           placeholder="Buscar país..."
                           class="flex-1 text-sm border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white py-1.5 px-3">
                </div>
                <div class="max-h-60 overflow-y-auto space-y-0.5 rounded-lg border border-gray-200 dark:border-gray-700">
                    @foreach($filteredCountries as $iso2 => $name)
                        @php $isEnabled = isset($enabledCountries[$iso2]); @endphp
                        <div class="flex items-center justify-between px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <span class="text-sm {{ $isEnabled ? 'text-primary-600 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $name }}
                                @if($isEnabled)
                                    <x-phosphor-check-circle-fill class="inline w-3.5 h-3.5 text-primary-500 ml-1" />
                                @endif
                            </span>
                            @if(!$isEnabled)
                                <button wire:click="enableCountry('{{ $iso2 }}')"
                                        class="text-xs px-2 py-0.5 bg-primary-600 text-white rounded hover:bg-primary-700">
                                    Habilitar
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- Country Selector --}}
    <x-filament::section>
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Seleccioná un país para configurar
                </label>
                <select wire:model.live="selectedCountry"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm py-2 px-3">
                    <option value="">— Elegí un país —</option>
                    @foreach($countries as $iso2 => $name)
                        <option value="{{ $iso2 }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
            @if($selectedCountry && $selectedCountry !== 'INTL')
                @php $isAlreadyEnabled = isset($enabledCountries[$selectedCountry]); @endphp
                @if(!$isAlreadyEnabled)
                    <button wire:click="enableCountry('{{ $selectedCountry }}')"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap">
                        <x-phosphor-check-circle-duotone class="w-4 h-4" />
                        Habilitar país
                    </button>
                @else
                    <span class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:text-green-400 dark:border-green-800 whitespace-nowrap">
                        <x-phosphor-check-circle-fill class="w-4 h-4" />
                        País habilitado (#{{ array_search($selectedCountry, array_keys($enabledCountries)) + 1 }})
                    </span>
                @endif
                <button wire:click="copyFromIntl"
                        wire:confirm="¿Copiar todos los tipos de INTL a {{ $selectedCountry }}? Solo se agregan los que no existan."
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 transition-colors whitespace-nowrap">
                    <x-phosphor-copy-duotone class="w-4 h-4" />
                    Copiar desde INTL
                </button>
            @endif
            </div>
        </div>
    </x-filament::section>

    @if(!$selectedCountry)
        <x-filament::section>
            <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                <x-phosphor-globe-duotone class="w-12 h-12 mx-auto mb-3 opacity-40" />
                <p class="text-base font-medium">Seleccioná un país para ver y gestionar sus tipos.</p>
                <p class="text-sm mt-1">Los países sin configuración propia usan automáticamente los tipos de <strong>INTL</strong>.</p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- ── TIPOS DE INMUEBLE ─────────────────────────────── --}}
            <x-filament::section>
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                        <x-phosphor-buildings-duotone class="w-5 h-5 text-primary-500" />
                        Tipos de Inmueble
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 text-xs font-bold text-white bg-primary-500 rounded-full">
                            {{ count($propertyTypes) }}
                        </span>
                    </h3>
                    @if(!$showAddPtForm)
                        <button wire:click="$set('showAddPtForm', true)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <x-phosphor-plus-bold class="w-3.5 h-3.5" />
                            Agregar
                        </button>
                    @endif
                </div>

                {{-- List --}}
                @if(count($propertyTypes) === 0 && !$showAddPtForm)
                    <div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm border border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                        <x-phosphor-buildings-duotone class="w-8 h-8 mx-auto mb-2 opacity-40" />
                        <p>No hay tipos configurados.</p>
                        <p class="text-xs mt-1">Se usarán los tipos de <strong>INTL</strong> como fallback.</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700 -mx-6 border-t border-gray-100 dark:border-gray-700">
                        @foreach($propertyTypes as $pt)
                            <li class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- Toggle switch --}}
                                    <button wire:click="togglePropertyType({{ $pt['id'] }})"
                                            title="{{ $pt['is_active'] ? 'Desactivar' : 'Activar' }}"
                                            class="relative flex-shrink-0 inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none
                                                   {{ $pt['is_active'] ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                                                     {{ $pt['is_active'] ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                    </button>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white {{ !$pt['is_active'] ? 'opacity-40 line-through' : '' }}">
                                            {{ $pt['label'] }}
                                        </p>
                                        <p class="text-xs text-gray-400 flex items-center gap-1 flex-wrap">
                                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ $pt['value'] }}</code>
                                            <span>→</span>
                                            <code class="bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1 rounded">{{ $pt['value_en'] }}</code>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                    <span class="text-xs text-gray-300 dark:text-gray-600">#{{ $pt['order'] }}</span>
                                    <button wire:click="removePropertyType({{ $pt['id'] }})"
                                            wire:confirm="¿Eliminar '{{ $pt['label'] }}'?"
                                            class="p-1.5 text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                        <x-phosphor-trash-bold class="w-4 h-4" />
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Add form --}}
                @if($showAddPtForm)
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-primary-300 dark:border-primary-700 space-y-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nuevo tipo de inmueble</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Valor/slug <span class="text-red-500">*</span>
                                    <span class="font-normal text-gray-400">(sin espacios)</span>
                                </label>
                                <input wire:model="newPtValue" type="text" placeholder="ej: departamento"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                @error('newPtValue') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Etiqueta visible <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="newPtLabel" type="text" placeholder="ej: Departamento"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                @error('newPtLabel') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Equivalente EN <span class="text-red-500">*</span>
                                    <span class="font-normal text-gray-400">(para matching)</span>
                                </label>
                                <select wire:model="newPtValueEn"
                                        class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">— Seleccioná —</option>
                                    @foreach($propertyValueEnOptions as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                @error('newPtValueEn') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Orden</label>
                                <input wire:model="newPtOrder" type="number" min="1"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button wire:click="addPropertyType"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                <x-phosphor-check-bold class="w-4 h-4" />
                                Guardar
                            </button>
                            <button wire:click="$set('showAddPtForm', false)"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                @endif
            </x-filament::section>

            {{-- ── TIPOS DE OPERACIÓN ────────────────────────────── --}}
            <x-filament::section>
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                        <x-phosphor-arrows-left-right-duotone class="w-5 h-5 text-primary-500" />
                        Tipos de Operación
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 text-xs font-bold text-white bg-primary-500 rounded-full">
                            {{ count($transactionTypes) }}
                        </span>
                    </h3>
                    @if(!$showAddTtForm)
                        <button wire:click="$set('showAddTtForm', true)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <x-phosphor-plus-bold class="w-3.5 h-3.5" />
                            Agregar
                        </button>
                    @endif
                </div>

                {{-- List --}}
                @if(count($transactionTypes) === 0 && !$showAddTtForm)
                    <div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm border border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                        <x-phosphor-arrows-left-right-duotone class="w-8 h-8 mx-auto mb-2 opacity-40" />
                        <p>No hay tipos configurados.</p>
                        <p class="text-xs mt-1">Se usarán los tipos de <strong>INTL</strong> como fallback.</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700 -mx-6 border-t border-gray-100 dark:border-gray-700">
                        @foreach($transactionTypes as $tt)
                            <li class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <button wire:click="toggleTransactionType({{ $tt['id'] }})"
                                            title="{{ $tt['is_active'] ? 'Desactivar' : 'Activar' }}"
                                            class="relative flex-shrink-0 inline-flex h-5 w-9 items-center rounded-full transition-colors focus:outline-none
                                                   {{ $tt['is_active'] ? 'bg-primary-500' : 'bg-gray-300 dark:bg-gray-600' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform
                                                     {{ $tt['is_active'] ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                    </button>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white {{ !$tt['is_active'] ? 'opacity-40 line-through' : '' }}">
                                            {{ $tt['label'] }}
                                        </p>
                                        <p class="text-xs text-gray-400 flex items-center gap-1 flex-wrap">
                                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">{{ $tt['value'] }}</code>
                                            <span>→</span>
                                            <code class="bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 px-1 rounded">{{ $tt['value_en'] }}</code>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                    <span class="text-xs text-gray-300 dark:text-gray-600">#{{ $tt['order'] }}</span>
                                    <button wire:click="removeTransactionType({{ $tt['id'] }})"
                                            wire:confirm="¿Eliminar '{{ $tt['label'] }}'?"
                                            class="p-1.5 text-gray-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors">
                                        <x-phosphor-trash-bold class="w-4 h-4" />
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Add form --}}
                @if($showAddTtForm)
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-primary-300 dark:border-primary-700 space-y-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nuevo tipo de operación</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Valor/slug <span class="text-red-500">*</span>
                                    <span class="font-normal text-gray-400">(sin espacios)</span>
                                </label>
                                <input wire:model="newTtValue" type="text" placeholder="ej: arriendo"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                @error('newTtValue') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Etiqueta visible <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="newTtLabel" type="text" placeholder="ej: Arriendo"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                @error('newTtLabel') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Equivalente EN <span class="text-red-500">*</span>
                                    <span class="font-normal text-gray-400">(para matching)</span>
                                </label>
                                <select wire:model="newTtValueEn"
                                        class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">— Seleccioná —</option>
                                    @foreach($transactionValueEnOptions as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                                @error('newTtValueEn') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Orden</label>
                                <input wire:model="newTtOrder" type="number" min="1"
                                       class="w-full text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button wire:click="addTransactionType"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                <x-phosphor-check-bold class="w-4 h-4" />
                                Guardar
                            </button>
                            <button wire:click="$set('showAddTtForm', false)"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                @endif
            </x-filament::section>

        </div>

        {{-- Info note --}}
        <x-filament::section>
            <div class="flex gap-3 text-sm text-gray-500 dark:text-gray-400">
                <x-phosphor-info-duotone class="w-5 h-5 flex-shrink-0 text-blue-400 mt-0.5" />
                <div class="space-y-1">
                    <p>El <strong>toggle</strong> activa/desactiva un tipo sin eliminarlo del sistema.</p>
                    <p>El <strong>Equivalente EN</strong> es la clave de matching: permite que "alquiler" (AR), "arriendo" (CL) y "renta" (MX) se consideren equivalentes porque todos apuntan a <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">rent</code>.</p>
                    <p><strong>Copiar desde INTL</strong> carga los tipos genéricos como punto de partida para países nuevos (no sobreescribe los ya existentes).</p>
                </div>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
