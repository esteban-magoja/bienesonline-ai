<?php

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Aviso de verificación del móvil de WhatsApp con botón para reenviar el link.
 * Se usa en /dashboard (solo si falta verificar) y en /settings/profile (también muestra "verificado").
 */
new class extends Component {
    /** Mostrar el recuadro verde cuando el número ya está verificado. */
    public bool $showVerified = false;

    /** Resultado del último intento: 'sent' o 'throttled'. */
    public ?string $status = null;

    public int $retryMinutes = 0;

    /**
     * Reenvía por WhatsApp el link de verificación del móvil guardado.
     * Limitado a 3 envíos por hora: cada template de WhatsApp tiene costo.
     */
    public function send(): void
    {
        $user = auth()->user();

        if (empty($user->movil) || $user->hasVerifiedPhone()) {
            return;
        }

        $key = 'phone-verification:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->status = 'throttled';
            $this->retryMinutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return;
        }

        RateLimiter::hit($key, 3600);

        $user->sendPhoneVerification();

        $this->status = 'sent';
    }

    /** El perfil avisa cuando se guardó (puede haber cambiado el número). */
    #[On('phone-updated')]
    public function phoneUpdated(): void
    {
        $this->status = null;
    }
}; ?>

<div>
    @php($user = auth()->user())
    @if (! empty($user->movil))
        @if ($user->hasVerifiedPhone())
            @if ($showVerified)
                <div class="flex items-center gap-2 px-4 py-3 text-sm text-green-800 border border-green-200 rounded-lg bg-green-50 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ __('settings.profile.phone_verified', ['phone' => $user->movil]) }}</span>
                </div>
            @endif
        @else
            <div class="p-5 border-l-4 shadow-sm rounded-xl bg-amber-50 border-amber-500 dark:bg-amber-900/20">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-[#128C7E] shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('settings.profile.phone_verification_title') }}</p>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">{{ __('settings.profile.phone_not_verified', ['phone' => $user->movil]) }}</p>

                        @if ($status === 'sent')
                            <p class="mt-3 text-sm font-medium text-green-700 dark:text-green-400">{{ __('settings.profile.phone_verification_sent') }}</p>
                        @elseif ($status === 'throttled')
                            <p class="mt-3 text-sm font-medium text-red-700 dark:text-red-400">{{ __('settings.profile.phone_verification_throttled', ['minutes' => $retryMinutes]) }}</p>
                        @endif

                        <button type="button"
                            wire:click="send"
                            wire:loading.attr="disabled"
                            wire:target="send"
                            class="inline-flex items-center justify-center px-4 py-2 mt-3 text-sm font-medium text-white rounded-md bg-[#128C7E] hover:bg-[#0f7a6d] disabled:opacity-60">
                            {{ $status === 'sent' ? __('settings.profile.phone_resend_verification') : __('settings.profile.phone_send_verification') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
