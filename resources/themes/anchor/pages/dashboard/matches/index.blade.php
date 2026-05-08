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
            <div class="space-y-4">
                @foreach($allMatches as $listing)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start gap-4 flex-1 min-w-0">
                            @php $displayImage = $listing->primaryImage ?? $listing->firstImage; @endphp
                            @if($displayImage)
                                <img src="{{ $displayImage->image_url }}" alt="{{ $listing->title }}"
                                     class="w-14 h-14 sm:w-16 sm:h-16 object-cover rounded-lg flex-shrink-0">
                            @else
                                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 sm:w-7 sm:h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                            @endif

                            <div class="min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $listing->title }}</h3>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    <span>{{ \App\Models\PropertyType::getLabel($listing->property_type) }}</span>
                                    <span class="hidden xs:inline">•</span>
                                    <span>{{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}</span>
                                    <span>•</span>
                                    <span>{{ $listing->city }}, {{ $listing->state }}</span>
                                    <span>•</span>
                                    <span class="font-medium text-blue-600 dark:text-blue-400">
                                        {{ $listing->currency }} {{ number_format($listing->price, 0) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between sm:justify-end gap-3 sm:flex-shrink-0">
                            <span class="px-3 py-1 bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300 text-sm font-semibold rounded-full whitespace-nowrap">
                                {{ trans_choice('dashboard.matches_section.match_count', $listing->match_count, ['count' => $listing->match_count]) }}
                            </span>
                            <a href="{{ route('dashboard.matches.show', $listing) }}"
                               class="inline-flex items-center gap-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                                {{ __('dashboard.matches_section.see_all') }}
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($allMatches->hasPages())
                <div class="mt-6">
                    {{ $allMatches->links() }}
                </div>
            @endif
        @endif

    </x-app.container>
</x-layouts.app>
