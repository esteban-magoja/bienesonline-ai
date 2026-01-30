<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedDomainsStr = config('app.allowed_domains', '');
        
        // Si no hay dominios configurados, permitir todos
        if (empty($allowedDomainsStr)) {
            return $next($request);
        }
        
        $allowedDomains = array_map('trim', explode(',', $allowedDomainsStr));
        $currentDomain = $request->getHost();

        // Si el dominio actual está en la lista, permitir
        if (in_array($currentDomain, $allowedDomains)) {
            return $next($request);
        }

        // Redirigir al dominio principal
        $primaryDomain = trim($allowedDomains[0]);
        $scheme = $request->secure() ? 'https' : 'http';
        $url = $scheme . '://' . $primaryDomain . $request->getRequestUri();
        
        return redirect()->away($url, 301);
    }
}
