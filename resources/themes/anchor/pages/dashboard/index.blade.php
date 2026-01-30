<?php
    use function Laravel\Folio\{middleware, name};
	use App\Models\PropertyListing;
	use App\Models\PropertyRequest;
	use App\Models\PropertyMessage;
	use App\Services\PropertyMatchingService;
	
	middleware('auth');
    name('dashboard');

	$userListings = PropertyListing::where('user_id', auth()->id())->active()->count();
	$userRequests = PropertyRequest::where('user_id', auth()->id())->active()->count();
	$unreadMessages = PropertyMessage::whereHas('propertyListing', function($query) {
		$query->where('user_id', auth()->id());
	})->where('is_read', false)->count();
	
	// Obtener algunos matches recientes
	$matchingService = app(PropertyMatchingService::class);
	$recentListings = PropertyListing::where('user_id', auth()->id())->active()->take(3)->get();
	$totalMatches = 0;
	foreach ($recentListings as $listing) {
		$totalMatches += $matchingService->findMatchesForListing($listing, 5)->count();
	}
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
						<p class="text-md text-gray-600 mb-3">{{ __('dashboard.home.messages') }}</p>
						<p class="text-3xl font-bold text-gray-900">{{ $unreadMessages }}</p>
					</div>
					<svg class="w-12 h-12 text-orange-500 mt-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
					</svg>
				</div>
				@if($unreadMessages > 0)
					<div class="mt-3 text-orange-600">
						<a href="{{ route('dashboard.messages.index') }}" class="mt-4 text-sm text-orange-600 hover:text-orange-700 font-medium">{{ __('dashboard.home.unread_messages', ['count' => $unreadMessages]) }}</a>
					</div>
				@else
					<div class="mt-3 text-orange-600">
						<a href="{{ route('dashboard.messages.index') }}" class="mt-4 text-sm text-orange-600 hover:text-orange-700 font-medium">{{ __('dashboard.home.view_messages') }}</a>
					</div>
				@endif
			</div>

			<div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-md text-gray-600 mb-3">{{ __('dashboard.home.matches') }}</p>
						<p class="text-3xl font-bold text-gray-900">{{ $totalMatches }}</p>
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
