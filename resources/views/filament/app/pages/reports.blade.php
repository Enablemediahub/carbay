<x-filament-panels::page>
    <div class="mb-6 max-w-3xl text-sm leading-6 text-gray-600">
        Export completed wash sales and, when expense tracking is enabled, expenses for a selected date range.
        Reports include only data you are permitted to view and use Ghana cedis.
    </div>

    <form wire:submit="export" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
            Download CSV report
        </x-filament::button>
    </form>
</x-filament-panels::page>
