<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

beforeEach(function (): void {
    View::addNamespace('theme', resource_path('themes/anchor'));
});

it('renders localized home SEO metadata and canonical alternate URLs', function (string $locale, string $title, string $description): void {
    app()->setLocale($locale);

    $homeRoute = Route::getRoutes()->getByName('home');
    $homeView = ($homeRoute->getAction('uses'))($locale);
    $seo = $homeView->getData()['seo'];
    $head = view('theme::partials.head', ['seo' => $seo])->render();

    expect($homeView->name())->toBe('theme::pages.index')
        ->and($head)->toContain("<title>{$title}</title>")
        ->and($head)->toContain('<meta name="description" content="' . $description . '">')
        ->and($head)->toContain('<link rel="canonical" href="' . route('home', ['locale' => $locale]) . '">')
        ->and($head)->toContain('<link rel="alternate" hreflang="es" href="' . route('home', ['locale' => 'es']) . '">')
        ->and($head)->toContain('<link rel="alternate" hreflang="en" href="' . route('home', ['locale' => 'en']) . '">')
        ->and($head)->toContain('<link rel="alternate" hreflang="x-default" href="' . route('home', ['locale' => 'es']) . '">');
})->with([
    'Spanish' => [
        'es',
        'Portal y buscador inmobiliario inteligente | BienesOnLine.ai',
        'Busca propiedades inmuebles en varios países. Publica tu anuncio, conecta con agentes y compradores y encuentra coincidencias inteligentes con IA.',
    ],
    'English' => [
        'en',
        'Smart Real Estate Portal and Search Engine | BienesOnLine.ai',
        'Search for properties across multiple countries. Post your listing, connect with agents and buyers, and discover intelligent matches powered by AI.',
    ],
]);
