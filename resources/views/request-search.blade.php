<x-layouts.marketing :seo="$seo ?? null">
    
    <!-- Hero Section with Search -->
    <section class="relative bg-gradient-to-r from-gray-100 to-gray-200 py-10">
        <div class="absolute inset-0 bg-white/10"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    {{ __('dashboard.requests.search_title') }}
                </h1>
                <p class="text-xl text-gray-700 mb-8 max-w-3xl mx-auto">
                    {{ __('dashboard.requests.search_subtitle') }}
                </p>
                
                @if (!$canSearch)
                    <!-- Mensaje de Membresía Requerida -->
                    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-8">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="ml-4 text-left">
                                <h3 class="text-xl font-medium text-yellow-800">
                                    Membresía Premium Requerida
                                </h3>
                                <div class="mt-3 text-sm text-yellow-700">
                                    <p class="mb-3">Para buscar solicitudes de clientes necesitas una membresía premium. Las membresías te permiten:</p>
                                    <ul class="list-disc list-inside space-y-2">
                                        <li>Buscar clientes potenciales ilimitados</li>
                                        <li>Contactar directamente a clientes interesados</li>
                                        <li>Recibir notificaciones de nuevas solicitudes</li>
                                        <li>Acceso a búsqueda inteligente con IA</li>
                                        <li>Ver información completa de contacto</li>
                                    </ul>
                                </div>
                                <div class="mt-6">
                                    @auth
                                        <a href="{{ route('settings.subscription') }}" class="inline-flex items-center px-6 py-3 text-base font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                            </svg>
                                            Obtener Membresía Premium
                                        </a>
                                    @else
                                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 text-base font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Iniciar Sesión
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                <!-- Search Form -->
                <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-2xl p-6">
                    <form method="GET" action="{{ route('requests.search') }}" class="space-y-4" id="requestSearchForm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Country Selection -->
                            <div class="md:col-span-1">
                                <label for="country" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                    País <span class="text-red-500">*</span>
                                    <span class="text-gray-500 text-xs">(obligatorio)</span>
                                </label>
                                <select name="country" id="country" class="w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200" required>
                                    <option value="">Selecciona un país</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country }}" {{ $selectedCountry === $country ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- Search Input -->
                            <div class="md:col-span-2">
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2 text-left">
                                    ¿Qué tipo de propiedad buscan? <span class="text-red-500">*</span>
                                    <span class="text-gray-500 text-xs">(mínimo 5 caracteres)</span>
                                </label>
                                <div class="relative">
                                    <input 
                                        type="text" 
                                        name="search" 
                                        id="search"
                                        value="{{ $searchTerm }}"
                                        placeholder="Ej: casa con jardín, departamento céntrico, terreno..." 
                                        class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                        required
                                        minlength="5"
                                    >
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <div id="charCounter" class="absolute -bottom-5 right-0 text-xs text-gray-500">
                                        <span id="charCount">0</span>/5 caracteres mínimos
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button 
                                type="submit" 
                                id="searchButton"
                                class="inline-flex items-center justify-center px-8 py-3 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 min-w-[140px] disabled:bg-blue-400 disabled:cursor-not-allowed transition-colors duration-200"
                            >
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                Buscar
                            </button>
                            
                            @if($searchTerm || $selectedCountry)
                                <a 
                                    href="{{ route('requests.search') }}" 
                                    class="inline-flex items-center px-6 py-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </form>
                    
                    <!-- Validation Errors -->
                    @if(!empty($validationErrors) && $isSearchRequest)
                        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">
                                        Revisa los siguientes campos:
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            @foreach($validationErrors as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </section>

    <!-- Results Section -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Results Header -->
            @if($totalResults > 0)
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">
                        Resultados de búsqueda
                    </h2>
                    <p class="text-gray-600">
                        {{ number_format($totalResults) }} {{ $totalResults === 1 ? 'solicitud encontrada' : 'solicitudes encontradas' }}
                        @if($selectedCountry)
                            en {{ $selectedCountry }}
                        @endif
                        @if($searchTerm)
                            para "{{ $searchTerm }}"
                        @endif
                        @if(isset($searchTime) && $searchTime > 0)
                            <span class="text-sm text-gray-500">
                                • {{ $searchTime }}ms
                                @if($searchTerm)
                                    <span class="inline-flex items-center ml-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        IA
                                    </span>
                                @endif
                            </span>
                        @endif
                    </p>
                </div>
            @endif

            <!-- Request Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($requests as $propertyRequest)
                    <!-- Request Card -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                                        {{ $propertyRequest->title }}
                                    </h3>
                                    
                                    <!-- Badges -->
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-blue-600 rounded">
                                            {{ ucfirst($propertyRequest->transaction_type) }}
                                        </span>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold text-white bg-purple-600 rounded">
                                            {{ ucfirst($propertyRequest->property_type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <p class="text-sm text-gray-600 mb-4 line-clamp-3">
                                {{ $propertyRequest->description }}
                            </p>
                            
                            <!-- Location -->
                            <p class="text-sm text-gray-600 mb-3 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                </svg>
                                {{ $propertyRequest->city }}, {{ $propertyRequest->state }}, {{ $propertyRequest->country }}
                            </p>
                            
                            <!-- Budget -->
                            <div class="mb-4">
                                <span class="text-lg font-bold text-green-600">
                                    {{ $propertyRequest->currency }} 
                                    @if($propertyRequest->min_budget)
                                        {{ number_format($propertyRequest->min_budget) }} - 
                                    @endif
                                    {{ number_format($propertyRequest->max_budget) }}
                                </span>
                            </div>
                            
                            <!-- Requirements -->
                            @if($propertyRequest->min_bedrooms || $propertyRequest->min_bathrooms || $propertyRequest->min_area)
                                <div class="grid grid-cols-3 gap-2 text-sm text-gray-600 mb-4">
                                    @if($propertyRequest->min_bedrooms)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z" />
                                            </svg>
                                            {{ $propertyRequest->min_bedrooms }}+
                                        </div>
                                    @endif
                                    @if($propertyRequest->min_bathrooms)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h8a2 2 0 012 2v10a2 2 0 002 2H4a2 2 0 01-2-2V5zm3 1h6v4H5V6zm6 6H5v2h6v-2z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $propertyRequest->min_bathrooms }}+
                                        </div>
                                    @endif
                                    @if($propertyRequest->min_area)
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12zm-9 7a1 1 0 012 0v1.586l2.293-2.293a1 1 0 111.414 1.414L6.414 15H8a1 1 0 010 2H4a1 1 0 01-1-1v-4zm13-1a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 010-2h1.586l-2.293-2.293a1 1 0 111.414-1.414L15 13.586V12a1 1 0 011-1z" clip-rule="evenodd" />
                                            </svg>
                                            {{ number_format($propertyRequest->min_area) }}+ m²
                                        </div>
                                    @endif
                                </div>
                            @endif
                            
                            <!-- Similarity Score -->
                            @if($searchTerm && isset($propertyRequest->similarity))
                                <div class="mb-4">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Relevancia:</span>
                                        <span class="font-medium text-green-600">{{ number_format($propertyRequest->similarity, 0) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-green-600 h-2 rounded-full" style="width: {{ $propertyRequest->similarity }}%"></div>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Contact Info (Oculto - solo para usuarios autenticados) -->
                            @auth
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <p class="text-xs text-gray-500 mb-2">
                                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        Inicia sesión para ver datos de contacto
                                    </p>
                                </div>
                            @endauth
                            
                            <!-- Expiration Date -->
                            @if($propertyRequest->expires_at)
                                <p class="text-xs text-gray-500 mt-2">
                                    Válida hasta: {{ $propertyRequest->expires_at->format('d/m/Y') }}
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    @if($searchTerm || $selectedCountry)
                        <!-- No Results -->
                        <div class="col-span-full">
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron solicitudes</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Intenta con otros términos de búsqueda o cambia el país seleccionado.
                                </p>
                            </div>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </section>

    <!-- Character counter script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const charCount = document.getElementById('charCount');
            const charCounter = document.getElementById('charCounter');
            
            if (searchInput && charCount) {
                function updateCounter() {
                    const length = searchInput.value.length;
                    charCount.textContent = length;
                    
                    if (length < 5) {
                        charCounter.classList.add('text-red-500');
                        charCounter.classList.remove('text-green-500');
                    } else {
                        charCounter.classList.add('text-green-500');
                        charCounter.classList.remove('text-red-500');
                    }
                }
                
                searchInput.addEventListener('input', updateCounter);
                updateCounter();
            }
        });
    </script>

</x-layouts.marketing>
