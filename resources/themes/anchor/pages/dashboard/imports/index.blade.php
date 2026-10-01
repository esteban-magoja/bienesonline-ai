<x-layouts.app>
    <x-app.container>

        <x-app.heading
            :title="__('import.section_title')"
            :description="__('import.section_subtitle')"
        />

        @if(! $canImport)
            <div class="mt-6">
                <div class="mb-6 p-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-yellow-800 dark:text-yellow-300">{{ __('import.premium_required') }}</h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-400">
                                <p>{{ __('import.premium_description') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <livewire:billing.checkout />
            </div>
        @else
        @if(session('success'))
            <div class="mb-4 px-4 py-3 text-sm text-green-800 bg-green-100 rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 text-sm text-red-800 bg-red-100 rounded-lg">{{ session('error') }}</div>
        @endif

        {{-- Fuentes conectadas --}}
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('import.connected_sources') }}</h3>

        @forelse($sources as $source)
            @php
                $job = $source->importJobs->first();
                $running = $job && in_array($job->status, ['pending', 'processing']);
                $driverLabel = isset($drivers[$source->driver]) ? $drivers[$source->driver]->label() : $source->driver;
            @endphp
            <div class="mb-3 p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700"
                 @if($running)
                 x-data="{
                     status: '{{ $job->status }}', total: {{ $job->total_listings }}, percent: {{ $job->progressPercent() }},
                     async poll() {
                         const res = await fetch('{{ url('dashboard/import/status/' . $job->id) }}', { headers: { 'Accept': 'application/json' } });
                         if (!res.ok) return;
                         const d = await res.json();
                         this.status = d.status; this.total = d.total_listings; this.percent = d.progress_percent;
                         if (['completed', 'failed'].includes(d.status)) { window.location.reload(); }
                     }
                 }"
                 x-init="setInterval(() => poll(), 3000)"
                 @endif>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-xs font-semibold text-indigo-700 bg-indigo-100 rounded-full">{{ $driverLabel }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $source->name }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 break-all">{{ $source->config['feed_url'] ?? '' }}</p>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('import.listings_count', ['count' => $source->listings_count]) }}
                            · {{ __('import.last_sync') }}:
                            {{ $source->last_synced_at ? $source->last_synced_at->diffForHumans() : __('import.never_synced') }}
                            · {{ $source->sync_enabled && $source->status === 'active'
                                ? __('import.auto_sync_every', ['hours' => $source->sync_interval_hours])
                                : __('import.auto_sync_paused') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ route('dashboard.imports.sync', $source) }}">
                            @csrf
                            <button type="submit" @disabled($running)
                                class="px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                {{ __('import.sync_now') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.imports.toggle', $source) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-800">
                                {{ $source->sync_enabled ? __('import.pause_sync') : __('import.resume_sync') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('dashboard.imports.destroy', $source) }}" onsubmit="return confirm(@js(__('import.delete_confirm')))">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded-md hover:bg-red-50">
                                {{ __('import.delete_source') }}
                            </button>
                        </form>
                    </div>
                </div>

                @if($running)
                    <div class="mt-3">
                        <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                            <div class="h-2 transition-all bg-indigo-600 rounded-full" :style="`width: ${percent}%`"></div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ __('import.processing') }}</p>
                    </div>
                @elseif($job)
                    <p class="mt-3 text-xs text-gray-500">
                        @if($job->status === 'failed')
                            <span class="text-red-600">{{ __('import.failed') }}: {{ $job->error_message }}</span>
                        @else
                            {{ __('import.results.imported', ['count' => $job->imported_listings]) }} ·
                            {{ __('import.results_updated', ['count' => $job->updated_listings]) }} ·
                            {{ __('import.results_deactivated', ['count' => $job->deactivated_listings]) }} ·
                            {{ __('import.results.failed', ['count' => $job->failed_listings]) }}
                        @endif
                    </p>
                @endif

                @if($source->status === 'error')
                    <p class="mt-2 text-xs text-red-600">{{ __('import.source_error') }}</p>
                @endif
            </div>
        @empty
            <p class="mb-6 text-sm text-gray-500">{{ __('import.no_sources') }}</p>
        @endforelse

        {{-- Conectar nueva fuente --}}
        @if(count($drivers) > 0)
            <div class="mt-8 p-5 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700"
                 x-data="{ driver: @js(old('driver', count($drivers) === 1 ? array_key_first($drivers) : '')) }">
                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('import.choose_source') }}</h3>

                <form method="POST" action="{{ route('dashboard.imports.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <select name="driver" x-model="driver" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 sm:text-sm">
                            <option value="">— {{ __('import.choose_source_placeholder') }} —</option>
                            @foreach($drivers as $key => $driver)
                                <option value="{{ $key }}">{{ $driver->label() }}</option>
                            @endforeach
                        </select>
                        @error('driver') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @foreach($drivers as $key => $driver)
                        <div x-show="driver === '{{ $key }}'" x-cloak class="space-y-4">
                            <p class="text-sm text-gray-500">{{ $driver->description() }}</p>

                            @foreach($driver->fields() as $name => $field)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $field['label'] }}</label>

                                    @if($field['type'] === 'country')
                                        <select name="{{ $name }}" x-bind:disabled="driver !== '{{ $key }}'"
                                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 sm:text-sm">
                                            <option value="">— {{ __('import.choose_country') }} —</option>
                                            @foreach($countries as $country)
                                                <option value="{{ $country->name }}" @selected(old($name) === $country->name)>{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="{{ $field['type'] === 'url' ? 'url' : 'text' }}" name="{{ $name }}"
                                            x-bind:disabled="driver !== '{{ $key }}'"
                                            value="{{ old($name) }}" placeholder="{{ $field['placeholder'] ?? '' }}"
                                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm dark:bg-gray-700 dark:border-gray-600 sm:text-sm">
                                    @endif

                                    @isset($field['help']) <p class="mt-1 text-xs text-gray-500">{{ $field['help'] }}</p> @endisset
                                    @error($name) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach

                            <p class="text-xs text-red-500">{{ __('import.background_notice') }}</p>

                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">
                                {{ __('import.connect_button') }}
                            </button>
                        </div>
                    @endforeach
                </form>
            </div>
        @endif

        @endif

    </x-app.container>
</x-layouts.app>
