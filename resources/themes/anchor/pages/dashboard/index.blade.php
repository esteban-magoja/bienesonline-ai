<?php
    use function Laravel\Folio\{middleware, name};
	use App\Models\PropertyListing;
	use App\Models\PropertyRequest;
	use App\Models\PropertyContact;
	use App\Models\ImportJob;
	
	
	middleware('auth');
    name('dashboard');

	$userListings = PropertyListing::where('user_id', auth()->id())->active()->count();
	$userRequests = PropertyRequest::where('user_id', auth()->id())->active()->count();
	$totalContacts = PropertyContact::where('owner_user_id', auth()->id())->count();
	$unseenContacts = PropertyContact::where('owner_user_id', auth()->id())->whereNull('seen_at')->count();
	
	// Último import job del usuario
	$latestImport = ImportJob::where('user_id', auth()->id())->latest()->first();
?>

<x-layouts.app>
	<x-app.container x-data class="lg:space-y-6" x-cloak>
        
		<!-- Mensaje de Verificación Exitosa -->
		@if(request()->query('verified') == '1')
			<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md shadow-sm">
				<div class="flex items-center">
					<div class="flex-shrink-0">
						<svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
						</svg>
					</div>
					<div class="ml-3">
						<h3 class="text-sm font-medium text-green-800">
							{{ __('dashboard.alerts.email_verified') }}
						</h3>
						<p class="mt-1 text-sm text-green-700">
							{{ __('dashboard.alerts.email_verified_desc') }}
						</p>
					</div>
					<div class="ml-auto pl-3">
						<button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex text-green-500 hover:text-green-700 focus:outline-none">
							<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
							</svg>
						</button>
					</div>
				</div>
			</div>
		@endif

		<!-- Mensaje de Éxito de Aceptación de Términos -->
		@if(session('success'))
			<div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded-md shadow-sm">
				<div class="flex items-center">
					<div class="flex-shrink-0">
						<svg class="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
						</svg>
					</div>
					<div class="ml-3">
						<p class="text-sm font-medium text-green-800">
							{{ session('success') }}
						</p>
					</div>
					<div class="ml-auto pl-3">
						<button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex text-green-500 hover:text-green-700 focus:outline-none">
							<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
							</svg>
						</button>
					</div>
				</div>
			</div>
		@endif

		<!-- Mensaje de Aceptación de Términos -->
		@if(!auth()->user()->hasAcceptedTerms())
			<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded-md shadow-sm">
				<div class="flex items-start">
					<div class="flex-shrink-0">
						<svg class="h-6 w-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
						</svg>
					</div>
					<div class="ml-3 flex-1">
						<h3 class="text-sm font-medium text-yellow-800">
							{{ __('dashboard.alerts.terms_pending_title') }}
						</h3>
						<p class="mt-2 text-sm text-yellow-700">
							{{ __('dashboard.alerts.terms_pending_desc') }}
						</p>
						<div class="mt-4">
							<a href="{{ route('dashboard.terms') }}" 
								class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-yellow-800 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
								{{ __('dashboard.alerts.view_accept_terms') }}
								<svg class="ml-2 -mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
								</svg>
							</a>
						</div>
					</div>
					<div class="ml-auto pl-3">
						<button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex text-yellow-500 hover:text-yellow-700 focus:outline-none">
							<svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
								<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
							</svg>
						</button>
					</div>
				</div>
			</div>
		@endif

		<x-app.alert id="dashboard_alert" class="hidden lg:flex">{{ __('dashboard.alerts.welcome_message') }}</x-app.alert>

        <x-app.heading
                :title="__('dashboard.home.title')"
                :description="__('dashboard.home.description')"
                :border="false"
            />

		<!-- Quick Stats -->
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
			<div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-md text-gray-600 mb-3">{{ __('dashboard.home.my_listings') }}</p>
						<p class="text-3xl font-bold text-gray-900">{{ $userListings }}</p>
					</div>
					<svg class="w-12 h-12 text-blue-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
					</svg>
				</div>
				<div class="mt-3 text-blue-600">
					<p><a href="/property-listings" class="mt-4 text-sm text-blue-600 hover:text-blue-700 font-medium">{{ __('dashboard.home.view_listings') }}</a></p>
					<p><a href="/property-listings/create" class="mt-4 text-sm text-blue-600 hover:text-blue-700 font-medium">{{ __('dashboard.home.publish_listing') }}</a></p>
				</div>
			</div>

			<div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-md text-gray-600 mb-3">{{ __('dashboard.home.clients') }}</p>
						<p class="text-3xl font-bold text-gray-900">{{ $userRequests }}</p>
					</div>
					<svg class="w-12 h-12 text-green-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
					</svg>
				</div>
				<div class="mt-3 text-green-600">
					<p><a href="{{ route('dashboard.requests.index') }}" class="mt-4 text-sm text-green-600 hover:text-green-700 font-medium">{{ __('dashboard.home.view_requests') }}</a></p>
					<p><a href="{{ route('dashboard.requests.create') }}" class="mt-4 text-sm text-green-600 hover:text-green-700 font-medium">{{ __('dashboard.home.add_request') }}</a></p>
				</div>
			</div>

			<div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-md text-gray-600 mb-3">Mis Contactos</p>
						<p class="text-3xl font-bold text-gray-900">{{ $unseenContacts }}</p>
						<p class="text-xs text-gray-400 mt-1">{{ $totalContacts }} en total</p>
					</div>
					<svg class="w-12 h-12 text-orange-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
					</svg>
				</div>
				<div class="mt-3 text-orange-600">
					@if($unseenContacts > 0)
						<a href="{{ route('dashboard.contacts.index') }}" class="mt-4 text-sm text-orange-600 hover:text-orange-700 font-medium">{{ $unseenContacts }} nuevo{{ $unseenContacts > 1 ? 's' : '' }} sin ver</a>
					@else
						<a href="{{ route('dashboard.contacts.index') }}" class="mt-4 text-sm text-orange-600 hover:text-orange-700 font-medium">Ver contactos</a>
					@endif
				</div>
			</div>

			<div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-md text-gray-600 mb-3">{{ __('dashboard.home.matches') }}</p>
						<p class="text-3xl font-bold text-gray-900">—</p>
					</div>
					<svg class="w-12 h-12 text-purple-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
					</svg>
				</div>
				<div class="mt-3 text-purple-600">
					<a href="{{ route('dashboard.matches.index') }}" class="mt-4 text-sm text-purple-600 hover:text-purple-700 font-medium">{{ __('dashboard.home.view_matches') }}</a>
				</div>	
			</div>
		</div>

		{{-- Sección de Importación desde sistema anterior --}}
		@php $importCountries = array_keys(config('import.legacy_urls', [])); @endphp
		@if(count($importCountries) > 0)
		<div class="mt-6"
			x-data="{
				country: '',
				jobId: {{ $latestImport && $latestImport->isRunning() ? $latestImport->id : 'null' }},
				status: '{{ $latestImport ? $latestImport->status : '' }}',
				progress: {{ $latestImport ? $latestImport->progressPercent() : 0 }},
				total: {{ $latestImport ? $latestImport->total_listings : 0 }},
				imported: {{ $latestImport ? $latestImport->imported_listings : 0 }},
				skipped: {{ $latestImport ? $latestImport->skipped_listings : 0 }},
				failed: {{ $latestImport ? $latestImport->failed_listings : 0 }},
				errorMsg: '',
				loading: false,
				pollInterval: null,

				get isRunning() { return this.status === 'pending' || this.status === 'processing'; },
				get isCompleted() { return this.status === 'completed'; },
				get isFailed() { return this.status === 'failed'; },

				init() {
					if (this.isRunning) this.startPolling();
				},

				async triggerImport() {
					if (!this.country) return;
					this.loading = true;
					this.errorMsg = '';
					const body = new URLSearchParams({ country: this.country });
					try {
						const res = await fetch('{{ route('dashboard.import.trigger') }}', {
							method: 'POST',
							headers: {
								'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
								'Accept': 'application/json',
								'Content-Type': 'application/x-www-form-urlencoded'
							},
							body: body.toString()
						});
						const data = await res.json();
						if (res.status === 200) {
							this.errorMsg = data.message;
						} else if (res.ok || res.status === 202) {
							this.jobId = data.job.id;
							this.status = data.job.status;
							this.total = data.job.total_listings;
							this.imported = 0; this.skipped = 0; this.failed = 0; this.progress = 0;
							this.startPolling();
						} else {
							this.errorMsg = data.message;
						}
					} catch(e) {
						this.errorMsg = '{{ __('import.connection_error') }}';
					}
					this.loading = false;
				},

				startPolling() {
					this.pollInterval = setInterval(() => this.pollStatus(), 2000);
				},

				async pollStatus() {
					if (!this.jobId) return;
					const res = await fetch('{{ url('dashboard/import/status') }}/' + this.jobId, {
						headers: { 'Accept': 'application/json' }
					});
					const data = await res.json();
					this.status = data.status;
					this.progress = data.progress_percent;
					this.total = data.total_listings;
					this.imported = data.imported_listings;
					this.skipped = data.skipped_listings;
					this.failed = data.failed_listings;
					if (!this.isRunning) {
						clearInterval(this.pollInterval);
						this.errorMsg = data.error_message || '';
					}
				}
			}"
			x-init="init()">

			<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
				<div class="flex items-start justify-between">
					<div>
						<h3 class="text-base font-semibold text-gray-900">{{ __('import.title') }}</h3>
						<p class="text-sm text-gray-500 mt-1">{{ __('import.subtitle') }}</p>
						<p class="text-xs !text-red-500 mt-1">{{ __('import.background_notice') }}</p>
					</div>
					<svg class="w-8 h-8 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
					</svg>
				</div>

				{{-- Selector de país + Botón principal --}}
				<div class="mt-4" x-show="!isRunning && !isCompleted && !isFailed">
					<div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
						<div class="flex-1 max-w-xs">
							<label class="block text-xs font-medium text-gray-600 mb-1">{{ __('import.select_country') }}</label>
							<select x-model="country"
								class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
								<option value="">— {{ __('import.choose_country') }} —</option>
								@foreach($importCountries as $countryName)
									<option value="{{ $countryName }}">{{ $countryName }}</option>
								@endforeach
							</select>
						</div>
						<button @click="triggerImport()" :disabled="loading || !country"
							class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg transition-colors">
							<svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
								<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
								<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
							</svg>
							<span x-text="loading ? '{{ __('import.processing') }}' : '{{ __('import.button') }}'"></span>
						</button>
					</div>
				</div>

				{{-- Progreso --}}
				<div class="mt-4" x-show="isRunning">
					<div class="flex justify-between text-sm text-gray-600 mb-1">
						<span>{{ __('import.processing') }}</span>
						<span x-text="progress + '%'"></span>
					</div>
					<div class="w-full bg-gray-200 rounded-full h-2.5">
						<div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300" :style="'width:' + progress + '%'"></div>
					</div>
					<p class="text-xs text-gray-500 mt-2" x-text="imported + ' / ' + total + ' {{ __('dashboard.home.listings') }}'"></p>
				</div>

				{{-- Resultado: completado --}}
				<div class="mt-4" x-show="isCompleted">
					<div class="flex items-center gap-2 text-green-700 font-medium text-sm mb-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
						</svg>
						{{ __('import.completed') }}
					</div>
					<div class="flex gap-4 text-sm">
						<span class="text-green-600" x-text="'✓ ' + imported + ' {{ __('import.results.imported', ['count' => '']) }}'"></span>
						<span x-show="skipped > 0" class="text-gray-500" x-text="'↩ ' + skipped + ' {{ __('import.results.skipped', ['count' => '']) }}'"></span>
						<span x-show="failed > 0" class="text-red-500" x-text="'✗ ' + failed + ' {{ __('import.results.failed', ['count' => '']) }}'"></span>
					</div>
					<button @click="status = ''; jobId = null"
						class="mt-3 text-xs text-indigo-600 hover:text-indigo-700 underline">
						{{ __('import.button') }} {{ __('dashboard.home.again') ?? 'de nuevo' }}
					</button>
				</div>

				{{-- Resultado: fallido --}}
				<div class="mt-4" x-show="isFailed">
					<p class="text-sm text-red-600 font-medium">{{ __('import.failed') }}</p>
					<p class="text-xs text-red-500 mt-1" x-text="errorMsg"></p>
					<button @click="status = ''; jobId = null; errorMsg = ''"
						class="mt-3 text-xs text-indigo-600 hover:text-indigo-700 underline">
						{{ __('import.button') }}
					</button>
				</div>

				{{-- Error (sin job iniciado) --}}
				<p x-show="errorMsg && !isRunning && !isCompleted && !isFailed"
					class="mt-3 text-sm text-amber-600" x-text="errorMsg"></p>
			</div>
		</div>
		@endif

		

		<div class="mt-5 space-y-5">
			@subscriber
				<p>{{ __('dashboard.home.role_message') }} <strong>{{ auth()->user()->roles()->first()->name }}</strong>.</p>
				<x-app.message-for-subscriber />
			@else
				<p>{{ __('dashboard.home.role_message') }} <strong>{{ auth()->user()->roles()->first()->name }}</strong>.</p>
			@endsubscriber
			
			@admin
				<x-app.message-for-admin />
			@endadmin
		</div>
    </x-app.container>
</x-layouts.app>
