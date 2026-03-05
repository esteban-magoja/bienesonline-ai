<?php

use function Laravel\Folio\{middleware, name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\PropertyListing;
use App\Models\PropertyImage;
use Illuminate\Support\Facades\Storage;

middleware('auth');
name('property-listings.images');

new class extends Component {
    use WithFileUploads;

    public PropertyListing $listing;
    public $images;
    public array $newUploads = [];

    public function mount(int $id): void
    {
        $this->listing = PropertyListing::where('user_id', auth()->id())->with('images')->findOrFail($id);
        $this->images = $this->listing->images()->orderByDesc('is_primary')->orderBy('sort_order')->get();
    }

    public function setPrimary(int $imageId): void
    {
        $image = PropertyImage::where('property_listing_id', $this->listing->id)->findOrFail($imageId);

        // Quitar flag primary de todas las imágenes del anuncio
        PropertyImage::where('property_listing_id', $this->listing->id)->update(['is_primary' => false]);

        // Marcar la seleccionada
        $image->update(['is_primary' => true]);

        $this->images = $this->listing->images()->orderByDesc('is_primary')->orderBy('sort_order')->get();
        $this->dispatch('notify', __('listings.images.primary_set'));
    }

    public function deleteImage(int $imageId): void
    {
        $image = PropertyImage::where('property_listing_id', $this->listing->id)->findOrFail($imageId);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        // Si era la principal y quedan imágenes, auto-asignar la primera
        $remaining = $this->listing->images()->get();
        if ($remaining->count() > 0 && !$remaining->where('is_primary', true)->count()) {
            $remaining->first()->update(['is_primary' => true]);
        }

        $this->images = $this->listing->images()->orderByDesc('is_primary')->orderBy('sort_order')->get();
        $this->dispatch('notify', __('listings.images.deleted'));
    }

    public function saveNewImages(): void
    {
        $this->validate(['newUploads.*' => 'image|max:10240']);

        $hasPrimary = $this->listing->images()->where('is_primary', true)->exists();

        foreach ($this->newUploads as $index => $upload) {
            $path = $upload->store('property_images', 'public');
            $isPrimary = !$hasPrimary && $index === 0;
            $this->listing->images()->create([
                'image_path'  => $path,
                'image_url'   => Storage::url($path),
                'is_primary'  => $isPrimary,
                'sort_order'  => $this->listing->images()->max('sort_order') + 1,
            ]);
            if ($isPrimary) {
                $hasPrimary = true;
            }
        }

        $this->newUploads = [];
        $this->images = $this->listing->images()->orderByDesc('is_primary')->orderBy('sort_order')->get();
        $this->dispatch('notify', __('listings.images.uploaded'));
        $this->dispatch('upload-finished');
    }
};
?>

<x-layouts.app>
    @volt('property-listings.images')
    <x-app.container>

        {{-- Notificación toast --}}
        <div
            x-data="{ show: false, msg: '' }"
            @notify.window="msg = $event.detail[0]; show = true; setTimeout(() => show = false, 3000)"
            x-show="show"
            x-transition
            class="fixed bottom-4 right-4 z-50 px-4 py-3 text-sm font-medium text-white bg-green-600 rounded-lg shadow-lg"
            style="display:none"
        >
            <span x-text="msg"></span>
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <a href="/property-listings" class="text-sm text-indigo-600 hover:underline dark:text-indigo-400">
                    ← {{ __('listings.back') }}
                </a>
                <h1 class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ __('listings.images.manage_title') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $listing->title }}</p>
            </div>
        </div>

        {{-- Grid de imágenes existentes --}}
        <div class="mt-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">{{ __('listings.images.current_images') }}</h2>

            @if($images->count() > 0)
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($images as $image)
                        <div class="relative group rounded-lg overflow-hidden border-2 {{ $image->is_primary ? 'border-indigo-500' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800 shadow-sm">
                            {{-- Imagen --}}
                            <img
                                src="{{ $image->image_url }}"
                                alt="{{ $image->alt_text ?? $listing->title }}"
                                class="object-cover w-full h-40"
                            >

                            {{-- Badge principal --}}
                            @if($image->is_primary)
                                <div class="absolute top-2 left-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold text-white bg-indigo-600 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        {{ __('listings.images.primary') }}
                                    </span>
                                </div>
                            @endif

                            {{-- Acciones --}}
                            <div class="p-2 flex items-center justify-between gap-2">
                                @if(!$image->is_primary)
                                    <button
                                        wire:click="setPrimary({{ $image->id }})"
                                        wire:loading.attr="disabled"
                                        class="flex-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 hover:underline truncate"
                                        title="{{ __('listings.images.set_primary') }}"
                                    >
                                        {{ __('listings.images.set_primary') }}
                                    </button>
                                @else
                                    <span class="flex-1 text-xs text-gray-400">{{ __('listings.images.is_primary') }}</span>
                                @endif

                                <button
                                    wire:click="deleteImage({{ $image->id }})"
                                    wire:loading.attr="disabled"
                                    wire:confirm="{{ __('listings.images.confirm_delete') }}"
                                    class="text-xs font-medium text-red-500 hover:text-red-700 hover:underline"
                                >
                                    {{ __('listings.delete') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-10 text-center text-gray-400 dark:text-gray-500 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                    <svg class="w-10 h-10 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ __('listings.images.no_images') }}
                </div>
            @endif
        </div>

        {{-- Subir nuevas imágenes --}}
        <div class="mt-10">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">{{ __('listings.images.upload_new') }}</h2>

            <form
                wire:submit.prevent="saveNewImages"
                x-data="imageResizer()"
                @upload-finished.window="isResizing = false"
                class="space-y-4"
            >
                <div>
                    <input
                        type="file"
                        x-ref="imageInput"
                        @change="handleFiles"
                        id="newUploads"
                        multiple
                        accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    >
                    <div x-show="isResizing" class="mt-2 flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('listings.processing_images') }}
                    </div>
                    @error('newUploads.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Preview de nuevas imágenes --}}
                @if($newUploads)
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                        @foreach($newUploads as $upload)
                            <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <img src="{{ $upload->temporaryUrl() }}" class="object-cover w-full h-24">
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="saveNewImages"
                        @if(empty($newUploads)) disabled @endif
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        <span wire:loading.remove wire:target="saveNewImages">{{ __('listings.images.save_new') }}</span>
                        <span wire:loading wire:target="saveNewImages">{{ __('listings.saving') }}</span>
                    </button>
                </div>
            </form>
        </div>

    </x-app.container>
    @endvolt
</x-layouts.app>

<script>
function imageResizer() {
    return {
        isResizing: false,
        async handleFiles(event) {
            this.isResizing = true;
            const files = this.$refs.imageInput.files;
            if (!files.length) { this.isResizing = false; return; }

            const maxWidth = 1920;
            const maxHeight = 1080;
            const resizedFiles = [];

            for (let i = 0; i < files.length; i++) {
                const resizedBlob = await this.resizeImage(files[i], maxWidth, maxHeight);
                resizedFiles.push(new File([resizedBlob], files[i].name, { type: 'image/jpeg' }));
            }

            const livewire = Livewire.find(this.$el.closest('[wire\\:id]').getAttribute('wire:id'));
            livewire.uploadMultiple('newUploads', resizedFiles, () => {
                this.isResizing = false;
                this.$refs.imageInput.value = '';
            }, () => { this.isResizing = false; }, () => {});
        },
        resizeImage(file, maxWidth, maxHeight) {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    let { width, height } = img;
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width; canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(resolve, 'image/jpeg', 0.85);
                };
                img.src = URL.createObjectURL(file);
            });
        }
    };
}
</script>
