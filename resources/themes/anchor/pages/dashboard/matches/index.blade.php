<x-layouts.app>
    <x-app.container class="lg:space-y-6">
        
        <x-app.heading
            :title="__('dashboard.matches_section.title')"
            :description="__('dashboard.matches_section.description')"
            :border="false"
        />

        @if($allMatches->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('dashboard.matches_section.no_matches') }}</h3>
                <p class="text-gray-600 mb-4">{{ __('dashboard.matches_section.no_matches_desc') }}</p>
                <a href="/dashboard/property-listings" 
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

                    <div class="bg-white rounded-lg shadow border border-gray-200">
                        <!-- Listing Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $listing->title }}</h3>
                                    <div class="flex items-center gap-4 text-sm text-gray-600">
                                        <span class="capitalize">{{ $listing->property_type }}</span>
                                        <span>•</span>
                                        <span class="capitalize">{{ $listing->transaction_type }}</span>
                                        <span>•</span>
                                        <span>{{ $listing->city }}, {{ $listing->state }}</span>
                                        <span>•</span>
                                        <span class="font-semibold text-blue-600">{{ $listing->currency }} {{ number_format($listing->price, 0) }}</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <span class="px-4 py-2 bg-purple-100 text-purple-800 text-sm font-medium rounded-full">
                                        {{ trans_choice('dashboard.matches_section.match_count', $matches->count(), ['count' => $matches->count()]) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Matches Grid -->
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($matches as $request)
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-sm transition-all">
                                        <!-- Match Level Badge -->
                                        <div class="flex justify-between items-start mb-3">
                                            @if($request->match_level === 'exact')
                                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                                    {{ __('dashboard.matches_section.exact_match') }}
                                                </span>
                                            @elseif($request->match_level === 'semantic')
                                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                                    {{ __('dashboard.matches_section.semantic_match') }}
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                                    {{ __('dashboard.matches_section.flexible_match') }}
                                                </span>
                                            @endif
                                            <span class="text-xs text-gray-500">{{ $request->match_score }}%</span>
                                        </div>

                                        <!-- Request Title -->
                                        <h4 class="font-medium text-gray-900 mb-2">{{ $request->title }}</h4>
                                        <p class="text-sm text-gray-600 line-clamp-2 mb-3">{{ $request->description }}</p>

                                        <!-- Budget & Location -->
                                        <div class="flex items-center gap-3 text-xs text-gray-600 mb-3">
                                            <span>{{ $request->budget_range }}</span>
                                            <span>•</span>
                                            <span>{{ $request->city ?? $request->state }}</span>
                                        </div>

                                        <!-- Match Details -->
                                        @if(!empty($request->match_details))
                                            <div class="mb-3 p-2 bg-gray-50 rounded text-xs text-gray-600">
                                                <strong>{{ __('dashboard.matches_section.reasons') }}:</strong>
                                                <ul class="list-disc list-inside mt-1">
                                                    @foreach(array_slice($request->match_details, 0, 2) as $detail)
                                                        <li>{{ $detail }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <!-- User Info -->
                                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                            <div class="flex items-center gap-2">
                                                @if($request->user->avatar)
                                                    <img src="{{ $request->user->avatar }}" 
                                                         alt="{{ $request->user->name }}"
                                                         class="w-6 h-6 rounded-full">
                                                @else
                                                    <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center text-xs font-medium text-gray-600">
                                                        {{ substr($request->user->name, 0, 1) }}
                                                    </div>
                                                @endif
                                                <span class="text-sm text-gray-700">{{ $request->user->name }}</span>
                                            </div>
                                            
                                            @if($request->user->email)
                                                <a href="mailto:{{ $request->user->email }}" 
                                                   class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                                    {{ __('dashboard.matches_section.contact_requester') }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- View All Link -->
                            @if($matches->count() >= 5)
                                <div class="mt-4 text-center">
                                    <a href="{{ route('dashboard.matches.show', $listing) }}" 
                                       class="text-sm text-blue-600 hover:text-blue-700 font-medium">
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
