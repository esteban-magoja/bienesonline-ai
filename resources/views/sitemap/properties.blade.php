<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($properties as $property)
    <url>
        <loc>{{ $property['loc'] }}</loc>
        <lastmod>{{ $property['lastmod'] }}</lastmod>
        <changefreq>{{ $property['changefreq'] }}</changefreq>
        <priority>{{ $property['priority'] }}</priority>
@foreach($property['alternates'] as $lang => $url)
        <xhtml:link rel="alternate" hreflang="{{ $lang }}" href="{{ $url }}" />
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $property['alternates']['es'] }}" />
@if($property['image'])
        <image:image>
            <image:loc>{{ $property['image'] }}</image:loc>
            <image:title>{{ $property['image_title'] }}</image:title>
        </image:image>
@endif
    </url>
@endforeach
</urlset>
