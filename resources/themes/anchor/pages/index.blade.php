<?php
    use function Laravel\Folio\{name};
    name('home');
?>

<x-layouts.marketing
    :seo="[
        'title'         => setting('site.title', 'Raxta - Plataforma Inmobiliaria Inteligente'),
        'description'   => setting('site.description', 'Conectamos propiedades con compradores y agentes de forma inteligente. Tu próxima oportunidad inmobiliaria está a un solo clic.'),
        'image'         => url('/og_image.png'),
        'type'          => 'website'
    ]"
>
        
        <x-marketing.sections.hero />
        
        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.features />
        </x-container>

        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.how-it-works />
        </x-container>

        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.practical-guides />
        </x-container>

        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.smart-tools />
        </x-container>


        <x-container class="py-12 border-t sm:py-24 border-zinc-200">
            <x-marketing.sections.testimonials />
        </x-container>
        
        <x-container class="py-12 border-t sm:py-24 border-zinc-200">
            <x-marketing.sections.pricing />
        </x-container>

</x-layouts.marketing>
