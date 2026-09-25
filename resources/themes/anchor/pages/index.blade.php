<?php
    use function Laravel\Folio\{name};
    name('home');
?>

<x-layouts.marketing :seo="$seo">
        
        <x-marketing.sections.hero :countries="$availableCountries" />
        
        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.countries :countries="$availableCountries" />
        </x-container>
        
        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.features />
        </x-container>

        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.how-it-works />
        </x-container>

        <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.practical-guides />
        </x-container>

        {{-- <x-container class="py-6 border-t sm:py-12 border-zinc-200">
            <x-marketing.sections.smart-tools />
        </x-container> --}}


        <x-container class="py-12 border-t sm:py-24 border-zinc-200">
            <x-marketing.sections.testimonials />
        </x-container>
        
        <x-container class="py-12 border-t sm:py-24 border-zinc-200">
            <x-marketing.sections.pricing />
        </x-container>

</x-layouts.marketing>
