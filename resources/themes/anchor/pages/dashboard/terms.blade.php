<?php
    use function Laravel\Folio\{middleware, name};
    
    middleware('auth');
    name('dashboard.terms');
?>

<x-layouts.app>
    <x-app.container>
        <div class="max-w-4xl mx-auto py-2 px-2 sm:px-6 lg:px-4">
            <div class="bg-white shadow rounded-lg p-8">
<div class="max-w-4xl mx-auto mt-2">

            <h1 class="text-3xl font-extrabold text-gray-900 mb-6">
                <span>{{ __('dashboard.terms.title') }}</span>
                
            </h1>

        </div>

        
        <div class="max-w-4xl mx-auto">
            <p class="mb-3">{!! __('dashboard.terms.intro') !!}</p>
            <ul>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.membership_title') }}</strong><br>{{ __('dashboard.terms.membership_text') }}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.listings_title') }}</strong><br>{{ __('dashboard.terms.listings_text') }}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.commissions_title') }}</strong><br>{{ __('dashboard.terms.commissions_text') }}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.delegation_title') }}</strong><br>{{ __('dashboard.terms.delegation_text') }}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.raxta_clients_title') }}</strong><br>{!! __('dashboard.terms.raxta_clients_text') !!}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.member_operations_title') }}</strong><br>{!! __('dashboard.terms.member_operations_text') !!}</p></li>
                <li><p class="mb-3"><strong>{{ __('dashboard.terms.compliance_title') }}</strong><br>{!! __('dashboard.terms.compliance_text') !!}</p></li>
            </ul>
            
            <p class="mb-3 mt-3"><strong>{{ __('dashboard.terms.acceptance_text') }}</strong></p>
        </div>
                @if(!auth()->user()->hasAcceptedTerms())
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <form action="{{ route('terms.accept') }}" method="POST">
                            @csrf
                            <div class="flex items-start mb-4">
                                <div class="flex items-center h-5">
                                    <input id="accept_terms" name="accept_terms" type="checkbox" required
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="accept_terms" class="font-medium text-gray-700">
                                        {{ __('dashboard.terms.checkbox_label') }}
                                    </label>
                                </div>
                            </div>
                            <button type="submit" 
                                class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                {{ __('dashboard.terms.accept_button') }}
                            </button>
                        </form>
                    </div>
                @else
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                            <div class="flex">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        {{ __('dashboard.terms.already_accepted', ['date' => auth()->user()->terms_accepted_at->format('d/m/Y H:i')]) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-700 font-medium">
                                {{ __('dashboard.terms.back_to_dashboard') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </x-app.container>
</x-layouts.app>
