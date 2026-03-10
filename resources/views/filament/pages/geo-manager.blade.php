<x-filament-panels::page>

    {{-- ── PASO 1: SELECTOR DE PAÍS ──────────────────────────────── --}}
    <x-filament::section>
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-2 shrink-0">
                <x-phosphor-globe-duotone class="w-5 h-5 text-primary-500" />
                <span class="font-semibold text-gray-900 dark:text-white">País</span>
            </div>
            <div class="flex-1 max-w-sm">
                <select wire:model.live="selectedCountry"
                        class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500 py-2 px-3">
                    <option value="">— Seleccioná un país —</option>
                    @foreach($countries as $iso2 => $label)
                        <option value="{{ $iso2 }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($selectedCountry)
                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded-full">
                    <x-phosphor-map-pin-fill class="w-3 h-3" />
                    {{ count($states) }} estado(s)
                    @if($selectedStateId)
                        · {{ count($cities) }} ciudad(es) en {{ $selectedStateName }}
                    @endif
                </span>
            @endif
        </div>
    </x-filament::section>

    @if($selectedCountry)

    {{-- ── PASO 2 + 3: ESTADOS Y CIUDADES LADO A LADO ─────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- ── PANEL ESTADOS ──────────────────────────────────────── --}}
        <x-filament::section>
            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white mb-3">
                <x-phosphor-map-trifold-duotone class="w-4 h-4 text-primary-500" />
                Estados / Provincias
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-primary-500 rounded-full">
                    {{ count($states) }}
                </span>
            </h3>

            {{-- Buscador --}}
            <div class="mb-3">
                <input type="text"
                       wire:model.live.debounce.300ms="stateSearch"
                       placeholder="Buscar estado / provincia..."
                       class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm text-sm py-1.5 px-3 focus:ring-primary-500 focus:border-primary-500">
            </div>

            {{-- Lista --}}
            @if(count($states) === 0)
                <p class="text-sm text-gray-400 italic text-center py-4">Sin resultados</p>
            @else
                <div class="space-y-0.5 max-h-[420px] overflow-y-auto pr-1 mb-3">
                    @foreach($states as $state)
                        @if($editingStateId === $state['id'])
                            {{-- Fila en modo edición --}}
                            <div class="p-2.5 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-950">
                                <div class="mb-2">
                                    <input type="text" wire:model="editStateName" placeholder="Nombre *"
                                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md text-sm py-1 px-2.5 focus:ring-warning-500 focus:border-warning-500">
                                    @error('editStateName') <p class="text-xs text-danger-600 mt-0.5">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex gap-1.5">
                                    <button wire:click="saveState"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-warning-600 hover:bg-warning-700 rounded-md transition-colors">
                                        <x-phosphor-floppy-disk-bold class="w-3 h-3" /> Guardar
                                    </button>
                                    <button wire:click="cancelEditState"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Fila normal --}}
                            <div wire:click="selectState({{ $state['id'] }}, '{{ addslashes($state['name']) }}')"
                                 class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors
                                        {{ $selectedStateId === $state['id']
                                            ? 'bg-primary-100 dark:bg-primary-900 border border-primary-300 dark:border-primary-700'
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-800 border border-transparent' }}">
                                <span class="flex-1 text-sm font-medium text-gray-800 dark:text-gray-200 truncate">
                                    {{ $state['name'] }}
                                </span>
                                @if($selectedStateId === $state['id'])
                                    <x-phosphor-caret-right-bold class="w-3.5 h-3.5 text-primary-500 shrink-0" />
                                @endif
                                <div class="flex items-center gap-0.5 shrink-0" onclick="event.stopPropagation()">
                                    <button wire:click="startEditState({{ $state['id'] }})"
                                            title="Editar"
                                            class="p-1.5 text-gray-400 hover:text-warning-600 dark:hover:text-warning-400 rounded transition-colors">
                                        <x-phosphor-pencil-simple-bold class="w-3.5 h-3.5" />
                                    </button>
                                    <button wire:click="deleteState({{ $state['id'] }})"
                                            wire:confirm="¿Eliminar '{{ $state['name'] }}'? Esta acción no se puede deshacer."
                                            title="Eliminar"
                                            class="p-1.5 text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 rounded transition-colors">
                                        <x-phosphor-trash-bold class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- ✚ Agregar nuevo estado (siempre visible al pie) --}}
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
                    <x-phosphor-plus-circle-bold class="w-3.5 h-3.5 text-success-500" />
                    Agregar Estado / Provincia
                </p>
                <div class="flex gap-2">
                    <input type="text"
                           wire:model="newStateName"
                           wire:keydown.enter="addState"
                           placeholder="Nombre del estado o provincia..."
                           class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm py-1.5 px-3 focus:ring-success-500 focus:border-success-500">
                    <button wire:click="addState"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-success-600 hover:bg-success-700 rounded-lg transition-colors shrink-0">
                        <x-phosphor-plus-bold class="w-3.5 h-3.5" /> Agregar
                    </button>
                </div>
                @error('newStateName') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </x-filament::section>

        {{-- ── PANEL CIUDADES ──────────────────────────────────────── --}}
        <x-filament::section>
            @if(!$selectedStateId)
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <x-phosphor-arrow-left-duotone class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-3" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">Seleccioná un estado / provincia<br>para ver sus ciudades</p>
                </div>
            @else
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white mb-3">
                    <x-phosphor-buildings-duotone class="w-4 h-4 text-primary-500" />
                    <span class="truncate max-w-[200px]" title="{{ $selectedStateName }}">{{ $selectedStateName }}</span>
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-primary-500 rounded-full">
                        {{ count($cities) }}
                    </span>
                </h3>

                {{-- Buscador --}}
                <div class="mb-3">
                    <input type="text"
                           wire:model.live.debounce.300ms="citySearch"
                           placeholder="Buscar ciudad / localidad..."
                           class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm text-sm py-1.5 px-3 focus:ring-primary-500 focus:border-primary-500">
                </div>

                {{-- Lista --}}
                @if(count($cities) === 0)
                    <p class="text-sm text-gray-400 italic text-center py-4">Sin resultados</p>
                @else
                    <div class="space-y-0.5 max-h-[420px] overflow-y-auto pr-1 mb-3">
                        @foreach($cities as $city)
                            @if($editingCityId === $city['id'])
                                {{-- Fila en modo edición --}}
                                <div class="p-2.5 rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-950">
                                    <div class="mb-2">
                                        <input type="text" wire:model="editCityName" placeholder="Nombre *"
                                               class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md text-sm py-1 px-2.5 focus:ring-warning-500 focus:border-warning-500">
                                        @error('editCityName') <p class="text-xs text-danger-600 mt-0.5">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex gap-1.5">
                                        <button wire:click="saveCity"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-white bg-warning-600 hover:bg-warning-700 rounded-md transition-colors">
                                            <x-phosphor-floppy-disk-bold class="w-3 h-3" /> Guardar
                                        </button>
                                        <button wire:click="cancelEditCity"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors">
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            @else
                                {{-- Fila normal --}}
                                <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <span class="flex-1 text-sm text-gray-800 dark:text-gray-200 truncate">{{ $city['name'] }}</span>
                                    <div class="flex items-center gap-0.5 shrink-0">
                                        <button wire:click="startEditCity({{ $city['id'] }})"
                                                title="Editar"
                                                class="p-1.5 text-gray-400 hover:text-warning-600 dark:hover:text-warning-400 rounded transition-colors">
                                            <x-phosphor-pencil-simple-bold class="w-3.5 h-3.5" />
                                        </button>
                                        <button wire:click="deleteCity({{ $city['id'] }})"
                                                wire:confirm="¿Eliminar '{{ $city['name'] }}'?"
                                                title="Eliminar"
                                                class="p-1.5 text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 rounded transition-colors">
                                            <x-phosphor-trash-bold class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- ✚ Agregar nueva ciudad (siempre visible al pie) --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
                        <x-phosphor-plus-circle-bold class="w-3.5 h-3.5 text-success-500" />
                        Agregar Ciudad / Localidad en {{ $selectedStateName }}
                    </p>
                    <div class="flex gap-2">
                        <input type="text"
                               wire:model="newCityName"
                               wire:keydown.enter="addCity"
                               placeholder="Nombre de la ciudad o localidad..."
                               class="flex-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm py-1.5 px-3 focus:ring-success-500 focus:border-success-500">
                        <button wire:click="addCity"
                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-white bg-success-600 hover:bg-success-700 rounded-lg transition-colors shrink-0">
                            <x-phosphor-plus-bold class="w-3.5 h-3.5" /> Agregar
                        </button>
                    </div>
                    @error('newCityName') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif
        </x-filament::section>

    </div>{{-- /grid --}}

    @endif{{-- /selectedCountry --}}

</x-filament-panels::page>

