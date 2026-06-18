<?php

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use function Laravel\Folio\{middleware, name};
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Devdojo\Auth\Traits\HasConfigs;

if(!isset($_GET['preview']) || (isset($_GET['preview']) && $_GET['preview'] != true) || !app()->isLocal()){
    middleware(['guest']);
}

name('auth.login');

new class extends Component
{
    use HasConfigs;

    #[Validate('required|email')]
    public $email = '';

    #[Validate('required')]
    public $password = '';

    #[Validate('bool')]
    public $rememberMe = false;

    public $showPasswordField = false;

    public $showIdentifierInput = true;
    public $showSocialProviderInfo = false;

    public $language = [];

    public $twoFactorEnabled = true;

    public $userSocialProviders = [];

    public $userModel = null;

    public function mount(){
        $this->loadConfigs();
        $this->twoFactorEnabled = $this->settings->enable_2fa;
        $this->userModel = app(config('auth.providers.users.model'));
    }

    public function editIdentity(){
        if($this->showPasswordField){
            $this->showPasswordField = false;
            return;
        }

        $this->showIdentifierInput = true;
        $this->showSocialProviderInfo = false;
    }

    public function authenticate()
    {

        if(!$this->showPasswordField){
            $this->validateOnly('email');
            $userTryingToValidate = $this->userModel->where('email', $this->email)->first();
            if(!is_null($userTryingToValidate)){
                if(is_null($userTryingToValidate->password)){
                    $this->userSocialProviders = [];
                    foreach($userTryingToValidate->socialProviders->all() as $provider){
                        array_push($this->userSocialProviders, $provider->provider_slug);
                    }
                    $this->showIdentifierInput = false;
                    $this->showSocialProviderInfo = true;
                    return;
                }
            }

            if(config('devdojo.auth.settings.check_account_exists_before_login') && is_null($userTryingToValidate)){
                $this->js("setTimeout(function(){ window.dispatchEvent(new CustomEvent('focus-email', {})); }, 10);");
                $this->addError('email', trans(config('devdojo.auth.language.login.couldnt_find_your_account')));
                return;
            }

            $this->showPasswordField = true;
            $this->js("setTimeout(function(){ window.dispatchEvent(new CustomEvent('focus-password', {})); }, 10);");
            return;
        }


        $this->validate();

        $credentials = ['email' => $this->email, 'password' => $this->password];
        
        event(new Attempting('web', $credentials, false));
        
        if(!\Auth::validate($credentials)){
            event(new Failed('web', null, $credentials)); 
            $this->addError('password', trans('auth.failed'));
            return;
        }

        $userAttemptingLogin = $this->userModel->where('email', $this->email)->first();

        if(!isset($userAttemptingLogin->id)){
            $this->addError('password', trans('auth.failed'));
            return;
        }

        if($this->twoFactorEnabled && !is_null($userAttemptingLogin->two_factor_confirmed_at)){
            session()->put([
                'login.id' => $userAttemptingLogin->getKey()
            ]);

            return redirect()->route('auth.two-factor-challenge');

        } else {
            if (!Auth::attempt($credentials, $this->rememberMe)) {
                event(new Failed('web', null, $credentials));
                $this->addError('password', trans('auth.failed'));
                return;
            }

            event(new Login(auth()->guard('web'), $this->userModel->where('email', $this->email)->first(), true));

            if(session()->get('url.intended') != route('logout.get')){
                session()->regenerate();
                redirect()->intended(config('devdojo.auth.settings.redirect_after_auth'));
            } else {
                session()->regenerate();
                return redirect(config('devdojo.auth.settings.redirect_after_auth'));
            }
        }

    }
};

?>

