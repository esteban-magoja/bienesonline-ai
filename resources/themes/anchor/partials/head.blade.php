@php
    if(isset($seo)){
        $seo = (is_array($seo)) ? ((object)$seo) : $seo;
    }

    $robotsDirective = $seo->robots ?? 'index,follow';
@endphp
@if(isset($seo->title))
    <title>{{ $seo->title }}</title>
@else
    <title>{{ setting('site.title', 'Raxta') . ' - ' . setting('site.description', '') }}</title>
@endif

<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge"> <!-- † -->
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="url" content="{{ url('/') }}">

<x-favicon></x-favicon>

{{-- Social Share Open Graph Meta Tags --}}
@if(isset($seo->title) && isset($seo->description) && isset($seo->image))
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:url" content="{{ Request::url() }}">
    <meta property="og:image" content="{{ $seo->image }}">
    <meta property="og:type" content="@if(isset($seo->type)){{ $seo->type }}@else{{ 'article' }}@endif">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:site_name" content="{{ setting('site.title') }}">

    <meta itemprop="name" content="{{ $seo->title }}">
    <meta itemprop="description" content="{{ $seo->description }}">
    <meta itemprop="image" content="{{ $seo->image }}">

    @if(isset($seo->image_w) && isset($seo->image_h))
        <meta property="og:image:width" content="{{ $seo->image_w }}">
        <meta property="og:image:height" content="{{ $seo->image_h }}">
    @endif
    
    {{-- OG Locale Tags --}}
    @if(isset($seo->og_locale))
        <meta property="og:locale" content="{{ $seo->og_locale }}">
        @if(isset($seo->og_alternate_locales))
            @foreach($seo->og_alternate_locales as $altLocale)
                <meta property="og:locale:alternate" content="{{ $altLocale }}">
            @endforeach
        @endif
    @endif

    {{-- Twitter Card Meta Tags --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo->title }}">
    <meta name="twitter:description" content="{{ $seo->description }}">
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif

{{-- Hreflang Tags for SEO --}}
@if(isset($seo->hreflang_tags))
    @foreach($seo->hreflang_tags as $tag)
        <link rel="{{ $tag['rel'] }}" hreflang="{{ $tag['hreflang'] }}" href="{{ $tag['href'] }}">
    @endforeach
@endif

{{-- Canonical URL --}}
@if(isset($seo->canonical))
    <link rel="canonical" href="{{ $seo->canonical }}">
@endif

@if(isset($seo->structured_data))
    <script type="application/ld+json">{!! json_encode($seo->structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endif

<meta name="robots" content="{{ $robotsDirective }}">
<meta name="googlebot" content="{{ $robotsDirective }}">

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-W55CSJ8X');</script>
<!-- End Google Tag Manager -->

@if(isset($seo->description))
    <meta name="description" content="{{ $seo->description }}">
@endif

@filamentStyles
@livewireStyles
@vite(['resources/themes/anchor/assets/css/app.css', 'resources/themes/anchor/assets/js/app.js'])
