<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $photo = $getState();
        $preview = $photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
            ? ($photo->isPreviewable() ? $photo->temporaryUrl() : null)
            : ($photo ? $getRecord()?->photo_url : null);
    @endphp
    <div class="flex flex-wrap items-center gap-4">
        @if ($preview)
            <img src="{{ $preview }}" alt="Profile photo" class="h-20 w-20 rounded-full object-cover">
        @endif
        <div class="flex flex-wrap gap-3">
            <label class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">
                Upload image
                <input type="file" accept="image/jpeg,image/png,image/webp" wire:model="{{ $getStatePath() }}" class="sr-only">
            </label>
            <label class="inline-flex min-h-11 cursor-pointer items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium">
                Take photo
                <input type="file" accept="image/*" capture="user" wire:model="{{ $getStatePath() }}" class="sr-only">
            </label>
        </div>
        <p wire:loading wire:target="{{ $getStatePath() }}" class="text-sm text-gray-500">Uploading photo…</p>
    </div>
    <p class="mt-2 text-xs text-gray-500">JPG, PNG or WebP, up to 5 MB. Take photo opens the camera on supported phones and tablets.</p>
</x-dynamic-component>
