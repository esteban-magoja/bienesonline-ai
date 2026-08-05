<?php

use App\Models\UserProfileSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?string $headline = null;
    public ?string $websiteUrl = null;
    public ?string $instagram = null;
    public ?string $facebook = null;
    public ?string $linkedin = null;
    public ?string $youtube = null;
    public ?string $officeHours = null;
    public bool $showEmail = true;
    public bool $showPhone = true;
    public bool $showAddress = true;
    public $coverImage;
    public ?string $coverImagePath = null;

    public function mount(): void
    {
        $settings = auth()->user()->profileSetting;

        if ($settings === null) {
            return;
        }

        $socialLinks = $settings->social_links ?? [];

        $this->headline = $settings->headline;
        $this->websiteUrl = $settings->website_url;
        $this->instagram = $socialLinks['instagram'] ?? null;
        $this->facebook = $socialLinks['facebook'] ?? null;
        $this->linkedin = $socialLinks['linkedin'] ?? null;
        $this->youtube = $socialLinks['youtube'] ?? null;
        $this->officeHours = $settings->office_hours['text'] ?? null;
        $this->showEmail = $settings->show_email;
        $this->showPhone = $settings->show_phone;
        $this->showAddress = $settings->show_address;
        $this->coverImagePath = $settings->cover_image_path;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'headline' => ['nullable', 'string', 'max:255'],
            'websiteUrl' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'facebook' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'youtube' => ['nullable', 'url', 'max:255'],
            'officeHours' => ['nullable', 'string', 'max:1000'],
            'coverImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $settings = UserProfileSetting::firstOrNew(['user_id' => auth()->id()]);

        if ($this->coverImage !== null) {
            $settings->cover_image_path = $this->coverImage->store('profile-covers', 'public');
        }

        $settings->fill([
            'headline' => $validated['headline'],
            'website_url' => $validated['websiteUrl'],
            'social_links' => array_filter([
                'instagram' => $validated['instagram'],
                'facebook' => $validated['facebook'],
                'linkedin' => $validated['linkedin'],
                'youtube' => $validated['youtube'],
            ]),
            'office_hours' => $validated['officeHours'] !== null
                ? ['text' => $validated['officeHours']]
                : null,
            'show_email' => $this->showEmail,
            'show_phone' => $this->showPhone,
            'show_address' => $this->showAddress,
        ]);
        $settings->save();

        $this->coverImage = null;
        $this->coverImagePath = $settings->cover_image_path;
        session()->flash('profile-settings-saved', __('settings.public_profile.saved_success'));
    }
}; ?>

<div class="space-y-6">
    @if (session('profile-settings-saved'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('profile-settings-saved') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="md:col-span-2">
                <label for="headline" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.headline') }}</label>
                <input id="headline" type="text" wire:model="headline" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                @error('headline') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="websiteUrl" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.website') }}</label>
                <input id="websiteUrl" type="url" wire:model="websiteUrl" placeholder="https://" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                @error('websiteUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label for="coverImage" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.cover_image') }}</label>
                <input id="coverImage" type="file" wire:model="coverImage" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-zinc-600">
                @if ($coverImagePath)
                    <img src="{{ Storage::disk('public')->url($coverImagePath) }}" alt="" class="mt-3 h-32 w-full rounded-md object-cover">
                @endif
                @error('coverImage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @foreach (['instagram', 'facebook', 'linkedin', 'youtube'] as $network)
                <div>
                    <label for="{{ $network }}" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ ucfirst($network) }}</label>
                    <input id="{{ $network }}" type="url" wire:model="{{ $network }}" placeholder="https://" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    @error($network) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="md:col-span-2">
                <label for="officeHours" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.office_hours') }}</label>
                <textarea id="officeHours" wire:model="officeHours" rows="3" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
                @error('officeHours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('settings.public_profile.contact_visibility') }}</legend>
            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><input type="checkbox" wire:model="showEmail"> {{ __('settings.public_profile.show_email') }}</label>
            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><input type="checkbox" wire:model="showPhone"> {{ __('settings.public_profile.show_phone') }}</label>
            <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><input type="checkbox" wire:model="showAddress"> {{ __('settings.public_profile.show_address') }}</label>
        </fieldset>

        <div class="text-right">
            <x-button type="submit">{{ __('settings.public_profile.save') }}</x-button>
        </div>
    </form>
</div>
