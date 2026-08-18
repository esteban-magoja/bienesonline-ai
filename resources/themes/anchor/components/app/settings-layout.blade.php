@props([
    'title' => '',
    'description' => '',
    'section' => 'configuration',
])

@php
    $navigation = $section === 'billing'
        ? [
            ['route' => 'settings.subscription', 'icon' => 'phosphor-credit-card-duotone', 'label' => __('settings.menu.subscription')],
            ['route' => 'settings.invoices', 'icon' => 'phosphor-invoice-duotone', 'label' => __('settings.menu.invoices')],
        ]
        : [
            ['route' => 'settings.profile', 'icon' => 'phosphor-user-circle-duotone', 'label' => __('settings.menu.profile')],
            ['route' => 'settings.public-profile', 'icon' => 'phosphor-storefront-duotone', 'label' => __('settings.menu.public_profile')],
            ['route' => 'settings.services', 'icon' => 'phosphor-list-checks-duotone', 'label' => __('settings.menu.services')],
            ['route' => 'settings.team', 'icon' => 'phosphor-users-three-duotone', 'label' => __('settings.menu.team')],
            ['route' => 'settings.security', 'icon' => 'phosphor-lock-duotone', 'label' => __('settings.menu.security')],
        ];
@endphp

<x-app.container class="max-w-6xl space-y-6 lg:space-y-8">
    <x-app.heading :title="$title" :description="$description" :border="false" />

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">
        <aside class="w-full shrink-0 lg:w-56">
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    {{ $section === 'billing' ? __('settings.menu.billing') : __('settings.menu.configuration') }}
                </p>
                <nav class="flex gap-1 overflow-x-auto lg:flex-col">
                    @foreach ($navigation as $item)
                        <x-settings-sidebar-link :href="route($item['route'])" :icon="$item['icon']">
                            {{ $item['label'] }}
                        </x-settings-sidebar-link>
                    @endforeach
                </nav>
            </div>
        </aside>

        <div class="min-w-0 flex-1 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800 sm:p-6 lg:p-8">
            {{ $slot }}
        </div>
    </div>
</x-app.container>
