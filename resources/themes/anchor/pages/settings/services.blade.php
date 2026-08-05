<?php
    use function Laravel\Folio\{middleware, name};

    middleware('auth');
    name('settings.services');
?>

<x-layouts.app>
    <x-app.settings-layout
        title="{{ __('settings.public_profile.services_title') }}"
        description="{{ __('settings.public_profile.services_description') }}"
    >
        <livewire:settings.services />
    </x-app.settings-layout>
</x-layouts.app>
