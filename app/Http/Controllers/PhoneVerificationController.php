<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function verify(string $token)
    {
        $user = User::where('movil_verification_token', $token)->first();

        if (!$user) {
            return view('phone-verified', [
                'success' => false,
                'message' => __('auth.phone_verification.invalid_token'),
            ]);
        }

        if ($user->movil_verified_at) {
            return view('phone-verified', [
                'success' => true,
                'already' => true,
                'message' => __('auth.phone_verification.already_verified'),
            ]);
        }

        $user->movil_verified_at = now();
        $user->movil_verification_token = null;

        // Verificar el número desde el link es aceptar los avisos por WhatsApp
        // (el texto junto al botón de verificar lo informa). Se desactivan desde el perfil o respondiendo BAJA.
        if (! $user->whatsapp_opt_in) {
            $user->whatsapp_opt_in = true;
            $user->whatsapp_opt_in_at = now();
        }

        $user->saveQuietly();

        return view('phone-verified', [
            'success' => true,
            'already' => false,
            'message' => __('auth.phone_verification.verified'),
        ]);
    }
}
