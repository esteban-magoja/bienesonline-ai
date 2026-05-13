<x-layouts.app>
    <x-app.container class="lg:space-y-6">
        
        <x-app.heading
            :title="__('dashboard.matches_section.for_listing') . ': ' . $listing->title"
            description="{{ __('dashboard.matches_section.description') }}"
            :border="false"
        >
            <x-slot name="actions">
                <a href="{{ route('dashboard.matches.index') }}" 
                   class="px-4 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                    {{ __('dashboard.request_form.back') }}
                </a>
            </x-slot>
        </x-app.heading>

        <!-- Listing Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-start gap-6">
                @php
                    $displayImage  = $listing->primaryImage ?? $listing->firstImage;
                    $locale        = app()->getLocale();
                    $listingUrl    = url("/{$locale}/" . \Illuminate\Support\Str::slug($listing->country) . "/" . \Illuminate\Support\Str::slug($listing->city) . "/propiedad/{$listing->id}-" . \Illuminate\Support\Str::slug($listing->title));
                @endphp
                @if($displayImage)
                    <img src="{{ $displayImage->image_url }}" 
                         alt="{{ $listing->title }}"
                         class="w-32 h-32 object-cover rounded-lg">
                @else
                    <div class="w-32 h-32 bg-gray-200 rounded-lg flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                @endif

                <div class="flex-1">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">
                        <a href="{{ $listingUrl }}" target="_blank" rel="noopener"
                           class="hover:text-blue-600 hover:underline transition-colors inline-flex items-center gap-1.5">
                            {{ $listing->title }}
                            <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </h2>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            {{ __("dashboard.requests.type") }}
                            <p class="font-medium text-gray-900">{{ \App\Models\PropertyType::getLabel($listing->property_type) }}</p>
                        </div>
                        <div>
                            {{ __("dashboard.requests.operation") }}
                            <p class="font-medium text-gray-900">{{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}</p>
                        </div>
                        <div>
                            {{ __("properties.price") }}
                            <p class="font-medium text-blue-600">{{ $listing->currency }} {{ number_format($listing->price, 0) }}</p>
                        </div>
                        <div>
                            {{ __("dashboard.requests.location") }}
                            <p class="font-medium text-gray-900">{{ $listing->city }}, {{ $listing->state }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Matches Section -->
        <div>
            <h3 class="text-xl font-bold text-gray-900 mb-4">
                {{ __('dashboard.matches_section.matching_requests') }}
                @if($matches->isNotEmpty())
                    @if(($totalMatches ?? 0) > $matches->count())
                        ({{ __('dashboard.matches_section.top_of_total', ['top' => $matches->count(), 'total' => $totalMatches]) }})
                    @else
                        ({{ $matches->count() }})
                    @endif
                @endif
            </h3>

            @if($matches->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('dashboard.matches_section.no_matching_requests') }}</h3>
                    <p class="text-gray-600">{{ __('dashboard.matches_section.no_matching_requests_desc') }}</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($matches as $request)
                        @php $isRecent = $request->created_at >= $recentThreshold; @endphp
                        <div class="bg-white rounded-lg shadow-sm border p-6 hover:shadow-md transition-shadow {{ $isRecent ? 'border-green-400 ring-1 ring-green-300' : 'border-gray-200' }}">
                            <!-- Match Level Badge -->
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @if($request->match_level === 'exact')
                                        <span class="px-3 py-1 text-sm font-medium bg-green-100 text-green-800 rounded-full">
                                            {{ __('dashboard.matches_section.exact_match') }}
                                        </span>
                                    @elseif($request->match_level === 'semantic')
                                        <span class="px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded-full">
                                            {{ __('dashboard.matches_section.intelligent_match') }}
                                        </span>
                                    @else
                                        <span class="px-3 py-1 text-sm font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                            {{ __('dashboard.matches_section.flexible_match') }}
                                        </span>
                                    @endif
                                    @if($isRecent)
                                        <span class="px-2 py-1 text-xs font-bold bg-green-500 text-white rounded-full uppercase tracking-wide">
                                            ✦ {{ __('dashboard.matches_section.new') }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-sm text-gray-500 shrink-0">{{ __('dashboard.matches_section.match_score', ['score' => $request->match_score]) }}</span>
                            </div>

                            <!-- Request Details -->
                            <h4 class="text-lg font-semibold text-gray-900 mb-2">{{ $request->title }}</h4>
                            <p class="text-gray-600 mb-4 line-clamp-3">{{ $request->description }}</p>

                            <!-- Budget & Specs -->
                            <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
                                <div>
                                    <span class="text-sm text-gray-500">{{ __('dashboard.matches_section.budget') }}</span>
                                    <p class="font-medium text-gray-900">{{ $request->budget_range }}</p>
                                </div>
                                <div>
                                    <span class="text-sm text-gray-500">{{ __('dashboard.matches_section.desired_location') }}</span>
                                    <p class="font-medium text-gray-900">{{ $request->city ?? $request->state ?? $request->country }}</p>
                                </div>
                            </div>

                            @if($request->min_bedrooms || $request->min_bathrooms || $request->min_area)
                                <div class="flex gap-4 text-sm text-gray-600 mb-4">
                                    @if($request->min_bedrooms)
                                        <span>{{ __('dashboard.requests.min_bedrooms_short', ['count' => $request->min_bedrooms]) }}</span>
                                    @endif
                                    @if($request->min_bathrooms)
                                        <span>{{ __('dashboard.requests.min_bathrooms_short', ['count' => $request->min_bathrooms]) }}</span>
                                    @endif
                                    @if($request->min_area)
                                        <span>{{ __('dashboard.requests.min_area_short', ['area' => $request->min_area]) }}</span>
                                    @endif
                                </div>
                            @endif

                            <!-- Match Details -->
                            @if(!empty($request->match_details))
                                <div class="mb-4 p-3 bg-gray-50 rounded text-sm">
                                    <strong class="text-gray-900">{{ __('dashboard.matches_section.why_matches') }}:</strong>
                                    <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                                        @foreach($request->match_details as $detail)
                                            <li>{{ $detail }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <!-- Contact Info del solicitante -->
                            @php
                                $clientName  = $request->client_name  ?? $request->user->name;
                                $clientEmail = $request->client_email ?? $request->user->email;
                                $clientPhone = $request->client_phone ?? $request->user->movil ?? null;
                            @endphp
                            <div class="pt-4 border-t border-gray-200">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    @if($clientName)
                                        <p class="font-medium text-gray-900">{{ $clientName }}</p>
                                    @endif

                                    <div class="flex gap-2">
                                        @if($clientEmail)
                                            <a href="mailto:{{ $clientEmail }}?subject={{ rawurlencode('Propiedad que coincide con tu búsqueda') }}&body={{ rawurlencode("Hola {$clientName},\n\nTengo una propiedad que podría interesarte:\n{$listingUrl}") }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                               title="{{ __('dashboard.matches_section.send_email') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                Email
                                            </a>
                                        @endif

                                        @if($clientPhone)
                                            @php $phone = preg_replace('/[^0-9]/', '', $clientPhone); @endphp
                                            <a href="https://wa.me/{{ $phone }}?text={{ rawurlencode("Hola {$clientName}, tengo una propiedad que coincide con tu búsqueda:\n{$listingUrl}") }}"
                                               target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                                               title="WhatsApp">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                </svg>
                                                WhatsApp
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                <p class="text-xs text-gray-500 mt-3">
                                    {{ __('dashboard.matches_section.request_created') }} {{ $request->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </x-app.container>
</x-layouts.app>