<x-auth::layouts.app title="{{ config('devdojo.auth.language.login.page_title') }}">

    @volt('auth.login')
        <x-auth::elements.container>

            <x-auth::elements.heading
                :text="($language->login->headline ?? 'No Heading')"
                :description="($language->login->subheadline ?? 'No Description')"
                :show_subheadline="($language->login->show_subheadline ?? false)" />

            <x-auth::elements.session-message />

            @if(config('devdojo.auth.settings.login_show_social_providers') && config('devdojo.auth.settings.social_providers_location') == 'top')
                <x-auth::elements.social-providers />
            @endif

            <form wire:submit="authenticate" class="space-y-5">

                @if($showPasswordField)
                    <x-auth::elements.input-placeholder value="{{ $email }}">
                        <button type="button" data-auth="edit-email-button" wire:click="editIdentity" class="font-medium text-blue-500">{{ config('devdojo.auth.language.login.edit') }}</button>
                    </x-auth::elements.input-placeholder>
                @else
                    @if($showIdentifierInput)
                        <x-auth::elements.input :label="config('devdojo.auth.language.login.email_address')" type="email" wire:model="email" autofocus="true" data-auth="email-input" id="email" name="email" autocomplete="email" required />
                    @endif
                @endif

                @if($showSocialProviderInfo)
                    <div class="p-4 text-sm border rounded-md bg-zinc-50 border-zinc-200">
                        <span>{{ str_replace('__social_providers_list__', implode(', ', $userSocialProviders), config('devdojo.auth.language.login.social_auth_authenticated_message')) }}</span>
                        <button wire:click="editIdentity" type="button" class="underline translate-x-0.5">{{ config('devdojo.auth.language.login.change_email') }}</button>
                    </div>

                    @if(!config('devdojo.auth.settings.login_show_social_providers'))
                        <x-auth::elements.social-providers
                            :socialProviders="\Devdojo\Auth\Helper::getProvidersFromArray($userSocialProviders)"
                            :separator="false"
                        />
                    @endif
                @endif

                @php
                    $passwordFieldClasses = $showPasswordField ? 'flex flex-col gap-6' : 'hidden';
                @endphp

                <div class="{{ $passwordFieldClasses }}">
                    <div
                        x-data="{
                            focusedOrFilled: false,
                            passwordVisible: false,
                            focused() { this.focusedOrFilled = true; },
                            blurred() { if (this.$refs.input.value == '') { this.focusedOrFilled = false; } }
                        }"
                        class="w-full h-auto"
                    >
                        <div class="flex relative flex-col justify-center h-11">
                            <div class="flex relative">
                                <label
                                    for="password"
                                    @click="$refs.input.focus()"
                                    :class="{ 'top-0 -translate-y-1 ml-2 text-xs auth-component-input-label-focused' : focusedOrFilled, 'top-[16px] ml-2.5 text-[15px] text-gray-500' : !focusedOrFilled }"
                                    class="block absolute top-0 z-10 px-1.5 py-0 font-normal leading-normal bg-white duration-300 ease-out cursor-text auth-component-input dark:text-gray-300"
                                    x-cloak
                                >
                                    {{ config('devdojo.auth.language.login.password') }}
                                </label>

                                <div class="mt-1.5 w-full rounded-md shadow-sm auth-component-input-container relative">
                                    <input
                                        wire:model="password"
                                        data-auth="password-input"
                                        @focus-password.window="$el.focus()"
                                        id="password"
                                        name="password"
                                        :type="passwordVisible ? 'text' : 'password'"
                                        x-ref="input"
                                        @focus="focused()"
                                        @blur="blurred()"
                                        autocomplete="current-password"
                                        class="auth-component-input appearance-none flex w-full h-11 px-3.5 pr-10 text-sm bg-white border rounded-md border-gray-300 ring-offset-background placeholder:text-gray-500 focus:outline-none focus:ring-1 focus:ring-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 @error('password') border-red-300 text-red-900 placeholder-red-300 focus:border-red-300 focus:ring-red @enderror"
                                    />

                                    <button
                                        type="button"
                                        @click="passwordVisible = !passwordVisible"
                                        class="absolute text-gray-400 hover:text-gray-600"
                                        style="right: 0.625rem; top: 50%; transform: translateY(-50%);"
                                        tabindex="-1"
                                        aria-label="Mostrar contraseña"
                                    >
                                        <svg x-show="!passwordVisible" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.578-3.007-9.964-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        <svg x-show="passwordVisible" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @error('password')
                            <p class="my-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-auth::elements.checkbox :label="config('devdojo.auth.language.login.remember_me')" wire:model="rememberMe" id="remember-me" data-auth="remember-me-input" />
                    <div class="flex items-center justify-between text-sm leading-5">
                        <x-auth::elements.text-link href="{{ route('auth.password.request') }}" data-auth="forgot-password-link">{{ config('devdojo.auth.language.login.forget_password') }}</x-auth::elements.text-link>
                    </div>
                </div>

                <x-auth::elements.button type="primary" data-auth="submit-button" rounded="md" size="md" submit="true">
                    {{ config('devdojo.auth.language.login.button') }}
                </x-auth::elements.button>
            </form>


            @if(config('devdojo.auth.settings.registration_enabled', true))
                <div class="mt-3 space-x-0.5 text-sm leading-5 @if(config('devdojo.auth.settings.center_align_text')){{ 'text-center' }}@else{{ 'text-left' }}@endif" style="color:{{ config('devdojo.auth.appearance.color.text') }}">
                    <span class="opacity-[47%]"> {{ config('devdojo.auth.language.login.dont_have_an_account') }} </span>
                    <x-auth::elements.text-link data-auth="register-link" href="{{ route('auth.register') }}">{{ config('devdojo.auth.language.login.sign_up') }}</x-auth::elements.text-link>
                </div>
            @endif

            @if(!$showPasswordField)
                <div class="mt-2 text-sm leading-5 @if(config('devdojo.auth.settings.center_align_text')){{ 'text-center' }}@else{{ 'text-left' }}@endif">
                    <x-auth::elements.text-link href="{{ route('auth.password.request') }}" data-auth="forgot-password-link-initial">{{ config('devdojo.auth.language.login.forget_password') }}</x-auth::elements.text-link>
                </div>
            @endif

            @if(config('devdojo.auth.settings.login_show_social_providers') && config('devdojo.auth.settings.social_providers_location') != 'top')
                <x-auth::elements.social-providers />
            @endif

        </x-auth::elements.container>
    @endvolt

</x-auth::layouts.app>
