<?php
    use function Laravel\Folio\{middleware, name};

    middleware('auth');
    name('settings.team');
?>

<x-layouts.app>
    <x-app.settings-layout
        title="{{ __('settings.public_profile.team_title') }}"
        description="{{ __('settings.public_profile.team_description') }}"
    >
        <livewire:settings.team />
    </x-app.settings-layout>
</x-layouts.app>
