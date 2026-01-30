<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class LogoutController extends Controller
{
    /**
     * Handle the logout request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        // Guardar el locale ANTES de invalidar la sesión
        $locale = session('locale', config('locales.default', 'es'));
        
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to home with the saved locale
        return redirect('/' . $locale);
    }
}
