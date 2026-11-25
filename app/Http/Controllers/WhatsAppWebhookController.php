<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la WhatsApp Cloud API: mensajes que escriben los usuarios y estados de entrega.
 * Se configura en developers.facebook.com → WhatsApp → Configuración (campo "messages").
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Verificación que hace Meta al guardar la URL del webhook (GET con hub.mode, hub.verify_token y hub.challenge).
     * PHP convierte los puntos de los parámetros en guiones bajos.
     */
    public function verify(Request $request): Response
    {
        $verifyToken = (string) config('whatsapp.webhook_verify_token');

        if ($verifyToken !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($verifyToken, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function store(Request $request): JsonResponse
    {
        $appSecret = (string) config('whatsapp.app_secret');

        // Sin App Secret no se puede validar la firma: 500 para que Meta reintente cuando esté configurado
        if ($appSecret === '') {
            Log::error('WhatsApp webhook: falta WHATSAPP_APP_SECRET, aviso rechazado.');

            return response()->json(['message' => 'Webhook not configured.'], 500);
        }

        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        if (! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            Log::warning('WhatsApp webhook: firma inválida.');

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $data = json_decode($payload, true);

        if (! is_array($data) || ($data['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        $event = WhatsAppWebhookEvent::create([
            'payload' => $data,
            'status' => 'pending',
        ]);

        ProcessWhatsAppWebhook::dispatch($event->id);

        return response()->json(['message' => 'Webhook received.']);
    }
}
