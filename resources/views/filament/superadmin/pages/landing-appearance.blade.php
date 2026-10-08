<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <div class="flex flex-wrap items-center gap-4">
            <x-filament::button type="submit" wire:loading.attr="disabled">Save settings</x-filament::button>
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="text-sm font-semibold text-primary-600 dark:text-primary-400">Preview landing page ↗</a>
        </div>
    </form>
    <x-filament::section heading="Platform management">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;">
            @foreach([
                'Companies / tenants' => \App\Filament\Superadmin\Resources\TenantResource::getUrl(panel: 'superadmin'),
                'Company finances' => \App\Filament\Superadmin\Pages\CompanyOverview::getUrl(panel: 'superadmin'),
                'Packages' => \App\Filament\Superadmin\Resources\PackageResource::getUrl(panel: 'superadmin'),
                'Package features' => \App\Filament\Superadmin\Resources\FeatureResource::getUrl(panel: 'superadmin'),
                'Global service catalogue' => \App\Filament\Superadmin\Resources\GlobalServiceResource::getUrl(panel: 'superadmin'),
                'Platform users' => \App\Filament\Superadmin\Resources\UserResource::getUrl(panel: 'superadmin'),
                'Vehicle categories' => \App\Filament\Superadmin\Resources\VehicleCategoryResource::getUrl(panel: 'superadmin'),
                'Vehicle makes' => \App\Filament\Superadmin\Resources\VehicleMakeResource::getUrl(panel: 'superadmin'),
                'Vehicle models' => \App\Filament\Superadmin\Resources\VehicleModelResource::getUrl(panel: 'superadmin'),
                'Tenant subscriptions' => \App\Filament\Superadmin\Pages\Subscriptions::getUrl(panel: 'superadmin'),
                'Subscription billing' => \App\Filament\Superadmin\Resources\SubscriptionInvoiceResource::getUrl(panel: 'superadmin'),
                'Branch billing' => \App\Filament\Superadmin\Resources\BranchAddonInvoiceResource::getUrl(panel: 'superadmin'),
                'Service Agreement' => route('legal.agreement'),
                'Data Privacy' => route('legal.privacy'),
            ] as $label => $url)
                <a href="{{ $url }}" class="text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $label }} ↗</a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
