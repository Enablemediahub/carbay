<x-filament-panels::page>
    <div class="mb-6 max-w-3xl text-sm leading-6 text-gray-600">
        Compare sales by day, week, month, branch, worker and service, plus payouts and expenses.
        Reports include only data you are permitted to view and use Ghana cedis.
    </div>

    <div class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
        <x-filament::button type="button" wire:click="export" icon="heroicon-o-arrow-down-tray">
            Download CSV report
        </x-filament::button>
        <x-filament::button type="button" wire:click="exportExcel" icon="heroicon-o-table-cells">
            Download Excel report
        </x-filament::button>
        <x-filament::button type="button" wire:click="exportPdf" icon="heroicon-o-document-text" color="gray">
            Download PDF report
        </x-filament::button>
        </div>
    </div>

    @php($summary = $this->reportSummary())
    @foreach ([
        'by_day' => 'Sales by day',
        'by_week' => 'Sales by week',
        'by_month' => 'Sales by month',
        'by_branch' => 'Sales by branch',
        'by_worker' => 'Worker shares',
        'by_service' => 'Sales by service',
        'payouts' => 'Worker payouts',
        'expenses' => 'Expenses by category',
    ] as $key => $title)
        <section class="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="text-base font-semibold">{{ $title }}</h2>
            @if (count($summary[$key]))
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b text-gray-500"><th class="py-2">Group</th><th class="py-2 text-right">Total (GHS)</th><th class="py-2 text-right">Count</th></tr></thead>
                        <tbody>
                        @foreach ($summary[$key] as $row)
                            <tr class="border-b last:border-0"><td class="py-2">{{ data_get($row, 'label') }}</td><td class="py-2 text-right">{{ number_format((float) data_get($row, 'total'), 2) }}</td><td class="py-2 text-right">{{ data_get($row, 'count') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-2 text-sm text-gray-500">No records for this period.</p>
            @endif
        </section>
    @endforeach
</x-filament-panels::page>
