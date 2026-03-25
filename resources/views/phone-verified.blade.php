@php
    $locale = app()->getLocale();
    $seo = [
        'title' => $success
            ? __('auth.phone_verification.title_success')
            : __('auth.phone_verification.title_error'),
        'description' => $success
            ? __('auth.phone_verification.subtitle_success')
            : __('auth.phone_verification.subtitle_error'),
    ];
@endphp

<x-layouts.marketing :seo="$seo">
    <div class="flex min-h-[60vh] items-center justify-center px-4 py-16">
        <div class="w-full max-w-md text-center">

            @if ($success)
                {{-- Ícono de éxito --}}
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <h1 class="mb-2 text-2xl font-bold text-gray-900">
                    {{ __('auth.phone_verification.title_success') }}
                </h1>
                <p class="mb-2 text-base text-gray-600">
                    {{ $message }}
                </p>
                <p class="mb-8 text-sm text-gray-500">
                    {{ __('auth.phone_verification.subtitle_success') }}
                </p>

            @else
                {{-- Ícono de error --}}
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
                    <svg class="h-10 w-10 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                </div>

                <h1 class="mb-2 text-2xl font-bold text-gray-900">
                    {{ __('auth.phone_verification.title_error') }}
                </h1>
                <p class="mb-2 text-base text-gray-600">
                    {{ $message }}
                </p>
                <p class="mb-8 text-sm text-gray-500">
                    {{ __('auth.phone_verification.subtitle_error') }}
                </p>
            @endif

            {{-- Botones de acción --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                @auth
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        {{ __('auth.phone_verification.go_dashboard') }}
                    </a>
                @endauth
                <a href="{{ url('/' . $locale) }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2">
                    {{ __('auth.phone_verification.go_home') }}
                </a>
            </div>

        </div>
    </div>
</x-layouts.marketing>
