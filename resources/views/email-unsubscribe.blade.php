@php
    $seo = [
        'title' => __('admin_email.unsubscribe.title'),
        'description' => __('admin_email.unsubscribe.title'),
        'robots' => 'noindex,nofollow',
    ];
@endphp

<x-layouts.marketing :seo="$seo">
    <div class="flex min-h-[60vh] items-center justify-center px-4 py-16">
        <div class="w-full max-w-md text-center">
            @if ($unsubscribed)
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-10 w-10 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="mb-2 text-2xl font-bold text-gray-900">{{ __('admin_email.unsubscribe.done_title') }}</h1>
                <p class="mb-8 text-base text-gray-600">{{ __('admin_email.unsubscribe.done_text', ['email' => $email]) }}</p>
            @else
                <h1 class="mb-2 text-2xl font-bold text-gray-900">{{ __('admin_email.unsubscribe.title') }}</h1>
                <p class="mb-8 text-base text-gray-600">{{ __('admin_email.unsubscribe.confirm_text', ['email' => $email]) }}</p>
                <form method="POST" action="{{ $formUrl }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        {{ __('admin_email.unsubscribe.button') }}
                    </button>
                </form>
            @endif

            <a href="{{ url('/' . app()->getLocale()) }}" class="mt-6 inline-block text-sm text-gray-500 hover:text-gray-700">
                {{ __('admin_email.unsubscribe.go_home') }}
            </a>
        </div>
    </div>
</x-layouts.marketing>
