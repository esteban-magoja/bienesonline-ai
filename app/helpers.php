<?php

if (!function_exists('current_locale')) {
    /**
     * Obtener el locale actual de la aplicación.
     *
     * @return string
     */
    function current_locale(): string
    {
        return app()->getLocale();
    }
}

if (!function_exists('route_localized')) {
    /**
     * Generar una URL de ruta con prefijo de locale.
     *
     * @param string $name Nombre de la ruta
     * @param array $parameters Parámetros adicionales
     * @param string|null $locale Locale específico (null = usar actual)
     * @return string
     */
    function route_localized(string $name, array $parameters = [], ?string $locale = null): string
    {
        $locale = $locale ?? current_locale();
        
        // Agregar locale a los parámetros
        $parameters = array_merge(['locale' => $locale], $parameters);
        
        return route($name, $parameters);
    }
}

if (!function_exists('trans_choice_formatted')) {
    /**
     * Traducción con pluralización y formato de números.
     *
     * @param string $key Clave de traducción
     * @param int $count Cantidad para pluralización
     * @param array $replace Reemplazos adicionales
     * @return string
     */
    function trans_choice_formatted(string $key, int $count, array $replace = []): string
    {
        $replace['count'] = number_format($count, 0, ',', '.');
        return trans_choice($key, $count, $replace);
    }
}

if (!function_exists('get_localized_url')) {
    /**
     * Convertir una URL a su versión localizada.
     *
     * @param string $url URL original
     * @param string|null $locale Locale deseado (null = usar actual)
     * @return string
     */
    function get_localized_url(string $url, ?string $locale = null): string
    {
        $locale = $locale ?? current_locale();
        
        // Si la URL ya tiene un locale, reemplazarlo
        $availableLocales = config('locales.available', ['es', 'en']);
        $pattern = '/^\/(' . implode('|', $availableLocales) . ')(\/|$)/';
        
        if (preg_match($pattern, $url)) {
            // Reemplazar locale existente
            return preg_replace($pattern, '/' . $locale . '$2', $url);
        }
        
        // Agregar locale al inicio
        return '/' . $locale . $url;
    }
}

if (!function_exists('alternate_locales')) {
    /**
     * Obtener array de locales alternativos (para hreflang tags).
     *
     * @return array ['es' => 'http://...', 'en' => 'http://...']
     */
    function alternate_locales(): array
    {
        $currentUrl = request()->url();
        $currentLocale = current_locale();
        $availableLocales = config('locales.available', ['es', 'en']);
        
        $alternates = [];
        
        foreach ($availableLocales as $locale) {
            if ($locale === $currentLocale) {
                $alternates[$locale] = $currentUrl;
            } else {
                $alternates[$locale] = str_replace(
                    '/' . $currentLocale . '/',
                    '/' . $locale . '/',
                    $currentUrl
                );
            }
        }
        
        return $alternates;
    }
}

if (!function_exists('locale_flag')) {
    /**
     * Obtener el emoji de bandera para un locale.
     *
     * @param string|null $locale Locale (null = usar actual)
     * @return string
     */
    function locale_flag(?string $locale = null): string
    {
        $locale = $locale ?? current_locale();
        return config("locales.flags.{$locale}", '🌐');
    }
}

if (!function_exists('locale_name')) {
    /**
     * Obtener el nombre del idioma para un locale.
     *
     * @param string|null $locale Locale (null = usar actual)
     * @return string
     */
    function locale_name(?string $locale = null): string
    {
        $locale = $locale ?? current_locale();
        return config("locales.names.{$locale}", $locale);
    }
}
