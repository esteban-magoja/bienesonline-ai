<?php
    use function Laravel\Folio\{middleware, name};

    middleware('auth');
    name('settings.public-profile');
?>

<x-layouts.app>
    <x-app.settings-layout
        title="{{ __('settings.public_profile.title') }}"
        description="{{ __('settings.public_profile.description') }}"
    >
        <livewire:settings.public-profile />
    </x-app.settings-layout>
</x-layouts.app>
