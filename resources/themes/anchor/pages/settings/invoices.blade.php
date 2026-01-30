<?php
    use function Laravel\Folio\{middleware, name};
    middleware('auth');
    name('settings.invoices');
?>

@php
    $invoices = auth()->user()->invoices();
@endphp

<x-layouts.app>
        <div class="relative">
            <x-app.settings-layout
                title="{{ __('settings.invoices.title') }}"
                description="{{ __('settings.invoices.invoice_history') }}"
            >
                @empty($invoices)
                    <x-app.alert id="dashboard_alert">{{ __('settings.invoices.no_invoices') }}</x-app.alert>
                    <p class="mt-3">{{ __('settings.invoices.no_invoices_message') }}</p>
                @else
                    <div class="overflow-hidden border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-zinc-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left uppercase bg-white text-zinc-500">{{ __('settings.invoices.amount') }}</th>
                                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left uppercase bg-white text-zinc-500">{{ __('settings.invoices.date') }}</th>
                                    <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-right uppercase bg-white text-zinc-500">{{ __('settings.invoices.pdf_download') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr wire:key="invoice-{{ $invoice->id }}" class="@if($loop->index%2 == 0){{ 'bg-zinc-50' }}@else{{ 'bg-white' }}@endif">
                                        <td class="px-6 py-4 text-sm font-medium leading-5 text-left whitespace-no-wrap text-zinc-900">${{ $invoice->total }}</td>
                                        <td class="px-6 py-4 text-sm font-medium leading-5 whitespace-no-wrap text-zinc-900">{{ $invoice->created }}</td>
                                        <td class="px-6 py-4 text-sm font-medium leading-5 text-right whitespace-no-wrap">
                                            <a href="{{ $invoice->download }}" @if(config("wave.billing_provider") == 'stripe') target="_blank" @endif class="mr-2 text-indigo-600 hover:underline focus:outline-none">{{ __('settings.invoices.download') }}</a>
                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endempty

            </x-app.settings-layout>
        </div>
</x-layouts.app>
