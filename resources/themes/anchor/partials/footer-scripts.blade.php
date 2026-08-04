@filamentScripts
@livewireScripts
@if(config('wave.dev_bar'))
    @include('theme::partials.dev_bar')
@endif

{{-- @yield('javascript') --}}

@php
    $userAgent = request()->userAgent() ?? '';
    $isBot = preg_match('/(?:bot|crawler|spider|slurp|facebookexternalhit|facebot|twitterbot|linkedinbot|whatsapp|telegrambot|slackbot|discordbot|bingpreview|headlesschrome)/i', $userAgent) === 1;
@endphp

@if(setting('site.google_analytics_tracking_id', '') && ! $isBot)
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ setting('site.google_analytics_tracking_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '{{ setting("site.google_analytics_tracking_id") }}');
    </script>
@endif
