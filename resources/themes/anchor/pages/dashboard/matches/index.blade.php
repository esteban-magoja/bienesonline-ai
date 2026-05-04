<x-layouts.app>
    <x-app.container class="lg:space-y-6">
        
        <x-app.heading
            :title="__('dashboard.matches_section.title')"
            :description="__('dashboard.matches_section.description')"
            :border="false"
        />

        @if($allMatches->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('dashboard.matches_section.no_matches') }}</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('dashboard.matches_section.no_matches_desc') }}</p>
                <a href="{{ route('property-listings.create') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                    {{ __('dashboard.matches_section.publish_listing') }}
                </a>
            </div>
        @else
            <div class="space-y-6">
                @foreach($allMatches as $item)
                    @php
                        $listing = $item['listing'];
                        $matches = $item['matches'];
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
                        <!-- Listing Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ $listing->title }}</h3>
                                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                        <span>{{ \App\Models\PropertyType::getLabel($listing->property_type) }}</span>
                                        <span>•</span>
                                        <span>{{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}</span>
                                        <span>•</span>
                                        <span>{{ $listing->city }}, {{ $listing->state }}</span>
                                        <span>•</span>
                                        <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $listing->currency }} {{ number_format($listing->price, 0) }}</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <span class="px-4 py-2 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 text-sm font-medium rounded-full">
                                        {{ trans_choice('dashboard.matches_section.match_count', $matches->count(), ['count' => $matches->count()]) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Matches Grid -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($matches as $request)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm transition-all bg-white dark:bg-gray-800">
                                        <!-- Match Level Badge -->
                                        <div class="flex justify-between items-start mb-3">
                                            @if($request->match_level === 'exact')
                                                <span class="px-2 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 rounded-full">
                                                    {{ __('dashboard.matches_section.exact_match') }}
                                                </span>
                                            @elseif($request->match_level === 'semantic')
                                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 rounded-full">
                                                    {{ __('dashboard.matches_section.semantic_match') }}
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300 rounded-full">
                                                    {{ __('dashboard.matches_section.flexible_match') }}
                                                </span>
                                            @endif
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $request->match_score }}%</span>
                                        </div>

                                        <!-- Request Title -->
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">{{ $request->title }}</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-3">{{ $request->description }}</p>

                                        <!-- Budget & Location -->
                                        <div class="flex items-center gap-3 text-xs text-gray-600 dark:text-gray-400 mb-3">
                                            <span>{{ $request->budget_range }}</span>
                                            <span>•</span>
                                            <span>{{ $request->city ?? $request->state }}</span>
                                        </div>

                                        <!-- Match Details -->
                                        @if(!empty($request->match_details))
                                            <div class="mb-3 p-2 bg-gray-50 dark:bg-gray-700/50 rounded text-xs text-gray-600 dark:text-gray-400">
                                                <strong>{{ __('dashboard.matches_section.reasons') }}:</strong>
                                                <ul class="list-disc list-inside mt-1">
                                                    @foreach(array_slice($request->match_details, 0, 2) as $detail)
                                                        <li>{{ $detail }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <!-- Contact Info -->
                                        @php
                                            $locale = app()->getLocale();
                                            $listingUrl = url("/{$locale}/" . \Illuminate\Support\Str::slug($listing->country) . "/" . \Illuminate\Support\Str::slug($listing->city) . "/propiedad/{$listing->id}-" . \Illuminate\Support\Str::slug($listing->title));
                                            $clientName  = $request->client_name  ?? $request->user->name;
                                            $clientEmail = $request->client_email ?? $request->user->email;
                                            $clientPhone = $request->client_phone ?? $request->user->movil ?? null;
                                        @endphp
                                        <div class="flex flex-col gap-2 pt-3 border-t border-gray-200 dark:border-gray-700">
                                            @if($clientName)
                                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $clientName }}</p>
                                            @endif
                                            <div class="flex gap-2">
                                                @if($clientEmail)
                                                    <a href="mailto:{{ $clientEmail }}?subject={{ rawurlencode('Propiedad que coincide con tu búsqueda') }}&body={{ rawurlencode("Hola {$clientName},\n\nTengo una propiedad que podría interesarte:\n{$listingUrl}") }}"
                                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                                       title="{{ __('dashboard.matches_section.send_email') }}">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                        </svg>
                                                        Email
                                                    </a>
                                                @endif
                                                @if($clientPhone)
                                                    @php $phone = preg_replace('/[^0-9]/', '', $clientPhone); @endphp
                                                    <a href="https://wa.me/{{ $phone }}?text={{ rawurlencode("Hola {$clientName}, tengo una propiedad que coincide con tu búsqueda:\n{$listingUrl}") }}"
                                                       target="_blank"
                                                       class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors"
                                                       title="WhatsApp">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                                                        </svg>
                                                        WhatsApp
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- View All Link -->
                            @if($matches->count() >= 5)
                                <div class="mt-4 text-center">
                                    <a href="{{ route('dashboard.matches.show', $listing) }}" 
                                       class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium">
                                        {{ __('dashboard.matches_section.see_all') }} →
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </x-app.container>
</x-layouts.app>
