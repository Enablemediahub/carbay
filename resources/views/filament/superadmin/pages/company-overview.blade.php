<x-filament-panels::page>
    <style>
        .company-overview-filters { display: flex; flex-wrap: wrap; gap: 16px; }
        .company-overview-filters label { flex: 1; min-width: 170px; font-size: 14px; }
        .company-overview-filters input, .company-overview-filters select { display: block; width: 100%; border-radius: 12px; border: 1px solid #b8cfe0; background: #fff; color: #173653; margin-top: 6px; }
        .company-overview-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin: 24px 0; }
        .company-overview-stat { padding: 20px; border: 1px solid #c9dff3; border-radius: 20px; background: linear-gradient(135deg, #eff8ff, #fff); color: #153653; }
        .company-overview-stat span { font-size: 13px; }
        .company-overview-stat strong { display: block; font-size: 23px; margin-top: 8px; }
        .dark .company-overview-stat { background: linear-gradient(135deg, #122d47, #172334); color: #e7f3ff; border-color: #34516c; }
        .dark .company-overview-filters input, .dark .company-overview-filters select { background: #172334; color: #e7f3ff; border-color: #34516c; }
    </style>
    <div class="company-overview-filters">
        <label>Period<select wire:model.live="period"><option value="today">Today</option><option value="week">This week</option><option value="month">This month</option><option value="year">This year</option><option value="all">All time</option><option value="custom">Custom dates</option></select></label>
        @if($period !== 'all')
            <label>From<input type="date" wire:model.live="from"></label><label>Until<input type="date" wire:model.live="until"></label>
        @endif
    </div>
    @error('from')<p role="alert" class="text-danger-600">{{ $message }}</p>@enderror
    @error('until')<p role="alert" class="text-danger-600">{{ $message }}</p>@enderror
    @php($totals = $this->totals())
    <div class="company-overview-stats">
        <div class="company-overview-stat"><span>Companies in view</span><strong>{{ $totals['companies'] }}</strong></div>
        @foreach(['sales' => 'Sales', 'expenses' => 'Expenses', 'company' => 'Job company share', 'workers' => 'Job worker share', 'payouts' => 'Workers paid in period'] as $key => $label)
            <div class="company-overview-stat"><span>{{ $label }}</span><strong>GHS {{ number_format((float) $totals[$key], 2) }}</strong></div>
        @endforeach
    </div>
    <p class="text-sm">Sales include completed, paid jobs and historical manual sales. Shares use the percentages saved on each job; older manual sales have no recorded share split. Expenses use their recorded date, and payouts use the date paid. Wallet owed is the current outstanding ledger balance across all dates, including earnings from open paid jobs. Company share minus recorded expenses is before taxes and other unrecorded costs.</p>
    {{ $this->table }}
</x-filament-panels::page>
