<x-layouts.app>
    <x-app.container class="lg:space-y-6">

        <x-app.heading
            :title="__('dashboard.matches_section.title')"
            :description="__('dashboard.matches_section.description')"
            :border="false"
        />

        {{-- Resumen --}}
        @if($summary['total_matches'] > 0)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['total_matches'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dashboard.matches_section.total_matches') }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="p-3 {{ $summary['new_this_week'] > 0 ? 'bg-green-100 dark:bg-green-900/40' : 'bg-gray-100 dark:bg-gray-700' }} rounded-lg">
                        <svg class="w-6 h-6 {{ $summary['new_this_week'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $summary['new_this_week'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $summary['new_this_week'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dashboard.matches_section.new_this_week') }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/40 rounded-lg">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['listings_count'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dashboard.matches_section.listings_with_matches') }}</p>
                    </div>
                </div>
            </div>
        @endif

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
            <div class="space-y-3">
                @foreach($allMatches as $listing)
                    @php
                        $isToday = $listing->new_today_count > 0;
                        $isNew   = $listing->new_match_count > 0;
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl border {{ $isToday ? 'border-red-400 dark:border-red-600' : ($isNew ? 'border-green-300 dark:border-green-700' : 'border-gray-200 dark:border-gray-700') }} shadow-sm hover:shadow-md transition-shadow">
                        <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-4">

                            {{-- Imagen --}}
                            @php $displayImage = $listing->primaryImage ?? $listing->firstImage; @endphp
                            @if($displayImage)
                                <img src="{{ $displayImage->image_url }}" alt="{{ $listing->title }}"
                                     class="w-16 h-16 object-cover rounded-lg flex-shrink-0">
                            @else
                                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                </div>
                            @endif

                            {{-- Info del anuncio --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $listing->title }}</h3>
                                    @if($isToday)
                                        <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-xs font-semibold rounded-full whitespace-nowrap">
                                            +{{ $listing->new_today_count }} AHORA
                                        </span>
                                    @elseif($isNew)
                                        <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-xs font-semibold rounded-full whitespace-nowrap">
                                            +{{ $listing->new_match_count }} {{ __('dashboard.matches_section.this_week') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ \App\Models\PropertyType::getLabel($listing->property_type) }}</span>
                                    <span>•</span>
                                    <span>{{ \App\Models\TransactionType::getLabel($listing->transaction_type) }}</span>
                                    <span>•</span>
                                    <span>{{ $listing->city }}, {{ $listing->state }}</span>
                                    <span>•</span>
                                    <span class="font-medium text-blue-600 dark:text-blue-400">{{ $listing->currency }} {{ number_format($listing->price, 0) }}</span>
                                </div>
                                @if($listing->latest_match_at)
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        {{ __('dashboard.matches_section.last_match') }}: {{ \Carbon\Carbon::parse($listing->latest_match_at)->diffForHumans() }}
                                    </p>
                                @endif
                            </div>

                            {{-- Badges + acción --}}
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-center">
                                    <span class="block text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $listing->match_count }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('dashboard.matches_section.matches') }}</span>
                                </div>
                                <a href="{{ route('dashboard.matches.show', $listing) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                                    {{ __('dashboard.matches_section.see_all') }}
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>

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
