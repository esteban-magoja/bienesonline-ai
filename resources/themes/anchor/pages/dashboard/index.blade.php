<?php
    use function Laravel\Folio\{middleware, name};
	use App\Models\PropertyListing;
	use App\Models\PropertyRequest;
	use App\Models\PropertyContact;
	use Illuminate\Support\Facades\Cache;
	use Illuminate\Support\Facades\DB;
	use Wave\Changelog;

	middleware('auth');
    name('dashboard');

	$userId = auth()->id();

	$userListings = Cache::remember("dashboard_listings_{$userId}", 300, fn () =>
		PropertyListing::where('user_id', $userId)->active()->count()
	);
	$userRequests = Cache::remember("dashboard_requests_{$userId}", 300, fn () =>
		PropertyRequest::where('user_id', $userId)->active()->count()
	);
	$totalContacts = Cache::remember("dashboard_contacts_total_{$userId}", 300, fn () =>
		PropertyContact::where('owner_user_id', $userId)->count()
	);
	$unseenContacts = Cache::remember("dashboard_contacts_unseen_{$userId}", 300, fn () =>
		PropertyContact::where('owner_user_id', $userId)->whereNull('seen_at')->count()
	);

	// No calcular matches en el dashboard (operación costosa con pgvector).
	// Los conteos reales están disponibles en /dashboard/matches y /dashboard/requests.

	// Matches inbound: solicitudes de otros que coinciden con mis anuncios (1 query agregada, sin vectores)
	$matchesInbound = Cache::remember("dashboard_matches_inbound_{$userId}", 21600, function () use ($userId) {
		return (int) DB::table('property_requests')
			->join('property_listings', function ($join) {
				$join->whereRaw('LOWER(property_requests.property_type) = LOWER(property_listings.property_type)')
					 ->whereRaw('LOWER(property_requests.transaction_type) = LOWER(property_listings.transaction_type)')
					 ->whereColumn('property_requests.country', 'property_listings.country')
					 ->where(function ($q) {
						 $q->whereNull('property_requests.max_budget')
						   ->orWhere('property_requests.max_budget', 0)
						   ->orWhereColumn('property_requests.max_budget', '>=', 'property_listings.price');
					 })
					 ->where(function ($q) {
						 $q->whereNull('property_requests.min_budget')
						   ->orWhereColumn('property_requests.min_budget', '<=', 'property_listings.price');
					 })
					 ->where(function ($q) {
						 $q->whereNull('property_requests.city')
						   ->orWhereColumn('property_requests.city', 'property_listings.city')
						   ->orWhereColumn('property_requests.state', 'property_listings.state');
					 });
			})
			->where('property_listings.user_id', $userId)
			->where('property_listings.is_active', true)
			->where('property_requests.is_active', true)
			->where(function ($q) {
				$q->whereNull('property_requests.expires_at')
				  ->orWhere('property_requests.expires_at', '>', now());
			})
			->count(DB::raw('DISTINCT COALESCE(property_requests.client_email, property_requests.id::text)'));
	});

	// Matches outbound: anuncios de otros que coinciden con mis solicitudes (1 query agregada, sin vectores)
	$matchesOutbound = Cache::remember("dashboard_matches_outbound_{$userId}", 21600, function () use ($userId) {
		return (int) DB::table('property_listings')
			->join('property_requests', function ($join) {
				$join->whereRaw('LOWER(property_listings.property_type) = LOWER(property_requests.property_type)')
					 ->whereRaw('LOWER(property_listings.transaction_type) = LOWER(property_requests.transaction_type)')
					 ->whereColumn('property_listings.country', 'property_requests.country')
					 ->where(function ($q) {
						 $q->whereNull('property_requests.max_budget')
						   ->orWhere('property_requests.max_budget', 0)
						   ->orWhereColumn('property_requests.max_budget', '>=', 'property_listings.price');
					 })
					 ->where(function ($q) {
						 $q->whereNull('property_requests.min_budget')
						   ->orWhereColumn('property_requests.min_budget', '<=', 'property_listings.price');
					 })
					 ->where(function ($q) {
						 $q->whereNull('property_requests.city')
						   ->orWhereColumn('property_requests.city', 'property_listings.city')
						   ->orWhereColumn('property_requests.state', 'property_listings.state');
					 });
			})
			->where('property_requests.user_id', $userId)
			->where('property_requests.is_active', true)
			->where(function ($q) {
				$q->whereNull('property_requests.expires_at')
				  ->orWhere('property_requests.expires_at', '>', now());
			})
			->where('property_listings.is_active', true)
			->count(DB::raw('DISTINCT property_listings.id'));
	});

	$latestChangelog = Changelog::latest()->first();
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
						<p class="text-3xl font-bold text-gray-900">{{ $matchesInbound + $matchesOutbound }}</p>
					</div>
					<svg class="w-12 h-12 text-purple-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
					</svg>
				</div>
				@if($matchesInbound > 0 || $matchesOutbound > 0)
				<div class="mt-3 space-y-1">
					@if($matchesInbound > 0)
					<a href="{{ route('dashboard.matches.index') }}" class="flex items-center gap-1 text-sm text-purple-600 hover:text-purple-700 font-medium">
						<span class="font-bold">{{ $matchesInbound }}</span>
						<span>{{ __('dashboard.home.matches_inbound') }}</span>
					</a>
					@endif
					@if($matchesOutbound > 0)
					<a href="{{ route('dashboard.requests.index') }}" class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
						<span class="font-bold">{{ $matchesOutbound }}</span>
						<span>{{ __('dashboard.home.matches_outbound') }}</span>
					</a>
					@endif
				</div>
				@endif
			</div>
			</div>
		</div>

		{{-- Aviso de suscripción obligatoria --}}
		<div class="mt-6 max-w-3xl mx-auto rounded-xl border-l-4 p-5 shadow-sm
			{{ auth()->user()->hasPremiumAccess() ? 'bg-green-50 border-green-500' : 'bg-amber-50 border-amber-500' }}">
			<div class="flex items-start gap-3">
				@if(auth()->user()->hasPremiumAccess())
					<svg class="w-6 h-6 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
					</svg>
				@else
					<svg class="w-6 h-6 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
					</svg>
				@endif
				<div>
				<p class="{{ auth()->user()->hasPremiumAccess() ? 'text-green-800' : 'text-amber-800' }} text-sm font-medium">
						Atención: A partir de Agosto 2026 solo quedarán publicados y activos anuncios de usuarios que mantienen una suscripción activa en Bienesonline.
					</p>
				<p class="mt-2 text-sm {{ auth()->user()->hasPremiumAccess() ? 'text-green-700 font-semibold' : 'text-amber-700' }}">
						@if(auth()->user()->hasPremiumAccess())
							🎉 Felicitaciones, usted ya tiene una suscripción activa.
						@else
							<a href="/settings/subscription" class="font-semibold underline hover:opacity-80">
								Activar mi suscripción →
							</a>
						@endif
					</p>
				</div>
			</div>
		</div>

		@if($latestChangelog)
			<div class="mt-6 max-w-3xl mx-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-5">
				<div class="flex items-center gap-2 mb-3">
					<svg class="w-5 h-5 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
					</svg>
					<h3 class="text-sm font-semibold text-indigo-700 dark:text-indigo-400 uppercase tracking-wide">Novedades</h3>
					<span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">{{ $latestChangelog->created_at->format('d/m/Y') }}</span>
				</div>
				<h4 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $latestChangelog->title }}</h4>
				@if($latestChangelog->description)
					<p class="text-sm text-gray-500 dark:text-gray-400 mb-3 italic">{{ $latestChangelog->description }}</p>
				@endif
				<div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
					{!! $latestChangelog->body !!}
				</div>
			</div>
		@endif

		<div class="mt-5 space-y-5">
			@subscriber
				<x-app.message-for-subscriber />
			@endsubscriber
			
			@admin
				<x-app.message-for-admin />
			@endadmin
		</div>
    </x-app.container>
</x-layouts.app>
