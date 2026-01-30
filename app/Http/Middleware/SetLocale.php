<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detectLocale($request);
        
        // Validar que el locale sea válido
        if (!in_array($locale, config('locales.available', ['es', 'en']))) {
            $locale = config('locales.default', 'es');
        }

        // Establecer el locale en la aplicación
        App::setLocale($locale);
        
        // Guardar en sesión para persistencia
        Session::put('locale', $locale);

        // Configurar Carbon para fechas localizadas
        if (class_exists(\Carbon\Carbon::class)) {
            \Carbon\Carbon::setLocale($locale);
        }

        return $next($request);
    }

    /**
     * Detectar el locale del usuario.
     * Prioridad: URL segment > Query param > Route param > Sesión > Accept-Language > Default
     *
     * @param Request $request
     * @return string
     */
    protected function detectLocale(Request $request): string
    {
        $availableLocales = config('locales.available', ['es', 'en']);
        
        // 1. Intentar obtener del primer segmento de la URL (/en/..., /es/...)
        $firstSegment = $request->segment(1);
        if ($firstSegment && in_array($firstSegment, $availableLocales)) {
            return $firstSegment;
        }

        // 2. Intentar obtener del query parameter (?locale=en)
        $localeFromQuery = $request->query('locale');
        if ($localeFromQuery && in_array($localeFromQuery, $availableLocales)) {
            return $localeFromQuery;
        }

        // 3. Intentar obtener de la URL (parámetro de ruta)
        $localeFromUrl = $request->route('locale');
        if ($localeFromUrl && in_array($localeFromUrl, $availableLocales)) {
            return $localeFromUrl;
        }

        // 4. Intentar obtener de la sesión
        $localeFromSession = Session::get('locale');
        if ($localeFromSession && in_array($localeFromSession, $availableLocales)) {
            return $localeFromSession;
        }

                // 5. Intentar detectar del header Accept-Language
        $localeFromHeader = $this->detectFromHeader($request);
        if ($localeFromHeader) {
            return $localeFromHeader;
        }

        // 6. Usar locale por defecto
        return config('locales.default', 'es');
    }

    /**
     * Detectar locale del header Accept-Language.
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectFromHeader(Request $request): ?string
    {
        $acceptLanguage = $request->header('Accept-Language');
        
        if (!$acceptLanguage) {
            return null;
        }

        // Parsear el header (formato: "es-AR,es;q=0.9,en;q=0.8")
        $languages = explode(',', $acceptLanguage);
        
        foreach ($languages as $language) {
            // Extraer solo el código de idioma (primeros 2 caracteres)
            $code = substr(trim($language), 0, 2);
            
            if (in_array($code, config('locales.available', ['es', 'en']))) {
                return $code;
            }
        }

        return null;
    }
}
