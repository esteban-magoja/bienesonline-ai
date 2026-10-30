@php
    $mapTexts = [
        'searchThisArea' => __('properties.map_view.search_this_area'),
        'inAreaOne' => __('properties.map_view.in_area_one'),
        'inAreaOther' => __('properties.map_view.in_area_other'),
        'truncated' => __('properties.map_view.truncated'),
        'viewDetails' => __('properties.map_view.view_details'),
        'priceOnRequest' => __('properties.map_view.price_on_request'),
        'bedsShort' => __('properties.map_view.beds_short'),
        'bathsShort' => __('properties.map_view.baths_short'),
        'featured' => __('properties.search_results.featured'),
        'thisListing' => __('properties.map_view.this_listing'),
    ];
@endphp

<x-layouts.marketing :seo="$seo">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"
        integrity="sha256-YU3qCpj/P06tdPBJGPax0bm6Q1wltfwjsho5TR4+TYc="
        crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"
        integrity="sha256-YSWCMtmNZNwqex4CEw1nQhvFub2lmU7vcCKP+XVwwXA="
        crossorigin=""/>

    <style>
        .map-layout { display: flex; flex-direction: column; }
        .map-panel { position: relative; height: 70vh; min-height: 420px; }
        .map-canvas { position: absolute; inset: 0; z-index: 0; }
        .map-list { padding: 1rem; }

        @media (max-width: 1023px) {
            .map-layout[data-mobile-view="map"] .map-list { display: none; }
            .map-layout[data-mobile-view="list"] .map-panel { display: none; }
        }

        @media (min-width: 1024px) {
            .map-layout { display: grid; grid-template-columns: 420px 1fr; height: calc(100vh - 150px); min-height: 560px; }
            .map-panel { height: auto; min-height: 0; }
            .map-list { overflow-y: auto; border-right: 1px solid #e5e7eb; }
        }

        /* Marcador con precio: el div del icono mide 0x0 y la etiqueta queda centrada arriba del punto */
        .price-marker { background: transparent; border: none; }
        .price-pill {
            position: absolute; left: 0; bottom: 0; transform: translate(-50%, -6px);
            white-space: nowrap; padding: 3px 8px; border-radius: 9999px;
            background: #fff; color: #111827; border: 1px solid #d1d5db;
            font: 600 12px/1.3 ui-sans-serif, system-ui, sans-serif;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .25); cursor: pointer;
        }
        .price-pill::after {
            content: ''; position: absolute; left: 50%; bottom: -5px; transform: translateX(-50%);
            border: 5px solid transparent; border-bottom: 0; border-top-color: #fff;
        }
        .price-pill.is-featured { border-color: #eab308; }
        .price-marker.is-active .price-pill, .price-pill:hover { background: #2563eb; color: #fff; border-color: #2563eb; }
        .price-marker.is-active .price-pill::after, .price-pill:hover::after { border-top-color: #2563eb; }
        .marker-cluster.is-active div { background-color: #2563eb; color: #fff; }

        /* Anuncio desde el que se llegó al mapa (?highlight=ID): rojo, más grande y con halo */
        .price-pill.is-origin { background: #dc2626; color: #fff; border-color: #dc2626; font-size: 13px; padding: 4px 10px; box-shadow: 0 0 0 4px rgba(220, 38, 38, .25), 0 2px 6px rgba(0, 0, 0, .3); }
        .price-pill.is-origin::after { border-top-color: #dc2626; }

        .map-popup .leaflet-popup-content-wrapper { padding: 0; border-radius: 10px; overflow: hidden; }
        .map-popup .leaflet-popup-content { margin: 0; width: 240px !important; }
        .map-popup-card { display: block; color: inherit; text-decoration: none; }
        .map-popup-card img, .map-popup-card .map-popup-placeholder { display: block; width: 100%; height: 130px; object-fit: cover; background: #e5e7eb; }
        .map-popup-body { padding: 10px 12px 12px; font-size: 13px; line-height: 1.35; color: #4b5563; }
        .map-popup-price { font-size: 16px; font-weight: 700; color: #2563eb; }
        .map-popup-title { margin: 2px 0 4px; font-weight: 600; color: #111827; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .map-popup-cta { display: inline-block; margin-top: 6px; font-weight: 600; color: #2563eb; }
    </style>

    <div x-data="listingMap(@js($mapConfig), @js($mapTexts))">
        {{-- Encabezado --}}
        <div class="bg-white border-b">
            <div class="container mx-auto px-4 py-4">
                <nav aria-label="Breadcrumb" class="mb-2">
                    <ol class="flex flex-wrap items-center gap-1 text-sm">
                        @foreach($breadcrumbs as $index => $breadcrumb)
                            <li class="inline-flex items-center">
                                @if($index > 0)
                                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                                @if($breadcrumb['url'])
                                    <a href="{{ $breadcrumb['url'] }}" class="font-medium text-gray-700 hover:text-blue-600">{{ $breadcrumb['label'] }}</a>
                                @else
                                    <span class="font-medium text-gray-500">{{ $breadcrumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $seo['title'] }}</h1>
                        <p class="text-sm text-gray-600">
                            {{ trans_choice('properties.results.found', $total, ['count' => $total]) }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ $listUrl }}"
                           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            {{ __('properties.map_view.view_list') }}
                        </a>
                    </div>
                </div>

                {{-- Navegación en selects compactos. Cada sección: title, items [label, url, count?, selected?]
                     y all_url opcional (la primera opción quita el filtro en lugar de ser solo el título) --}}
                @if(!empty($navigationSections))
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        @foreach($navigationSections as $section)
                            <label class="sr-only" for="map-nav-{{ $loop->index }}">{{ $section['title'] }}</label>
                            <select id="map-nav-{{ $loop->index }}" data-map-nav
                                    onchange="if (this.value) window.location.href = this.value"
                                    class="w-full sm:w-auto sm:min-w-52 rounded-lg border-gray-300 bg-white py-2 pl-2.5 pr-8 text-xs sm:pl-3 sm:text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="{{ $section['all_url'] ?? '' }}" @selected(!collect($section['items'])->contains('selected', true))>{{ $section['title'] }}</option>
                                @foreach($section['items'] as $item)
                                    <option value="{{ $item['url'] }}" @selected($item['selected'] ?? false)>{{ $item['label'] }}@isset($item['count']) ({{ $item['count'] }})@endisset</option>
                                @endforeach
                            </select>
                        @endforeach
                    </div>
                @endif

                @if($total > $mappableCount)
                    <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
                        {{ trans_choice('properties.map_view.without_location', $total - $mappableCount, ['count' => $total - $mappableCount]) }}
                        <a href="{{ $listUrl }}" class="font-semibold underline hover:no-underline">{{ __('properties.map_view.see_in_list') }}</a>
                    </p>
                @endif
            </div>
        </div>

        {{-- Lista + mapa --}}
        <div class="map-layout bg-gray-50" :data-mobile-view="mobileView">
            <aside class="map-list" aria-live="polite">
                <p class="mb-3 text-sm font-medium text-gray-700" x-show="!loading && !error" x-text="countLabel()"></p>
                <p class="mb-3 text-sm text-gray-500" x-show="loading">{{ __('properties.map_view.loading') }}</p>
                <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700" x-show="error" x-cloak>{{ __('properties.map_view.error') }}</p>
                <p class="mb-3 rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-800" x-show="!loading && total > listings.length" x-cloak x-text="truncatedLabel()"></p>
                <p class="rounded-lg bg-white p-6 text-center text-sm text-gray-600 shadow-sm" x-show="!loading && !error && listings.length === 0" x-cloak>
                    {{ __('properties.map_view.no_results_area') }}
                </p>

                <div class="flex flex-col gap-3">
                    <template x-for="listing in listings.slice(0, visibleCount)" :key="listing.id">
                        <a :href="listing.url"
                           @mouseenter="highlight(listing.id, true)" @mouseleave="highlight(listing.id, false)"
                           class="flex overflow-hidden rounded-lg bg-white shadow-sm transition hover:shadow-md"
                           :class="isOrigin(listing) ? 'ring-2 ring-red-500' : 'ring-1 ring-gray-200 hover:ring-blue-300'">
                            <div class="relative w-32 flex-shrink-0 bg-gray-200">
                                <template x-if="listing.image">
                                    <img :src="listing.image" :alt="listing.title" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                                </template>
                                <span x-show="listing.featured" class="absolute left-1 top-1 rounded-full bg-yellow-500 px-2 py-0.5 text-[10px] font-bold text-white" x-text="texts.featured"></span>
                            </div>
                            <div class="min-w-0 flex-1 p-3">
                                <span x-show="isOrigin(listing)" class="mb-1 inline-block rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white" x-text="texts.thisListing"></span>
                                <p class="text-base font-bold text-blue-600" x-text="fullPrice(listing)"></p>
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900" x-text="listing.title"></p>
                                <p class="mt-1 text-xs text-gray-600" x-text="[listing.type, listing.operation].filter(Boolean).join(' · ')"></p>
                                <p class="text-xs text-gray-600" x-text="features(listing)"></p>
                                <p class="mt-1 truncate text-xs text-gray-500" x-text="listing.location"></p>
                            </div>
                        </a>
                    </template>
                </div>

                <button type="button" x-show="visibleCount < listings.length" x-cloak @click="visibleCount += 20"
                        class="mt-4 w-full rounded-md bg-white py-2 text-sm font-medium text-blue-700 ring-1 ring-gray-200 hover:bg-blue-50">
                    {{ __('properties.map_view.load_more') }}
                </button>
            </aside>

            <div class="map-panel">
                <div x-ref="map" class="map-canvas"></div>

                <div class="pointer-events-none absolute inset-x-0 top-3 z-[1000] flex justify-center">
                    <button type="button" x-show="areaDirty && !loading" x-cloak @click="load(true)"
                            class="pointer-events-auto inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-lg ring-1 ring-gray-200 hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        {{ __('properties.map_view.search_this_area') }}
                    </button>
                    <span x-show="loading" x-cloak class="rounded-full bg-white px-4 py-2 text-sm text-gray-700 shadow-lg ring-1 ring-gray-200">
                        {{ __('properties.map_view.loading') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Alternar mapa/lista en móviles --}}
        <div class="fixed inset-x-0 bottom-5 z-[1100] flex justify-center lg:hidden">
            <button type="button" @click="toggleMobileView()"
                    class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-5 py-3 text-sm font-semibold text-white shadow-xl">
                <span x-show="mobileView === 'map'">{{ __('properties.map_view.show_list') }} (<span x-text="listings.length"></span>)</span>
                <span x-show="mobileView === 'list'" x-cloak>{{ __('properties.map_view.show_map') }}</span>
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"
        integrity="sha256-Hk4dIpcqOSb0hZjgyvFOP+cEmDXUKKNE/tT542ZbNQg="
        crossorigin=""></script>

    <script>
        // Al volver con "atrás" el navegador restaura la opción elegida: se vuelve a la opción original
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-map-nav]').forEach((select) => {
                const original = [...select.options].find((option) => option.defaultSelected);
                select.value = original ? original.value : '';
            });
        });

        function listingMap(config, texts) {
            // Los objetos de Leaflet quedan fuera del estado de Alpine (no deben ser reactivos)
            let map = null;
            let cluster = null;
            let markersById = {};
            let originMarker = null;
            let suppressMoveEvents = false;
            let request = null;

            const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));
            const thousands = (value) => Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const decimalSeparator = config.locale === 'en' ? '.' : ',';

            // Movimientos hechos por código (encuadre inicial, redimensionar): no muestran "Buscar en esta zona"
            const silently = (callback) => {
                suppressMoveEvents = true;
                try { callback(); } finally { suppressMoveEvents = false; }
            };

            return {
                texts,
                listings: [],
                total: 0,
                loading: false,
                error: false,
                areaDirty: false,
                visibleCount: 20,
                mobileView: 'map',

                // El mapa se crea recién cuando el contenedor tiene tamaño (el body arranca con x-cloak
                // y en móviles puede estar oculto); después cada cambio de tamaño recalcula el mapa
                init() {
                    const container = this.$refs.map;
                    new ResizeObserver(() => {
                        if (!container.clientWidth || !container.clientHeight) {
                            return;
                        }
                        if (map) {
                            silently(() => map.invalidateSize({ pan: false }));
                        } else {
                            this.createMap(container);
                        }
                    }).observe(container);
                },

                createMap(container) {
                    map = L.map(container, { zoomControl: true });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(map);
                    L.control.scale({ imperial: false }).addTo(map);

                    cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 50 });
                    map.addLayer(cluster);

                    map.on('moveend', () => {
                        if (!suppressMoveEvents) {
                            this.areaDirty = true;
                        }
                    });

                    if (config.center) {
                        silently(() => map.setView([config.center.lat, config.center.lng], config.center.zoom, { animate: false }));
                        this.load(true);
                    } else {
                        silently(() => map.setView([-15, -60], 3, { animate: false }));
                        this.load(false);
                    }
                },

                async load(useBounds) {
                    request?.abort();
                    request = new AbortController();

                    const params = new URLSearchParams(config.params);
                    if (useBounds) {
                        const bounds = map.getBounds();
                        const clamp = (value, limit) => Math.max(-limit, Math.min(limit, value)).toFixed(5);
                        params.set('bbox', [
                            clamp(bounds.getSouth(), 90), clamp(bounds.getWest(), 180),
                            clamp(bounds.getNorth(), 90), clamp(bounds.getEast(), 180),
                        ].join(','));
                    }

                    this.loading = true;
                    this.error = false;
                    this.areaDirty = false;

                    try {
                        const response = await fetch(`${config.dataUrl}?${params}`, {
                            headers: { Accept: 'application/json' },
                            signal: request.signal,
                        });
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        const data = await response.json();

                        // El anuncio destacado va primero en la lista
                        const listings = [...data.listings].sort((a, b) => this.isOrigin(b) - this.isOrigin(a));

                        this.total = data.total;
                        this.listings = listings;
                        this.visibleCount = 20;
                        this.renderMarkers(listings);

                        if (useBounds) {
                            this.rememberArea();
                        } else if (listings.length) {
                            const bounds = cluster.getBounds();
                            if (originMarker) {
                                bounds.extend(originMarker.getLatLng());
                            }
                            silently(() => map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15, animate: false }));
                        }
                    } catch (exception) {
                        if (exception.name !== 'AbortError') {
                            this.error = true;
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                renderMarkers(listings) {
                    cluster.clearLayers();
                    originMarker?.remove();
                    originMarker = null;
                    markersById = {};

                    const markers = listings.map((listing) => {
                        const pillClass = this.isOrigin(listing) ? ' is-origin' : (listing.featured ? ' is-featured' : '');
                        const marker = L.marker([listing.lat, listing.lng], {
                            icon: L.divIcon({
                                className: 'price-marker',
                                html: `<span class="price-pill${pillClass}">${escapeHtml(this.shortPrice(listing))}</span>`,
                                iconSize: [0, 0],
                                iconAnchor: [0, 0],
                                popupAnchor: [0, -30],
                            }),
                            title: listing.title,
                        });
                        marker.bindPopup(() => this.popupHtml(listing), { className: 'map-popup', minWidth: 240, maxWidth: 240 });
                        markersById[listing.id] = marker;

                        return marker;
                    });

                    // El destacado queda fuera del agrupado para que siempre se vea
                    originMarker = markers.find((marker, index) => this.isOrigin(listings[index])) ?? null;
                    originMarker?.setZIndexOffset(2000).addTo(map);
                    cluster.addLayers(markers.filter((marker) => marker !== originMarker));
                },

                isOrigin(listing) {
                    return config.highlight != null && listing.id === config.highlight;
                },

                highlight(id, active) {
                    const marker = markersById[id];
                    if (!marker) {
                        return;
                    }
                    if (marker === originMarker) {
                        marker.getElement()?.classList.toggle('is-active', active);
                        return;
                    }
                    const visible = cluster.getVisibleParent(marker);
                    visible?.getElement()?.classList.toggle('is-active', active);
                    if (visible === marker) {
                        marker.setZIndexOffset(active ? 1000 : 0);
                    }
                },

                popupHtml(listing) {
                    const image = listing.image
                        ? `<img src="${escapeHtml(listing.image)}" alt="" loading="lazy">`
                        : '<span class="map-popup-placeholder"></span>';
                    const meta = [listing.type, listing.operation].filter(Boolean).join(' · ');

                    return `<a class="map-popup-card" href="${escapeHtml(listing.url)}">${image}
                        <span class="map-popup-body" style="display:block">
                            <span class="map-popup-price" style="display:block">${escapeHtml(this.fullPrice(listing))}</span>
                            <span class="map-popup-title">${escapeHtml(listing.title)}</span>
                            <span style="display:block">${escapeHtml(meta)}</span>
                            <span style="display:block">${escapeHtml(this.features(listing))}</span>
                            <span style="display:block">${escapeHtml(listing.location)}</span>
                            <span class="map-popup-cta">${escapeHtml(texts.viewDetails)} →</span>
                        </span></a>`;
                },

                shortPrice(listing) {
                    if (!listing.price) {
                        return texts.priceOnRequest;
                    }
                    const price = listing.price;
                    let label = thousands(price);
                    if (price >= 1e6) {
                        label = (Math.round(price / 1e5) / 10).toString().replace('.', decimalSeparator) + 'M';
                    } else if (price >= 1e4) {
                        label = Math.round(price / 1e3) + 'K';
                    }

                    return [listing.currency, label].filter(Boolean).join(' ');
                },

                fullPrice(listing) {
                    return listing.price
                        ? [listing.currency, thousands(listing.price)].filter(Boolean).join(' ')
                        : texts.priceOnRequest;
                },

                features(listing) {
                    return [
                        listing.bedrooms ? texts.bedsShort.replace(':count', listing.bedrooms) : null,
                        listing.bathrooms ? texts.bathsShort.replace(':count', listing.bathrooms) : null,
                        listing.area ? `${thousands(listing.area)} m²` : null,
                    ].filter(Boolean).join(' · ');
                },

                countLabel() {
                    return (this.total === 1 ? texts.inAreaOne : texts.inAreaOther).replace(':count', thousands(this.total));
                },

                truncatedLabel() {
                    return texts.truncated
                        .replace(':shown', thousands(this.listings.length))
                        .replace(':total', thousands(this.total));
                },

                // Guarda la zona en la URL para poder recargar o compartir el mapa
                rememberArea() {
                    const url = new URL(window.location.href);
                    const center = map.getCenter();
                    url.searchParams.set('lat', center.lat.toFixed(5));
                    url.searchParams.set('lng', center.lng.toFixed(5));
                    url.searchParams.set('zoom', map.getZoom());
                    window.history.replaceState(null, '', url);
                },

                toggleMobileView() {
                    this.mobileView = this.mobileView === 'map' ? 'list' : 'map';
                    window.scrollTo({ top: 0 });
                },
            };
        }
    </script>
</x-layouts.marketing>
