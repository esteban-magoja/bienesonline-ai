<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Adjuntos recibidos en la bandeja de WhatsApp (disco privado), solo para quien entra al admin.
 */
class WhatsAppMediaController extends Controller
{
    public function show(Request $request, WhatsAppMessage $message): StreamedResponse
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $disk = Storage::disk(config('whatsapp.inbox.media_disk', 'local'));

        abort_if($message->media_path === null || ! $disk->exists($message->media_path), 404);

        $filename = $message->media_filename ?: basename($message->media_path);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return $disk->response($message->media_path, $filename, [
            'Content-Type' => $message->media_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ], $disposition);
    }
}
