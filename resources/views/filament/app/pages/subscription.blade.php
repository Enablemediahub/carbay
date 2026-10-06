<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Current plan</p>
                    <h2 class="mt-1 text-2xl font-bold">{{ $package?->name ?? 'No plan assigned' }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ ucfirst($package?->billing_cycle ?? 'monthly') }} · {{ \App\Support\Currency::format($package?->price ?? 0) }}</p>
                </div>
                <a href="{{ $upgradeUrl }}" class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 py-2 font-semibold text-white">Request plan upgrade</a>
            </div>
            @if ($tenant->grace_period_ends_at)
                <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">Payment grace period ends {{ $tenant->grace_period_ends_at->toDayDateTimeString() }}. Please contact billing to avoid service interruption.</p>
            @endif
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                'Active branches' => $branches,
                'Active workers' => $workers.' / '.($package?->worker_limit ?? 0),
                'SMS credits remaining' => $smsBalance.' / '.($package?->sms_credits ?? 0),
            ] as $label => $value)
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                    <p class="text-sm text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-xl font-semibold">{{ $value }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="font-semibold">Subscription invoices</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead><tr class="border-b text-gray-500"><th class="py-2">Invoice</th><th>Period</th><th>Due</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @forelse ($subscriptionInvoices as $invoice)
                        <tr class="border-b last:border-0"><td class="py-3">{{ $invoice->invoice_number }}</td><td>{{ $invoice->period_starts_at?->toDateString() }} – {{ $invoice->period_ends_at?->toDateString() }}</td><td>{{ $invoice->due_at?->toDateString() ?? '—' }}</td><td>{{ ucfirst($invoice->status) }}</td><td class="text-right">{{ \App\Support\Currency::format($invoice->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">No subscription invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="font-semibold">Branch add-on invoices</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead><tr class="border-b text-gray-500"><th class="py-2">Invoice</th><th>Branch</th><th>Due</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    @forelse ($branchInvoices as $invoice)
                        <tr class="border-b last:border-0"><td class="py-3">{{ $invoice->invoice_number }}</td><td>{{ $invoice->branch?->name }}</td><td>{{ $invoice->due_at?->toDateString() ?? '—' }}</td><td>{{ ucfirst($invoice->status) }}</td><td class="text-right">{{ \App\Support\Currency::format($invoice->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">No branch add-on invoices.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
