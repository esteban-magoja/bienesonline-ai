<?php
    use function Laravel\Folio\{name, middleware};
    use App\Models\Page;
    
    name('page.show.es');
    middleware(['web']);
?>

@php
    // Locale establecido por middleware SetLocale basado en URL /es/...
    
    // Buscar la página por slug
    $page = \App\Models\Page::where('slug', $slug)
        ->where('status', 'ACTIVE')
        ->firstOrFail();
    
    // El modelo Page traduce automáticamente según el locale actual
    $seo = [
        'seo_title' => $page->title,
        'seo_description' => $page->meta_description,
    ];
@endphp

<x-layouts.marketing :seo="$seo">

    <x-elements.back-button
        class="max-w-3xl mx-auto mt-4 md:mt-8"
        text="{{ __('messages.back_home') }}"
        :href="route_localized('home')"
    />
    
    <article id="post-{{ $page->id }}" class="max-w-3xl px-5 mx-auto mb-32 prose prose-lg lg:prose-xl lg:px-0">

        <meta property="name" content="{{ $page->title }}">
        <meta property="author" typeof="Person" content="admin">
        <meta property="dateModified" content="{{ Carbon\Carbon::parse($page->updated_at)->toIso8601String() }}">
        <meta property="datePublished" content="{{ Carbon\Carbon::parse($page->created_at)->toIso8601String() }}">

        <div class="max-w-4xl mx-auto mt-6">
            <h1 class="flex flex-col leading-none">
                <span>{{ $page->title }}</span>
            </h1>
        </div>

        @if($page->image)
            <div class="relative">
                <img class="w-full h-auto rounded-lg" src="{{ url($page->image) }}" alt="{{ $page->title }}">
            </div>
        @endif

        <div class="max-w-4xl mx-auto">
            {!! $page->body !!}
        </div>

    </article>

</x-layouts.marketing>
