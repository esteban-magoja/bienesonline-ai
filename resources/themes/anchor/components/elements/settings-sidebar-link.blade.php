@php($isActive = $href === RalphJSmit\Livewire\Urls\Facades\Url::current())

<a href="{{ $href }}" wire:navigate class="{{ $isActive ? 'bg-zinc-100 dark:bg-zinc-700/70 text-zinc-900 dark:text-zinc-200 font-semibold' : 'hover:bg-zinc-100 hover:dark:bg-zinc-700/70 hover:dark:text-zinc-200 text-zinc-600 dark:text-zinc-400 font-medium' }} flex shrink-0 items-center justify-start gap-2 rounded-md px-3 py-2.5 text-sm whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 lg:w-full">
    <span class="h-5 w-1 shrink-0 rounded-full transition-all duration-300 lg:-ml-3 {{ $isActive ? 'opacity-100' : 'opacity-0' }}" style="background:{{ config('wave.primary_color') }}"></span>
    <x-dynamic-component :component="$icon" class="h-5 w-5 shrink-0" />
    <span class="truncate">{{ $slot }}</span>
</a>
