<?php

use App\Models\UserProfileMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?int $editingId = null;
    public string $name = '';
    public string $role = '';
    public string $bioEs = '';
    public string $bioEn = '';
    public string $specialtiesText = '';
    public string $areasText = '';
    public string $phone = '';
    public string $email = '';
    public bool $showPhone = false;
    public bool $showEmail = false;
    public int $sortOrder = 0;
    public bool $isVisible = true;
    public $photo;
    public ?string $photoPath = null;

    public function getMembersProperty(): Collection
    {
        return auth()->user()->profileMembers()->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'bioEs' => ['nullable', 'string', 'max:2000'],
            'bioEn' => ['nullable', 'string', 'max:2000'],
            'specialtiesText' => ['nullable', 'string', 'max:1000'],
            'areasText' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'sortOrder' => ['integer', 'min:0', 'max:999'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $member = $this->editingId
            ? UserProfileMember::where('user_id', auth()->id())->findOrFail($this->editingId)
            : new UserProfileMember(['user_id' => auth()->id()]);

        if ($this->photo !== null) {
            $member->photo_path = $this->photo->store('profile-members', 'public');
        }

        $member->fill([
            'name' => $validated['name'],
            'role' => $validated['role'],
            'bio_i18n' => array_filter(['es' => $validated['bioEs'], 'en' => $validated['bioEn']]),
            'specialties' => $this->splitList($validated['specialtiesText']),
            'areas' => $this->splitList($validated['areasText']),
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'show_phone' => $this->showPhone,
            'show_email' => $this->showEmail,
            'sort_order' => $validated['sortOrder'],
            'is_visible' => $this->isVisible,
        ])->save();

        $this->resetForm();
        session()->flash('member-saved', __('settings.public_profile.member_saved'));
    }

    public function edit(int $id): void
    {
        $member = UserProfileMember::where('user_id', auth()->id())->findOrFail($id);
        $this->editingId = $member->id;
        $this->name = $member->name;
        $this->role = $member->role ?? '';
        $this->bioEs = $member->bio_i18n['es'] ?? '';
        $this->bioEn = $member->bio_i18n['en'] ?? '';
        $this->specialtiesText = implode(', ', $member->specialties ?? []);
        $this->areasText = implode(', ', $member->areas ?? []);
        $this->phone = $member->phone ?? '';
        $this->email = $member->email ?? '';
        $this->showPhone = $member->show_phone;
        $this->showEmail = $member->show_email;
        $this->sortOrder = $member->sort_order;
        $this->isVisible = $member->is_visible;
        $this->photoPath = $member->photo_path;
    }

    public function remove(int $id): void
    {
        UserProfileMember::where('user_id', auth()->id())->findOrFail($id)->delete();
        session()->flash('member-saved', __('settings.public_profile.member_deleted'));
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'role', 'bioEs', 'bioEn', 'specialtiesText', 'areasText', 'phone', 'email', 'showPhone', 'showEmail', 'sortOrder', 'isVisible', 'photo', 'photoPath']);
        $this->sortOrder = 0;
        $this->isVisible = true;
    }

    private function splitList(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<div class="space-y-8">
    @if (session('member-saved'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('member-saved') }}</div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-lg border border-zinc-200 p-5 dark:border-zinc-800">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $editingId ? __('settings.public_profile.edit_member') : __('settings.public_profile.new_member') }}</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <input wire:model="name" placeholder="{{ __('settings.public_profile.member_name') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input wire:model="role" placeholder="{{ __('settings.public_profile.member_role') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <textarea wire:model="bioEs" placeholder="{{ __('settings.public_profile.bio_es') }}" rows="3" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
            <textarea wire:model="bioEn" placeholder="{{ __('settings.public_profile.bio_en') }}" rows="3" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
            <input wire:model="specialtiesText" placeholder="{{ __('settings.public_profile.specialties') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input wire:model="areasText" placeholder="{{ __('settings.public_profile.areas') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input wire:model="phone" placeholder="{{ __('settings.public_profile.member_phone') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input type="email" wire:model="email" placeholder="{{ __('settings.public_profile.member_email') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="text-sm text-zinc-600">
            <input type="number" wire:model="sortOrder" min="0" placeholder="{{ __('settings.public_profile.order') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
        </div>
        @if ($photoPath)<img src="{{ Storage::disk('public')->url($photoPath) }}" alt="" class="h-24 w-24 rounded-full object-cover">@endif
        @foreach (['name', 'role', 'bioEs', 'bioEn', 'specialtiesText', 'areasText', 'phone', 'email', 'sortOrder', 'photo'] as $field)
            @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endforeach
        <div class="flex flex-wrap gap-4 text-sm text-zinc-700 dark:text-zinc-300">
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="showPhone"> {{ __('settings.public_profile.show_phone') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="showEmail"> {{ __('settings.public_profile.show_email') }}</label>
            <label class="flex items-center gap-2"><input type="checkbox" wire:model="isVisible"> {{ __('settings.public_profile.visible') }}</label>
        </div>
        <div class="flex justify-end gap-2">
            @if ($editingId)<button type="button" wire:click="resetForm" class="rounded-md px-4 py-2 text-sm text-zinc-600">{{ __('settings.public_profile.cancel') }}</button>@endif
            <x-button type="submit">{{ __('settings.public_profile.save') }}</x-button>
        </div>
    </form>

    <div class="space-y-3">
        @forelse ($this->members as $member)
            <div wire:key="member-{{ $member->id }}" class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-start gap-3">
                    @if ($member->photo_path)<img src="{{ Storage::disk('public')->url($member->photo_path) }}" alt="" class="h-14 w-14 rounded-full object-cover">@endif
                    <div><h4 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $member->name }}</h4><p class="text-sm text-zinc-500">{{ $member->role }}</p><p class="mt-1 text-xs text-zinc-500">{{ $member->is_visible ? __('settings.public_profile.visible') : __('settings.public_profile.hidden') }}</p></div>
                </div>
                <div class="flex shrink-0 gap-2"><button type="button" wire:click="edit({{ $member->id }})" class="text-sm text-blue-600">{{ __('settings.public_profile.edit') }}</button><button type="button" wire:click="remove({{ $member->id }})" wire:confirm="{{ __('settings.public_profile.delete_confirm') }}" class="text-sm text-red-600">{{ __('settings.public_profile.delete') }}</button></div>
            </div>
        @empty
            <p class="text-sm text-zinc-500">{{ __('settings.public_profile.no_members') }}</p>
        @endforelse
    </div>
</div>
