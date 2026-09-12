<?php

use Wave\Http\Livewire\Billing\Checkout;

it('allows Livewire to return its external checkout redirect', function (): void {
    $method = new ReflectionMethod(Checkout::class, 'redirectToStripeCheckout');

    expect($method->getReturnType())->toBeNull();
});
