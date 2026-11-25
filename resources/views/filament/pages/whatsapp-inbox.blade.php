<x-filament-panels::page>
    <div wire:poll.10s.visible="refreshInbox"
         class="grid grid-cols-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:grid-cols-[22rem_1fr] dark:border-white/10 dark:bg-gray-900"
         style="min-height: 70vh;">

        {{-- ── LISTA DE CONVERSACIONES ─────────────────────────────── --}}
        <aside class="{{ $conversation ? 'hidden lg:flex' : 'flex' }} flex-col border-gray-200 lg:border-r dark:border-white/10">
            <div class="space-y-2 border-b border-gray-200 p-3 dark:border-white/10">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar por nombre, email o número" />
                </x-filament::input.wrapper>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <x-filament::input.checkbox wire:model.live="onlyUnread" />
                    Solo no leídas
                </label>
            </div>

            <div class="flex-1 overflow-y-auto" style="max-height: 70vh;">
                @forelse ($conversations as $item)
                    <button type="button"
                            wire:key="conversation-{{ $item->id }}"
                            wire:click="openConversation({{ $item->id }})"
                            class="flex w-full items-start gap-3 border-b border-gray-100 px-3 py-3 text-left transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5 {{ $conversation?->id === $item->id ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                            {{ mb_strtoupper(mb_substr($item->displayName(), 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm {{ $item->unread_count > 0 ? 'font-bold text-gray-950 dark:text-white' : 'font-medium text-gray-800 dark:text-gray-200' }}">
                                    {{ $item->displayName() }}
                                </span>
                                <span class="shrink-0 text-xs text-gray-400">
                                    {{ $item->last_message_at?->isToday() ? $item->last_message_at->format('H:i') : $item->last_message_at?->format('d/m') }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item->last_message_preview }}</span>
                                @if ($item->unread_count > 0)
                                    <span class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-500 px-1.5 text-xs font-bold text-white">
                                        {{ $item->unread_count }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        @if ($search !== '' || $onlyUnread)
                            No hay conversaciones con ese filtro.
                        @else
                            Todavía no hay mensajes. Aparecerán aquí cuando los usuarios escriban o respondan al número de WhatsApp.
                        @endif
                    </div>
                @endforelse

                @if ($hasMoreConversations)
                    <div class="p-3 text-center">
                        <x-filament::link tag="button" wire:click="loadMoreConversations" size="sm">Ver más conversaciones</x-filament::link>
                    </div>
                @endif
            </div>
        </aside>

        {{-- ── CONVERSACIÓN ────────────────────────────────────────── --}}
        <section class="{{ $conversation ? 'flex' : 'hidden lg:flex' }} min-w-0 flex-col">
            @if ($conversation === null)
                <div class="flex flex-1 flex-col items-center justify-center gap-2 p-10 text-center text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-chat-bubble-oval-left-ellipsis" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm">Elige una conversación para ver los mensajes.</p>
                </div>
            @else
                {{-- Encabezado --}}
                <header class="flex flex-wrap items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <x-filament::icon-button icon="heroicon-o-arrow-left" wire:click="closeConversation" label="Volver" class="lg:hidden" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold text-gray-950 dark:text-white">{{ $conversation->displayName() }}</div>
                        <div class="flex flex-wrap items-center gap-x-3 text-xs text-gray-500 dark:text-gray-400">
                            <span>+{{ $conversation->wa_id }}</span>
                            @if ($conversation->profile_name && $conversation->user)
                                <span>WhatsApp: {{ $conversation->profile_name }}</span>
                            @endif
                            @if ($conversation->user)
                                <span>{{ $conversation->user->email }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($conversation->opted_out_at)
                            <x-filament::badge color="danger" tooltip="Pidió la baja el {{ $conversation->opted_out_at->format('d/m/Y H:i') }}">Pidió la baja</x-filament::badge>
                        @endif
                        @if ($conversation->user)
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-user" tag="a" target="_blank"
                                                :href="\App\Filament\Resources\UserManagement\UserManagementResource::getUrl('view', ['record' => $conversation->user])">
                                Ver usuario
                            </x-filament::button>
                        @else
                            <x-filament::badge color="gray">Sin usuario vinculado</x-filament::badge>
                        @endif
                    </div>
                </header>

                {{-- Mensajes --}}
                <div wire:key="chat-{{ $conversation->id }}-{{ $messages->last()?->id }}"
                     x-data
                     x-init="$el.scrollTop = $el.scrollHeight"
                     class="flex-1 space-y-2 overflow-y-auto bg-gray-50 px-4 py-4 dark:bg-gray-950/40"
                     style="max-height: 55vh;">
                    @if ($hasOlderMessages)
                        <div class="text-center">
                            <x-filament::link tag="button" wire:click="loadOlderMessages" size="sm">Ver mensajes anteriores</x-filament::link>
                        </div>
                    @endif

                    @foreach ($messages as $message)
                        @php
                            $inbound = $message->isInbound();
                            $quoted = $message->context_wamid ? $messages->firstWhere('wamid', $message->context_wamid) : null;
                            $log = $message->notificationLog;
                            $location = $message->type === 'location' ? ($message->payload['location'] ?? null) : null;
                        @endphp
                        <div wire:key="message-{{ $message->id }}" class="flex {{ $inbound ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm shadow-sm sm:max-w-[70%] {{ $inbound ? 'rounded-tl-sm bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100' : 'rounded-tr-sm bg-emerald-100 text-gray-900 dark:bg-emerald-900/60 dark:text-emerald-50' }}">

                                @if ($log)
                                    <div class="mb-1 rounded-md border-l-4 border-emerald-500 bg-black/5 px-2 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                        Responde al aviso <strong>{{ $log->template_name ?? $log->event_type }}</strong>
                                        del {{ $log->created_at?->format('d/m/Y H:i') }}
                                        @if ($log->propertyListing)
                                            <br>Anuncio: {{ \Illuminate\Support\Str::limit($log->propertyListing->title, 60) }} (#{{ $log->property_listing_id }})
                                        @endif
                                        @if ($log->propertyRequest)
                                            <br>Solicitud: {{ \Illuminate\Support\Str::limit($log->propertyRequest->title, 60) }} (#{{ $log->property_request_id }})
                                        @endif
                                    </div>
                                @elseif ($quoted)
                                    <div class="mb-1 rounded-md border-l-4 border-gray-400 bg-black/5 px-2 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                        {{ \Illuminate\Support\Str::limit($quoted->body ?? '[adjunto]', 120) }}
                                    </div>
                                @endif

                                @if ($message->type === 'template')
                                    <div class="mb-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Template: {{ $message->template_name }}</div>
                                @endif

                                @if ($message->hasMedia())
                                    @php($mediaUrl = route('whatsapp.media', $message))
                                    @if (in_array($message->type, ['image', 'sticker'], true))
                                        <a href="{{ $mediaUrl }}" target="_blank" rel="noopener">
                                            <img src="{{ $mediaUrl }}" alt="Imagen recibida" loading="lazy" class="mb-1 max-h-64 rounded-lg">
                                        </a>
                                    @elseif ($message->type === 'audio')
                                        <audio controls preload="none" src="{{ $mediaUrl }}" class="mb-1 max-w-full"></audio>
                                    @elseif ($message->type === 'video')
                                        <video controls preload="none" src="{{ $mediaUrl }}" class="mb-1 max-h-64 max-w-full rounded-lg"></video>
                                    @else
                                        <a href="{{ $mediaUrl }}?download=1" class="mb-1 inline-flex items-center gap-1 font-medium text-primary-600 underline dark:text-primary-400">
                                            <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                                            {{ $message->media_filename ?: 'Descargar archivo' }}
                                        </a>
                                    @endif
                                @elseif (in_array($message->type, \App\Models\WhatsAppMessage::MEDIA_TYPES, true))
                                    <div class="mb-1 text-xs italic text-gray-500">
                                        Adjunto ({{ $message->type }}) no disponible{{ $message->error_message ? ': '.$message->error_message : '' }}
                                    </div>
                                @endif

                                @if ($message->type === 'reaction')
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Reaccionó {{ $message->body }}</div>
                                @elseif ($location && isset($location['latitude'], $location['longitude']))
                                    <a href="https://www.openstreetmap.org/?mlat={{ $location['latitude'] }}&mlon={{ $location['longitude'] }}#map=16/{{ $location['latitude'] }}/{{ $location['longitude'] }}"
                                       target="_blank" rel="noopener" class="font-medium text-primary-600 underline dark:text-primary-400">
                                        📍 {{ $message->body }}
                                    </a>
                                @elseif (filled($message->body) && ! ($message->hasMedia() && $message->type === 'document' && $message->body === $message->media_filename))
                                    <div class="whitespace-pre-line break-words">{{ $message->body }}</div>
                                @elseif (! $message->hasMedia() && ! in_array($message->type, \App\Models\WhatsAppMessage::MEDIA_TYPES, true))
                                    <div class="text-xs italic text-gray-500">Tipo de mensaje no soportado ({{ $message->type }})</div>
                                @endif

                                <div class="mt-1 flex items-center justify-end gap-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    @if (! $inbound && $message->sender)
                                        <span>{{ $message->sender->name }} ·</span>
                                    @endif
                                    <span>{{ $message->sent_at?->format('d/m H:i') }}</span>
                                    @if (! $inbound)
                                        @switch($message->status)
                                            @case('read')
                                                <span class="font-bold text-sky-500" title="Leído">✓✓</span>
                                                @break
                                            @case('delivered')
                                                <span title="Entregado">✓✓</span>
                                                @break
                                            @case('failed')
                                                <span class="font-semibold text-danger-600" title="{{ $message->error_message }}">⚠ No enviado</span>
                                                @break
                                            @default
                                                <span title="Enviado">✓</span>
                                        @endswitch
                                    @endif
                                </div>
                                @if (! $inbound && $message->status === 'failed' && $message->error_message)
                                    <div class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message->error_message }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Respuesta --}}
                <footer class="border-t border-gray-200 p-3 dark:border-white/10">
                    @if ($conversation->isServiceWindowOpen())
                        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                            Puedes responder con texto libre hasta el {{ $conversation->serviceWindowExpiresAt()->format('d/m H:i') }}
                            ({{ $conversation->serviceWindowExpiresAt()->diffForHumans(['parts' => 2, 'short' => true]) }}).
                        </p>
                        <form wire:submit="sendReply" class="flex items-end gap-2">
                            <textarea wire:model="reply"
                                      rows="2"
                                      maxlength="4096"
                                      x-on:keydown.ctrl.enter.prevent="$wire.sendReply()"
                                      x-on:keydown.meta.enter.prevent="$wire.sendReply()"
                                      placeholder="Escribe una respuesta (Ctrl + Enter para enviar)"
                                      class="block w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendReply">
                                Enviar
                            </x-filament::button>
                        </form>
                        @error('reply')
                            <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                        @enderror
                    @else
                        <div class="mb-2 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                            Pasaron más de 24 horas desde el último mensaje del usuario: WhatsApp solo permite escribirle con un template aprobado.
                            Si responde, se vuelve a abrir la ventana para texto libre.
                        </div>

                        @if (count($templates) === 0)
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                No hay templates aprobados que se puedan usar desde aquí (solo sirven los que tienen variables únicamente en el texto).
                                Créalos en Meta Business Suite → WhatsApp Manager → Plantillas de mensajes.
                                <x-filament::link tag="button" wire:click="refreshTemplates" size="sm">Volver a consultar</x-filament::link>
                            </p>
                        @else
                            <form wire:submit="sendTemplate" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <x-filament::input.wrapper class="flex-1">
                                        <x-filament::input.select wire:model.live="templateKey">
                                            <option value="">Elegir template…</option>
                                            @foreach ($templates as $template)
                                                <option value="{{ $template['key'] }}">{{ $template['name'] }} ({{ $template['language'] }}{{ $template['category'] ? ' · '.mb_strtolower($template['category']) : '' }})</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                    <x-filament::icon-button icon="heroicon-o-arrow-path" wire:click="refreshTemplates" label="Actualizar templates" tooltip="Actualizar templates" color="gray" />
                                </div>

                                @if ($selectedTemplate)
                                    <div class="whitespace-pre-line rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ \App\Services\WhatsAppInbox\WhatsAppInboxService::renderTemplate($selectedTemplate['body'], array_filter($templateParams, 'filled')) }}</div>

                                    @foreach ($selectedTemplate['variables'] as $variable)
                                        <x-filament::input.wrapper wire:key="template-var-{{ $templateKey }}-{{ $variable }}" :prefix="'{' . '{' . $variable . '}' . '}'">
                                            <x-filament::input type="text" wire:model.live.debounce.300ms="templateParams.{{ $variable }}" maxlength="1000" />
                                        </x-filament::input.wrapper>
                                    @endforeach

                                    <div class="flex justify-end">
                                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendTemplate">
                                            Enviar template
                                        </x-filament::button>
                                    </div>
                                @endif
                            </form>
                        @endif
                    @endif
                </footer>
            @endif
        </section>
    </div>
</x-filament-panels::page>
