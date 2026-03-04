<x-layouts.app>
    <x-app.container>

        <x-app.heading
            title="Mis Contactos"
            description="Usuarios que contactaron tus anuncios por WhatsApp o teléfono."
        />

        @if(session('success'))
            <div class="mb-4 px-4 py-3 text-sm text-green-800 bg-green-100 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if($filterListing)
            <div class="mb-4 flex items-center gap-3 px-4 py-3 bg-orange-50 border border-orange-200 rounded-lg dark:bg-orange-900/20 dark:border-orange-800">
                <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                <span class="text-sm text-orange-800 dark:text-orange-300">
                    Mostrando contactos de: <strong>{{ $filterListing->title }}</strong>
                </span>
                <a href="{{ route('dashboard.contacts.index') }}" class="ml-auto text-xs text-orange-600 hover:underline dark:text-orange-400">
                    Ver todos
                </a>
            </div>
        @endif

        @if($contacts->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-gray-100 rounded-full dark:bg-gray-800">
                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Sin contactos todavía</h3>
                <p class="mt-1 text-gray-500 dark:text-gray-400">Cuando alguien contacte uno de tus anuncios aparecerá aquí.</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Anuncio</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Visitante</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Acción</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($contacts as $contact)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                {{-- Anuncio --}}
                                <td class="px-4 py-3">
                                    @php
                                        $seoService = app(\App\Services\SeoService::class);
                                        $listingUrl = $contact->listing ? $seoService->generatePropertyUrl($contact->listing, app()->getLocale()) : null;
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        @if($contact->listing?->primaryImage)
                                            <a href="{{ $listingUrl }}" target="_blank" class="flex-shrink-0">
                                                <img src="{{ Storage::url($contact->listing->primaryImage->image_path) }}"
                                                     alt="{{ $contact->listing->title }}"
                                                     class="w-12 h-12 rounded-lg object-cover hover:opacity-80 transition-opacity">
                                            </a>
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-gray-200 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            @if($listingUrl)
                                                <a href="{{ $listingUrl }}" target="_blank" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400 truncate max-w-[180px] block">
                                                    {{ $contact->listing->title }}
                                                </a>
                                            @else
                                                <p class="text-sm font-medium text-gray-400 dark:text-gray-500 truncate max-w-[180px]">Anuncio eliminado</p>
                                            @endif
                                            <p class="text-xs text-gray-500 truncate max-w-[180px]">
                                                {{ $contact->listing?->city }}, {{ $contact->listing?->country }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Visitante --}}
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $contact->visitor?->name ?? 'Usuario eliminado' }}</p>
                                    @if($contact->visitor?->email)
                                        <a href="mailto:{{ $contact->visitor->email }}" class="text-xs text-blue-600 hover:underline dark:text-blue-400">{{ $contact->visitor->email }}</a>
                                    @endif
                                    @if($contact->visitor?->movil)
                                        @if($contact->action === 'whatsapp')
                                            <p class="text-xs mt-0.5">
                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contact->visitor->movil) }}" target="_blank" class="text-green-600 hover:underline dark:text-green-400">{{ $contact->visitor->movil }}</a>
                                            </p>
                                        @else
                                            <p class="text-xs mt-0.5">
                                                <a href="tel:{{ $contact->visitor->movil }}" class="text-gray-600 hover:underline dark:text-gray-400">{{ $contact->visitor->movil }}</a>
                                            </p>
                                        @endif
                                    @endif
                                </td>

                                {{-- Acción --}}
                                <td class="px-4 py-3">
                                    @if($contact->action === 'whatsapp')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                            WhatsApp
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            Teléfono
                                        </span>
                                    @endif
                                </td>

                                {{-- Fecha --}}
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $contact->created_at->format('d/m/Y H:i') }}
                                    <p class="text-xs text-gray-400">{{ $contact->created_at->diffForHumans() }}</p>
                                </td>

                                {{-- Notas (segunda fila, ancho completo) --}}
                                <tr class="bg-gray-50 dark:bg-gray-800/40">
                                    <td colspan="4" class="px-4 pb-3 pt-1">
                                        <form action="{{ route('dashboard.contacts.update-notes', $contact) }}" method="POST" class="flex gap-2 items-start">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="notes"
                                                      rows="2"
                                                      placeholder="Agregar nota sobre este contacto..."
                                                      class="flex-1 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none">{{ $contact->notes }}</textarea>
                                            <button type="submit"
                                                    class="px-3 py-2 text-xs font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors whitespace-nowrap self-end">
                                                Guardar nota
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $contacts->links() }}
            </div>
        @endif

    </x-app.container>
</x-layouts.app>
