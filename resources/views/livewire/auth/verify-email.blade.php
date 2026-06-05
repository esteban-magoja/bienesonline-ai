<div class="relative w-full sm:max-w-md sm:mx-auto">
    <div class="flex flex-col justify-center items-stretch px-10 py-8 w-full min-h-screen bg-white border-gray-200 sm:min-h-0 sm:border sm:rounded-xl">

        <h1 class="mt-1 text-xl font-medium leading-9 text-center">{{ __('auth.verify.headline') }}</h1>
        <p class="mt-1 text-sm text-center text-gray-500 mb-5">{{ __('auth.verify.subheadline') }}</p>

        @if (session('resent'))
            <div class="flex items-start gap-2 px-4 py-3 mb-4 text-sm text-white bg-green-500 rounded-lg" role="alert">
                <svg class="shrink-0 w-5 h-5 fill-current mt-0.5" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <p>{{ __('auth.verify.new_link_sent') }}</p>
            </div>
        @endif

        {{-- Email step --}}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 mb-4">
            <div class="flex gap-3">
                <svg class="shrink-0 w-5 h-5 text-blue-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
                <div class="text-sm text-blue-800 leading-relaxed">
                    <p class="font-medium mb-1">{{ __('auth.verify.email_step_title') }}</p>
                    <p>{{ __('auth.verify.description') }}
                        <button wire:click="resend" class="font-medium underline cursor-pointer hover:text-blue-600 transition-colors">{{ __('auth.verify.new_request_link') }}</button>.
                        {{ __('auth.verify.check_spam') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- WhatsApp step --}}
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 mb-5">
            <div class="flex gap-3">
                <svg class="shrink-0 w-5 h-5 text-green-600 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/>
                </svg>
                <div class="text-sm text-green-800 leading-relaxed">
                    <p class="font-medium mb-1">{{ __('auth.verify.whatsapp_step_title') }}</p>
                    <p>{{ __('auth.verify.whatsapp_description') }}</p>
                    <p class="mt-1 font-medium text-green-700">⚠️ {{ __('auth.verify.whatsapp_required_notice') }}</p>
                </div>
            </div>
        </div>

        {{-- Dashboard button --}}
        <a href="{{ route('dashboard') }}"
           style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;border-radius:6px;background:#111827;padding:10px 16px;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;margin-bottom:16px;">
            <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
            {{ __('auth.verify.go_dashboard') }}
        </a>

        {{-- Logout --}}
        <div class="text-sm text-center text-gray-500">
            <span>{{ __('auth.verify.or') }}</span>
            <button onclick="document.getElementById('vf-logout').submit();" class="ml-1 underline cursor-pointer hover:text-gray-800 transition-colors">
                {{ __('auth.verify.logout') }}
            </button>
            <form id="vf-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        </div>

    </div>
</div>

