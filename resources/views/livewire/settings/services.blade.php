<?php

use App\Models\UserProfileService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingId = null;
    public string $nameEs = '';
    public string $nameEn = '';
    public string $descriptionEs = '';
    public string $descriptionEn = '';
    public string $icon = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    public function getIconOptionsProperty(): array
    {
        return [
            '' => __('settings.public_profile.icon_none'),
            'phosphor-buildings' => __('settings.public_profile.icon_buildings'),
            'phosphor-house' => __('settings.public_profile.icon_house'),
            'phosphor-handshake' => __('settings.public_profile.icon_handshake'),
            'phosphor-magnifying-glass' => __('settings.public_profile.icon_search'),
            'phosphor-users-three' => __('settings.public_profile.icon_team'),
            'phosphor-note' => __('settings.public_profile.icon_note'),
        ];
    }

    public function getServicesProperty(): Collection
    {
        return auth()->user()->profileServices()->get();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'nameEs' => ['required', 'string', 'max:255'],
            'nameEn' => ['nullable', 'string', 'max:255'],
            'descriptionEs' => ['nullable', 'string', 'max:2000'],
            'descriptionEn' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', Rule::in(array_keys($this->iconOptions))],
            'sortOrder' => ['integer', 'min:0', 'max:999'],
        ]);

        $service = $this->editingId
            ? UserProfileService::where('user_id', auth()->id())->findOrFail($this->editingId)
            : new UserProfileService(['user_id' => auth()->id()]);

        $service->fill([
            'name_i18n' => array_filter(['es' => $validated['nameEs'], 'en' => $validated['nameEn']]),
            'description_i18n' => array_filter(['es' => $validated['descriptionEs'], 'en' => $validated['descriptionEn']]),
            'icon' => $validated['icon'],
            'sort_order' => $validated['sortOrder'],
            'is_active' => $this->isActive,
        ])->save();

        $this->resetForm();
        session()->flash('service-saved', __('settings.public_profile.service_saved'));
    }

    public function edit(int $id): void
    {
        $service = UserProfileService::where('user_id', auth()->id())->findOrFail($id);
        $this->editingId = $service->id;
        $this->nameEs = $service->name_i18n['es'] ?? '';
        $this->nameEn = $service->name_i18n['en'] ?? '';
        $this->descriptionEs = $service->description_i18n['es'] ?? '';
        $this->descriptionEn = $service->description_i18n['en'] ?? '';
        $this->icon = in_array($service->icon, array_keys($this->iconOptions), true)
            ? $service->icon
            : '';
        $this->sortOrder = $service->sort_order;
        $this->isActive = $service->is_active;
    }

    public function remove(int $id): void
    {
        UserProfileService::where('user_id', auth()->id())->findOrFail($id)->delete();
        session()->flash('service-saved', __('settings.public_profile.service_deleted'));
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'nameEs', 'nameEn', 'descriptionEs', 'descriptionEn', 'icon', 'sortOrder', 'isActive']);
        $this->sortOrder = 0;
        $this->isActive = true;
    }
}; ?>

<div class="space-y-8">
    @if (session('service-saved'))
        <div class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('service-saved') }}</div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-lg border border-zinc-200 p-5 dark:border-zinc-800">
        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $editingId ? __('settings.public_profile.edit_service') : __('settings.public_profile.new_service') }}</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <input wire:model="nameEs" placeholder="{{ __('settings.public_profile.name_es') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <input wire:model="nameEn" placeholder="{{ __('settings.public_profile.name_en') }}" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
            <textarea wire:model="descriptionEs" placeholder="{{ __('settings.public_profile.description_es') }}" rows="3" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
            <textarea wire:model="descriptionEn" placeholder="{{ __('settings.public_profile.description_en') }}" rows="3" class="rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"></textarea>
            <div>
                <label for="service_icon" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.icon') }}</label>
                <select id="service_icon" wire:model="icon" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    @foreach ($this->iconOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-zinc-500">{{ __('settings.public_profile.icon_help') }}</p>
                @if ($icon && in_array($icon, \App\Models\UserProfileService::allowedIconComponents(), true))
                    <div class="mt-2 flex items-center gap-2 text-sm text-blue-600">
                        <x-dynamic-component :component="$icon" class="h-6 w-6" />
                        <span>{{ $this->iconOptions[$icon] }}</span>
                    </div>
                @endif
            </div>
            <div>
                <label for="service_sort_order" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('settings.public_profile.order_label') }}</label>
                <input id="service_sort_order" type="number" wire:model="sortOrder" min="0" placeholder="0" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                <p class="mt-1 text-xs text-zinc-500">{{ __('settings.public_profile.order_help') }}</p>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300"><input type="checkbox" wire:model="isActive"> {{ __('settings.public_profile.active') }}</label>
        @foreach (['nameEs', 'nameEn', 'descriptionEs', 'descriptionEn', 'icon', 'sortOrder'] as $field)
            @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @endforeach
        <div class="flex justify-end gap-2">
            @if ($editingId)<button type="button" wire:click="resetForm" class="rounded-md px-4 py-2 text-sm text-zinc-600">{{ __('settings.public_profile.cancel') }}</button>@endif
            <x-button type="submit">{{ __('settings.public_profile.save') }}</x-button>
        </div>
    </form>

    <div class="space-y-3">
        @forelse ($this->services as $service)
            <div wire:key="service-{{ $service->id }}" class="flex items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <div>
                    <h4 class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $service->localizedName('es') }}</h4>
                    @if ($service->localizedDescription('es'))<p class="mt-1 text-sm text-zinc-500">{{ $service->localizedDescription('es') }}</p>@endif
                    <p class="mt-2 text-xs text-zinc-500">{{ $service->is_active ? __('settings.public_profile.active') : __('settings.public_profile.inactive') }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" wire:click="edit({{ $service->id }})" class="text-sm text-blue-600">{{ __('settings.public_profile.edit') }}</button>
                    <button type="button" wire:click="remove({{ $service->id }})" wire:confirm="{{ __('settings.public_profile.delete_confirm') }}" class="text-sm text-red-600">{{ __('settings.public_profile.delete') }}</button>
                </div>
            </div>
        @empty
            <p class="text-sm text-zinc-500">{{ __('settings.public_profile.no_services') }}</p>
        @endforelse
    </div>
</div>
