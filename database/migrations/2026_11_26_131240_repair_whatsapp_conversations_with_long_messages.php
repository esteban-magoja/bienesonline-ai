<?php

use App\Services\WhatsAppInbox\WhatsAppWebhookProcessor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repara las conversaciones de la bandeja de WhatsApp que quedaron sin actualizar porque un mensaje
 * largo no entraba en la vista previa (varchar 191): el mensaje se guardó, pero la conversación quedó
 * sin last_inbound_at / last_message_at, y la bandeja daba la ventana de 24 h por vencida.
 *
 * Solo datos: recalcula esos campos a partir de los mensajes guardados. Es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conversations = DB::table('whatsapp_conversations')
            ->select(['id', 'last_inbound_at', 'last_message_at', 'unread_count'])
            ->get();

        foreach ($conversations as $conversation) {
            $lastInboundAt = DB::table('whatsapp_messages')
                ->where('whatsapp_conversation_id', $conversation->id)
                ->where('direction', 'in')
                ->max('sent_at');

            $latest = DB::table('whatsapp_messages')
                ->where('whatsapp_conversation_id', $conversation->id)
                ->orderByDesc('sent_at')
                ->orderByDesc('id')
                ->first(['type', 'body', 'sent_at']);

            if ($latest === null) {
                continue;
            }

            $attributes = [];

            if ($lastInboundAt !== null && ($conversation->last_inbound_at === null || Carbon::parse($lastInboundAt)->gt(Carbon::parse($conversation->last_inbound_at)))) {
                $missedInbound = DB::table('whatsapp_messages')
                    ->where('whatsapp_conversation_id', $conversation->id)
                    ->where('direction', 'in')
                    ->when($conversation->last_inbound_at !== null, fn ($query) => $query->where('sent_at', '>', $conversation->last_inbound_at))
                    ->count();

                $attributes['last_inbound_at'] = $lastInboundAt;
                $attributes['unread_count'] = (int) $conversation->unread_count + $missedInbound;
            }

            if ($conversation->last_message_at === null || Carbon::parse($latest->sent_at)->gt(Carbon::parse($conversation->last_message_at))) {
                $attributes['last_message_at'] = $latest->sent_at;
                $attributes['last_message_preview'] = WhatsAppWebhookProcessor::preview($latest->type, $latest->body);
            }

            if ($attributes !== []) {
                DB::table('whatsapp_conversations')->where('id', $conversation->id)->update($attributes);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
