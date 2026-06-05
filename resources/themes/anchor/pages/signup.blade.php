<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Events\Registered;
use App\Models\User;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

middleware(['guest']);
name('signup');

new class extends Component
{
    public string $name = '';
    public string $agency = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $phoneCountryCode = '';
    public string $phoneLocal = '';
    public bool $whatsapp_opt_in = false;

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'email'             => 'required|string|email|max:255|unique:users',
            'password'          => 'required|string|min:8|confirmed',
            'phoneCountryCode'  => 'required|string|digits_between:1,4',
            'phoneLocal'        => ['required', 'string', 'min:5', 'max:15', 'regex:/^\d+$/'],
            'whatsapp_opt_in'   => 'accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => __('validation.required', ['attribute' => __('attributes.name')]),
            'email.required'            => __('validation.required', ['attribute' => __('attributes.email')]),
            'email.email'               => __('validation.email', ['attribute' => __('attributes.email')]),
            'email.unique'              => __('validation.unique', ['attribute' => __('attributes.email')]),
            'password.required'         => __('validation.required', ['attribute' => __('attributes.password')]),
            'password.min'              => __('validation.min.string', ['attribute' => __('attributes.password'), 'min' => 8]),
            'password.confirmed'        => __('validation.confirmed', ['attribute' => __('attributes.password')]),
            'phoneCountryCode.required' => __('validation.required', ['attribute' => __('auth.signup.phone_country_code')]),
            'phoneCountryCode.digits_between' => __('validation.numeric', ['attribute' => __('auth.signup.phone_country_code')]),
            'phoneLocal.required'       => __('validation.required', ['attribute' => __('auth.signup.phone_local')]),
            'phoneLocal.min'            => __('validation.min.string', ['attribute' => __('auth.signup.phone_local'), 'min' => 5]),
            'phoneLocal.regex'          => __('validation.numeric', ['attribute' => __('auth.signup.phone_local')]),
            'whatsapp_opt_in.accepted'  => __('auth.signup.whatsapp_opt_in_required'),
        ];
    }

    /**
     * Builds the full international phone number from country code + local number.
     * Strips all non-digit characters and removes leading zeros from the local part.
     */
    protected function buildFullPhoneNumber(): string
    {
        $code  = preg_replace('/[^\d]/', '', $this->phoneCountryCode);
        $local = preg_replace('/[^\d]/', '', $this->phoneLocal);
        $local = ltrim($local, '0');

        return '+' . $code . $local;
    }

    public function register(): mixed
    {
        $this->validate();

        $locale = app()->getLocale();
        $locale = in_array($locale, ['es', 'en']) ? $locale : 'es';

        $userData = [
            'name'                      => $this->name,
            'agency'                    => $this->agency ?: null,
            'email'                     => $this->email,
            'password'                  => Hash::make($this->password),
            'locale'                    => $locale,
            'movil'                     => $this->buildFullPhoneNumber(),
            'whatsapp_opt_in'           => true,
            'whatsapp_opt_in_at'        => now(),
            'movil_verification_token'  => \Illuminate\Support\Str::random(64),
        ];

        $user = User::create($userData);

        event(new Registered($user));

        Auth::login($user, true);

        if (config('devdojo.auth.settings.registration_require_email_verification')) {
            return redirect()->route('verification.notice');
        }

        if (session()->get('url.intended') != route('logout.get')) {
            session()->regenerate();
            return redirect()->intended(config('devdojo.auth.settings.redirect_after_auth'));
        } else {
            session()->regenerate();
            return redirect(config('devdojo.auth.settings.redirect_after_auth'));
        }
    }
};

?>

