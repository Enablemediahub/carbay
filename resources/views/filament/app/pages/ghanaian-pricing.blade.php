<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">Save service menu</x-filament::button>
            <a href="{{ url('/app/service-prices') }}" class="text-sm font-semibold text-primary-600 dark:text-primary-400">Manage standard services</a>
        </div>
    </form>
</x-filament-panels::page>
