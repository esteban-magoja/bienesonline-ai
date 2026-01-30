<x-layouts.app>
    <x-app.container class="lg:space-y-6">
        
        <x-app.heading
            :title="__('dashboard.messages_section.message')"
            :description="__('dashboard.messages_section.description')"
            :border="false"
        >
            <x-slot name="actions">
                <a href="{{ route('dashboard.messages.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    {{ __('dashboard.request_form.back') }}
                </a>
            </x-slot>
        </x-app.heading>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Message Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Header -->
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 border-b border-blue-200">
                        <div class="flex items-start justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">{{ $message->name }}</h2>
                                <p class="text-sm text-gray-600 mt-1">{{ $message->created_at->format('d/m/Y H:i') }} ({{ $message->created_at->diffForHumans() }})</p>
                            </div>
                            @if(!$message->is_read)
                                <span class="px-3 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                                    {{ __('dashboard.messages_section.new_badge') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Property Info -->
                    <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
                        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('dashboard.messages_section.property') }}:</h3>
                        <div class="flex items-start gap-4">
                            @if($message->propertyListing->primaryImage)
                                <img src="{{ $message->propertyListing->primaryImage->image_url }}" 
                                     alt="{{ $message->propertyListing->title }}"
                                     class="w-20 h-20 object-cover rounded-lg">
                            @endif
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900">{{ $message->propertyListing->title }}</h4>
                                <p class="text-sm text-gray-600">{{ $message->propertyListing->city }}, {{ $message->propertyListing->state }}</p>
                                <p class="text-sm font-semibold text-green-600 mt-1">
                                    {{ $message->propertyListing->currency }} {{ number_format($message->propertyListing->price) }}
                                </p>
                                @php
                                    $seoService = app(\App\Services\SeoService::class);
                                    $propertySlug = $seoService->generatePropertySlug($message->propertyListing);
                                @endphp
                                <a href="{{ route_localized('property.show', ['id' => $message->propertyListing->id, 'slug' => $propertySlug]) }}" 
                                   target="_blank"
                                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 mt-2">
                                    {{ __('properties.view_full_listing') }}
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Message Content -->
                    <div class="px-6 py-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('dashboard.messages_section.message') }}:</h3>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <p class="text-gray-800 whitespace-pre-line leading-relaxed">{{ $message->message }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-wrap gap-3">
                        <a href="mailto:{{ $message->email }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            {{ __('dashboard.messages_section.reply_email') }}
                        </a>

                        @if($message->phone)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $message->phone) }}?text={{ urlencode(__('dashboard.messages_section.whatsapp_greeting', ['name' => $message->name, 'property' => $message->propertyListing->title])) }}" 
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                {{ __('dashboard.messages_section.contact_whatsapp') }}
                            </a>

                            <a href="tel:{{ $message->phone }}" 
                               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                                {{ __('dashboard.messages_section.call') }}
                            </a>
                        @endif

                        @if(!$message->is_read)
                            <form action="{{ route('dashboard.messages.mark-read', $message->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 bg-orange-100 hover:bg-orange-200 text-orange-800 font-medium rounded-lg transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ __('dashboard.messages_section.mark_read') }}
                                </button>
                            </form>
                        @else
                            <form action="{{ route('dashboard.messages.mark-unread', $message->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    {{ __('dashboard.messages_section.mark_unread') }}
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('dashboard.messages.destroy', $message->id) }}" 
                              method="POST" 
                              class="inline ml-auto"
                              onsubmit="return confirm('{{ __('dashboard.messages_section.confirm_delete') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-red-100 hover:bg-red-200 text-red-800 font-medium rounded-lg transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                {{ __('dashboard.messages_section.delete') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Contact Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.messages_section.contact_info') }}</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600">{{ __('dashboard.messages_section.name') }}</label>
                            <p class="text-gray-900 font-medium">{{ $message->name }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-600">Email</label>
                            <p class="text-gray-900">
                                <a href="mailto:{{ $message->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $message->email }}
                                </a>
                            </p>
                            <button onclick="navigator.clipboard.writeText('{{ $message->email }}')" 
                                    class="text-xs text-gray-500 hover:text-gray-700 mt-1">
                                {{ __('dashboard.messages_section.copy_email') }}
                            </button>
                        </div>

                        @if($message->phone)
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('dashboard.messages_section.phone') }}</label>
                                <p class="text-gray-900">{{ $message->phone }}</p>
                                <button onclick="navigator.clipboard.writeText('{{ $message->phone }}')" 
                                        class="text-xs text-gray-500 hover:text-gray-700 mt-1">
                                    {{ __('dashboard.messages_section.copy_phone') }}
                                </button>
                            </div>
                        @endif

                        @if($message->user)
                            <div>
                                <label class="text-sm font-medium text-gray-600">{{ __('dashboard.messages_section.registered_user') }}</label>
                                <p class="text-gray-900">{{ __('dashboard.messages_section.yes') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Message Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.messages_section.message_status') }}</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ __('dashboard.messages_section.status') }}</span>
                            @if($message->is_read)
                                <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    {{ __('dashboard.messages_section.read') }}
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded-full">
                                    {{ __('dashboard.messages_section.unread') }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ __('dashboard.messages_section.received') }}</span>
                            <span class="text-sm text-gray-900">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">{{ __('dashboard.messages_section.time_ago') }}</span>
                            <span class="text-sm text-gray-900">{{ $message->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </x-app.container>
</x-layouts.app>