<x-auth::layouts.app :title="__('auth.signup.page_title') . ' - ' . config('app.name')">
    <style>
        body#auth-body { overflow-y: auto !important; height: auto !important; min-height: 100vh; }
        #auth-container { height: auto !important; min-height: 100vh; }
    </style>
    @volt('signup')
    <x-auth::elements.container>
        
        <x-auth::elements.heading :text="__('auth.signup.headline')" :description="__('auth.signup.subheadline')" />
        
        <x-auth::elements.session-message />

        @php
            $priorityIsoCodes = ['AR', 'MX', 'CL', 'ES', 'CO', 'UY', 'PE', 'VE', 'EC', 'BO', 'PY'];
            $phoneCountries = Cache::remember('phone_countries_select', 86400, function () use ($priorityIsoCodes) {
                $priority = \Nnjeim\World\Models\Country::whereIn('iso2', $priorityIsoCodes)
                    ->orderByRaw("CASE iso2
                        WHEN 'AR' THEN 1 WHEN 'MX' THEN 2 WHEN 'CL' THEN 3
                        WHEN 'ES' THEN 4 WHEN 'CO' THEN 5 WHEN 'UY' THEN 6
                        WHEN 'PE' THEN 7 WHEN 'VE' THEN 8 WHEN 'EC' THEN 9
                        WHEN 'BO' THEN 10 WHEN 'PY' THEN 11 ELSE 99 END")
                    ->get(['iso2', 'name', 'phone_code']);
                $others = \Nnjeim\World\Models\Country::whereNotIn('iso2', $priorityIsoCodes)
                    ->orderBy('name')
                    ->get(['iso2', 'name', 'phone_code']);
                return ['priority' => $priority, 'others' => $others];
            });
        @endphp

        <form wire:submit="register" class="space-y-5">
            
            <!-- Nombre -->
            <div>
                <x-auth::elements.input 
                    :label="__('auth.signup.name')" 
                    type="text" 
                    wire:model="name" 
                    autofocus="true" 
                    required 
                />
                @error('name') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
            </div>

            <!-- Inmobiliaria / Empresa (opcional) -->
            <div>
                <x-auth::elements.input 
                    :label="__('auth.signup.agency')" 
                    type="text" 
                    wire:model="agency" 
                    :placeholder="__('auth.signup.agency_placeholder')"
                />
                @error('agency') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
                <p class="text-sm text-gray-500 mt-1">{{ __('auth.signup.agency_help') }}</p>
            </div>

            <!-- Email -->
            <div>
                <x-auth::elements.input 
                    :label="__('auth.signup.email')" 
                    id="email" 
                    name="email" 
                    type="email" 
                    wire:model="email" 
                    autocomplete="email" 
                    required 
                />
                @error('email') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
            </div>

            <!-- Teléfono Móvil con selector de país -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('auth.signup.phone') }} <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-2">
                    {{-- Selector de código de país --}}
                    <div class="w-2/5">
                        <select
                            wire:model="phoneCountryCode"
                            id="phoneCountryCode"
                            name="phoneCountryCode"
                            required
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white"
                        >
                            <option value="" disabled selected>{{ app()->getLocale() === 'en' ? '— Select country —' : '— Selecciona tu país —' }}</option>
                            <optgroup label="— Populares —">
                                @foreach ($phoneCountries['priority'] as $country)
                                    <option value="{{ $country->phone_code }}" @selected($phoneCountryCode === $country->phone_code)>
                                        +{{ $country->phone_code }} {{ $country->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                            <optgroup label="— Todos los países —">
                                @foreach ($phoneCountries['others'] as $country)
                                    <option value="{{ $country->phone_code }}" @selected($phoneCountryCode === $country->phone_code)>
                                        +{{ $country->phone_code }} {{ $country->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        </select>
                    </div>
                    {{-- Número local: solo dígitos 0-9 --}}
                    <div class="flex-1">
                        <input
                            type="tel"
                            wire:model="phoneLocal"
                            id="phoneLocal"
                            name="phoneLocal"
                            placeholder="{{ __('auth.signup.phone_local_placeholder') }}"
                            autocomplete="tel-national"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            required
                            class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            x-on:input="$el.value = $el.value.replace(/[^0-9]/g, ''); $wire.phoneLocal = $el.value"
                            x-on:keydown="if(!/[0-9]|Backspace|Delete|Tab|ArrowLeft|ArrowRight|Home|End/.test($event.key)) $event.preventDefault()"
                        />
                    </div>
                </div>
                @error('phoneCountryCode')
                    <span class="text-red-500 text-sm block mt-1">{{ $message }}</span>
                @enderror
                @error('phoneLocal')
                    <span class="text-red-500 text-sm block mt-1">{{ $message }}</span>
                @enderror
                {{-- Preview del número completo --}}
                <p class="text-sm text-gray-500 mt-1" x-data x-show="$wire.phoneLocal.trim() !== ''">
                    <span class="font-medium">{{ __('auth.signup.phone_preview') }}:</span>
                    <span class="font-mono text-gray-700">
                        +<span x-text="$wire.phoneCountryCode"></span>
                        <span x-text="$wire.phoneLocal.replace(/\D/g, '').replace(/^0+/, '')"></span>
                    </span>
                </p>
                <p class="text-sm text-gray-500 mt-1">{{ __('auth.signup.phone_local_help') }}</p>
            </div>

            <!-- Contraseña -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.signup.password') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input
                        type="password"
                        wire:model="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <button type="button" onclick="togglePassword('password', 'eye-password')" class="absolute text-gray-400 hover:text-gray-600" style="right: 0.625rem; top: 50%; transform: translateY(-50%);" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg id="eye-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.578-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                @error('password') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
            </div>

            <!-- Confirmar Contraseña -->
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">{{ __('auth.signup.password_confirmation') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input
                        type="password"
                        wire:model="password_confirmation"
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 pr-9 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                    <button type="button" onclick="togglePassword('password_confirmation', 'eye-password-confirm')" class="absolute text-gray-400 hover:text-gray-600" style="right: 0.625rem; top: 50%; transform: translateY(-50%);" tabindex="-1" aria-label="Mostrar contraseña">
                        <svg id="eye-password-confirm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.578-3.007-9.964-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>
                @error('password_confirmation') 
                    <span class="text-red-500 text-sm">{{ $message }}</span> 
                @enderror
            </div>

            <script>
            function togglePassword(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
                } else {
                    input.type = 'password';
                    icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.578-3.007-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;
                }
            }
            </script>

            <!-- WhatsApp Opt-in (OBLIGATORIO) -->
            <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="whatsapp_opt_in"
                        id="whatsapp_opt_in"
                        name="whatsapp_opt_in"
                        class="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500 shrink-0"
                    />
                    <span class="text-sm text-gray-700 leading-snug">
                        <svg class="inline-block w-4 h-4 text-green-600 mr-1 -mt-0.5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347"/>
                        </svg>
                        <span class="font-medium">{{ __('auth.signup.whatsapp_opt_in') }}</span>
                        <span class="text-red-500 ml-1">*</span>
                    </span>
                </label>
                @error('whatsapp_opt_in')
                    <span class="text-red-500 text-sm block mt-2">{{ $message }}</span>
                @enderror
            </div>

            <!-- Botón de Registro -->
            <x-auth::elements.button submit="true" rounded="md">
                {{ __('auth.signup.button') }}
            </x-auth::elements.button>
        </form>

        <!-- Link a Login -->
        <div class="mt-6 text-center">
            <span class="text-sm opacity-70">{{ __('auth.signup.have_account') }}</span>
            <x-auth::elements.text-link href="{{ route('auth.login') }}">
                {{ __('auth.signup.login_link') }}
            </x-auth::elements.text-link>
        </div>

    </x-auth::elements.container>
    @endvolt
</x-auth::layouts.app>
