<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Verified;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('auth::components.layouts.app')]
class VerifyEmail extends Component
{
    public function resend(): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirect('/dashboard');
            return;
        }

        $user->sendEmailVerificationNotification();

        session()->flash('resent', true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.auth.verify-email');
    }
}
