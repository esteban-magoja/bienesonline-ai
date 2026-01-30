<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TermsController extends Controller
{
    /**
     * Accept the terms and conditions.
     */
    public function accept(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasAcceptedTerms()) {
            $user->acceptTerms();
        }

        return redirect()->route('dashboard')
            ->with('success', 'Has aceptado los términos y condiciones. Ahora puedes publicar anuncios.');
    }
}
