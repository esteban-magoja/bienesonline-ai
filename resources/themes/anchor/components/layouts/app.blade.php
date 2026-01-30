<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @include('theme::partials.head', ['seo' => ($seo ?? null) ])
    <!-- Used to add dark mode right away, adding here prevents any flicker -->
    <script>
        if (typeof(Storage) !== "undefined") {
            if(localStorage.getItem('theme') && localStorage.getItem('theme') == 'dark'){
                document.documentElement.classList.add('dark');
            }
        }
        document.addEventListener("livewire:navigated", () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        });
    </script>
</head>
<body x-data class="flex flex-col lg:min-h-screen bg-zinc-50 dark:bg-zinc-900 @if(config('wave.dev_bar')){{ 'pb-10' }}@endif">

    <x-app.sidebar />

    <div class="flex flex-col pl-0 min-h-screen justify-stretch lg:pl-64">
        {{-- Mobile Header --}}
        <header class="lg:hidden px-5 block flex justify-between sticky top-0 z-40 bg-gray-50 dark:bg-zinc-900 -mb-px border-b border-zinc-200/70 dark:border-zinc-700 h-[72px] items-center">
            <div class="flex items-center justify-between w-full px-5 py-3 border-b lg:hidden border-zinc-200 dark:border-zinc-700">
                <button @click="$dispatch('open-sidebar')" class="flex items-center justify-center flex-shrink-0 w-10 h-10 rounded-md text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 dark:hover:bg-zinc-700/70 hover:bg-gray-200/70">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                <x-logo class="w-auto h-7" />
                {{-- Language switcher removed - now in sidebar --}}
                <x-app.user-menu position="top" />
            </div>
        </header>
        {{-- End Mobile Header --}}
        <main class="flex flex-col flex-1 xl:px-0 lg:pt-4 lg:h-screen">
            <div class="overflow-hidden flex-1 h-full bg-white border-t border-l-0 lg:border-l dark:bg-zinc-800 lg:rounded-tl-xl border-zinc-200/70 dark:border-zinc-700">
                <div class="px-5 w-full h-full sm:px-8 lg:overflow-y-scroll scrollbar-hidden lg:pt-5 lg:px-5">
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    @livewire('notifications')
    @if(!auth()->guest() && auth()->user()->hasChangelogNotifications())
        @include('theme::partials.changelogs')
    @endif
    @include('theme::partials.footer-scripts')
    {{ $javascript ?? '' }}
    

</body>
</html>

