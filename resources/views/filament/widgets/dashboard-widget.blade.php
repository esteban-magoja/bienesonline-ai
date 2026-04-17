<x-filament-widgets::widget class="gap-5 fi-filament-info-widget">

    {{-- Fila 1: Estadísticas principales --}}
    <section class="grid grid-cols-2 gap-5 mb-5 xl:grid-cols-4">

        {{-- Usuarios registrados --}}
        <a href="{{ route('filament.admin.resources.user-management.index') }}" class="group">
            <x-filament::section class="h-full transition-shadow group-hover:shadow-md">
                <div class="flex gap-x-4 items-center">
                    <x-phosphor-users-duotone class="h-10 text-blue-600 fill-current shrink-0" />
                    <div>
                        <div class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-200">
                            {{ $totalUsers }}
                        </div>
                        <div class="mt-1 text-xs font-medium text-gray-500">Usuarios registrados</div>
                    </div>
                </div>
                <div class="mt-3 text-xs text-blue-600 dark:text-blue-400 group-hover:underline">
                    Gestionar usuarios →
                </div>
            </x-filament::section>
        </a>

        {{-- Suscriptores activos --}}
        <x-filament::section class="h-full">
            <div class="flex gap-x-4 items-center">
                <x-phosphor-credit-card-duotone class="h-10 text-emerald-600 fill-current shrink-0" />
                <div>
                    <div class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-200">
                        {{ $totalSubscribers }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-gray-500">Suscriptores activos</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Total anuncios --}}
        <x-filament::section class="h-full">
            <div class="flex gap-x-4 items-center">
                <x-phosphor-buildings-duotone class="h-10 text-violet-600 fill-current shrink-0" />
                <div>
                    <div class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-200">
                        {{ $totalListings }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-gray-500">Anuncios totales</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Anuncios activos --}}
        <x-filament::section class="h-full">
            <div class="flex gap-x-4 items-center">
                <x-phosphor-check-circle-duotone class="h-10 text-emerald-600 fill-current shrink-0" />
                <div>
                    <div class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-200">
                        {{ $activeListings }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-gray-500">Anuncios activos</div>
                </div>
            </div>
        </x-filament::section>

    </section>

    {{-- Fila 2: Anuncios por país --}}
    <x-filament::section>
        <div class="flex items-center gap-x-2 mb-4">
            <x-phosphor-globe-duotone class="h-5 text-blue-600 fill-current" />
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Anuncios por país</h2>
        </div>

        @if($listingsByCountry->isEmpty())
            <p class="text-sm text-gray-400 dark:text-gray-500">No hay anuncios publicados aún.</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                @foreach($listingsByCountry as $row)
                    <div class="flex flex-col items-center justify-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10">
                        <span class="text-xl font-bold text-gray-900 dark:text-white">{{ $row->total }}</span>
                        <span class="mt-1 text-xs text-center font-medium text-gray-500 dark:text-gray-400 truncate w-full text-center">{{ $row->country }}</span>
                        @if($totalListings > 0)
                            <div class="mt-2 w-full bg-gray-200 dark:bg-white/10 rounded-full h-1">
                                <div class="h-1 rounded-full bg-blue-500" style="width: {{ round(($row->total / $totalListings) * 100) }}%"></div>
                            </div>
                            <span class="mt-1 text-xs text-gray-400">{{ round(($row->total / $totalListings) * 100) }}%</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

</x-filament-widgets::widget>
