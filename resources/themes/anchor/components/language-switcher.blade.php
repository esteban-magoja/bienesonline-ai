@props(['theme' => 'light'])

@php
    $isDark = $theme === 'dark';

    $buttonClasses = $isDark
        ? 'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-zinc-100 bg-zinc-900 border border-zinc-700 rounded-md hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-white/20 focus:ring-offset-2 focus:ring-offset-black'
        : 'inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';

    $menuClasses = $isDark
        ? 'absolute right-0 z-50 w-48 mt-2 origin-top-right bg-black border border-zinc-800 divide-y divide-zinc-800 rounded-md shadow-lg'
        : 'absolute right-0 z-50 w-48 mt-2 origin-top-right bg-white border border-gray-200 divide-y divide-gray-100 rounded-md shadow-lg dark:bg-gray-800 dark:border-gray-700';

    $activeItemClasses = $isDark
        ? 'bg-zinc-900 text-white'
        : 'bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-200';

    $inactiveItemClasses = $isDark
        ? 'text-zinc-200 hover:bg-zinc-900'
        : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';

    $activeIconClasses = $isDark ? 'w-4 h-4 text-white' : 'w-4 h-4 text-blue-600 dark:text-blue-400';
@endphp

{{-- Language Switcher Component --}}
<div x-data="{ open: false }" class="relative inline-block text-left">
    {{-- Botón principal --}}
    <button 
        @click="open = !open" 
        @click.away="open = false"
        type="button" 
        class="{{ $buttonClasses }}"
        aria-expanded="false"
        aria-haspopup="true"
    >
        <span>{{ locale_name() }}</span>
        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    {{-- Dropdown menu --}}
    <div 
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="{{ $menuClasses }}"
        role="menu"
        aria-orientation="vertical"
        style="display: none;"
    >
        <div class="py-1" role="none">
            @foreach(config('locales.available', ['es', 'en']) as $locale)
                <a 
                    href="{{ get_localized_url(request()->getRequestUri(), $locale) }}"
                    class="flex items-center gap-3 px-4 py-2 text-sm transition duration-150 {{ current_locale() === $locale ? $activeItemClasses : $inactiveItemClasses }}"
                    role="menuitem"
                >
                    <span class="flex-1">{{ locale_name($locale) }}</span>
                    @if(current_locale() === $locale)
                        <svg class="{{ $activeIconClasses }}" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
