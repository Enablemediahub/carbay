<x-filament-panels::page>
    <p>Manage every company's subscription, paid period and outstanding fees. Subscription receipts are payable to Abidale Group.</p>
    <div class="flex flex-wrap gap-4">
        <a class="text-primary-600 underline" href="/superadmin/subscription-invoices">All invoices &amp; receipt confirmation</a>
        <a class="text-primary-600 underline" href="/superadmin/subscription-payments">All payment attempts &amp; receipts</a>
        <a class="text-primary-600 underline" href="/superadmin/settings">Abidale Group payment settings</a>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
