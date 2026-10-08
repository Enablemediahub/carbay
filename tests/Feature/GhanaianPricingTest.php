<?php

namespace Tests\Feature;

use App\Filament\App\Pages\GhanaianPricing;
use App\Filament\App\Pages\NewWashJob;
use App\Models\Feature;
use App\Models\Job;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\Wallet;
use App\Models\Worker;
use App\Services\TenantOnboarder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GhanaianPricingTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $email): Tenant
    {
        auth()->forgetGuards();
        $tenant = app(TenantOnboarder::class)->create(['name' => 'Wash '.$email, 'email' => 'company-'.$email, 'status' => 'active'], ['name' => 'Owner', 'email' => $email, 'password' => 'password']);
        $this->actingAs(User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('app'));

        return $tenant;
    }

    private function menu(string $mode = 'ghanaian', int $body = 20): array
    {
        return ['mode' => $mode, 'company_pct' => 70, 'worker_pct' => 30,
            'vehicles' => [['vehicle_type' => 'Saloon', 'body' => $body, 'under' => 20, 'engine' => 20], ['vehicle_type' => 'Motor', 'body' => 15]],
            'extras' => [['vehicle_type' => 'Saloon', 'service_name' => 'Vacuum', 'price' => 20]]];
    }

    public function test_owner_can_enter_matrix_prices_and_switch_menus_without_losing_prices(): void
    {
        $tenant = $this->company('ghana-owner@example.test');
        $standardCategory = VehicleCategory::create(['name' => 'Standard saloon']);
        $standardService = Service::create(['name' => 'Standard full wash', 'is_global' => true, 'is_active' => true]);
        $standardPrice = ServicePrice::create(['vehicle_category_id' => $standardCategory->id, 'service_id' => $standardService->id, 'price' => 90, 'company_pct' => 70, 'worker_pct' => 30, 'is_active' => true]);
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu())->call('save')->assertHasNoFormErrors();
        $this->assertSame('ghanaian', $tenant->fresh()->service_pricing_mode);
        $category = VehicleCategory::where('tenant_id', $tenant->id)->where('name', 'Saloon')->firstOrFail();
        $prices = ServicePrice::where('vehicle_category_id', $category->id)->where('pricing_system', 'ghanaian')->with('service')->get();
        $body = $prices->first(fn ($p) => $p->service->name === 'Body')->service_id;
        Livewire::test(NewWashJob::class)->set('vehicleCategoryId', (string) $category->id)->set('selectedServices', [$body])->assertSee('GH₵ 20.00');
        Livewire::test(NewWashJob::class)->set('vehicleCategoryId', (string) $category->id)->set('selectedServices', $prices->filter(fn ($p) => in_array($p->service->name, ['Body', 'Under', 'Engine']))->pluck('service_id')->all())->assertSee('GH₵ 60.00');
        $this->assertEquals(90, $standardPrice->fresh()->price);
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu('standard'))->call('save')->assertHasNoFormErrors();
        $this->assertSame('standard', $tenant->fresh()->service_pricing_mode);
        Livewire::test(NewWashJob::class)->set('vehicleCategoryId', (string) $standardCategory->id)->assertSee('Standard full wash')->assertDontSee('Ghanaian wash menu:');
        $this->assertSame(5, ServicePrice::where('pricing_system', 'ghanaian')->where('is_active', true)->count());
    }

    public function test_same_vehicle_and_cleaning_names_have_independent_company_prices(): void
    {
        $first = $this->company('first-menu@example.test');
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu())->call('save')->assertHasNoFormErrors();
        $second = $this->company('second-menu@example.test');
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu('ghanaian', 35))->call('save')->assertHasNoFormErrors();
        $this->assertSame(2, Service::where('name', 'Body')->count());
        $firstPrice = ServicePrice::withoutGlobalScopes()->where('tenant_id', $first->id)->whereHas('service', fn ($q) => $q->where('name', 'Body'))->whereHas('vehicleCategory', fn ($q) => $q->where('name', 'Saloon'))->firstOrFail();
        $this->assertEquals(20, $firstPrice->price);
        $this->assertSame('ghanaian', $second->fresh()->service_pricing_mode);
    }

    public function test_carpet_vacuum_and_detailing_services_are_saved_and_available_on_jobs(): void
    {
        $tenant = $this->company('extras-menu@example.test');
        $menu = $this->menu();
        $menu['extras'] = [
            ['vehicle_type' => 'Saloon', 'service_name' => 'Carpet cleaning', 'price' => 50],
            ['vehicle_type' => 'Saloon', 'service_name' => 'Vacuuming', 'price' => 25],
            ['vehicle_type' => 'Saloon', 'service_name' => 'Full detailing', 'price' => 150],
        ];
        Livewire::test(GhanaianPricing::class)
            ->assertSee('Vehicle wash price table')
            ->assertSee('<table', false)
            ->fillForm($menu)->call('save')->assertHasNoFormErrors();
        $category = VehicleCategory::where('tenant_id', $tenant->id)->where('name', 'Saloon')->firstOrFail();
        $services = Service::where('tenant_id', $tenant->id)->whereIn('name', ['Carpet cleaning', 'Vacuuming', 'Full detailing'])->pluck('id')->all();
        $this->assertCount(3, $services);
        Livewire::test(NewWashJob::class)->set('vehicleCategoryId', (string) $category->id)
            ->assertSee('Carpet cleaning')->assertSee('Vacuuming')->assertSee('Full detailing')
            ->set('selectedServices', $services)->assertSee('GH₵ 225.00');
        Livewire::test(GhanaianPricing::class)->assertSee('Vehicle wash price table');
    }

    public function test_manager_cannot_edit_menu_and_invalid_shares_are_rejected(): void
    {
        $this->company('validation-menu@example.test');
        Livewire::test(GhanaianPricing::class)->fillForm([...$this->menu(), 'worker_pct' => 20])->call('save')->assertHasFormErrors(['worker_pct']);
        Livewire::test(GhanaianPricing::class)->fillForm([...$this->menu(), 'vehicles' => [], 'extras' => []])->call('save')->assertHasFormErrors(['vehicles']);
        auth()->user()->forceFill(['role' => 'manager'])->save();
        Livewire::test(GhanaianPricing::class)->assertForbidden();
    }

    public function test_manager_sees_company_ghanaian_vehicle_types_and_can_select_multiple_services(): void
    {
        $tenant = $this->company('manager-menu@example.test');
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu())->call('save')->assertHasNoFormErrors();
        $category = VehicleCategory::where('tenant_id', $tenant->id)->where('name', 'Saloon')->firstOrFail();
        $services = Service::where('tenant_id', $tenant->id)->whereIn('name', ['Body', 'Under', 'Engine'])->pluck('id')->all();
        $manager = auth()->user();
        $manager->forceFill(['role' => 'manager'])->save();
        $this->actingAs($manager);
        Livewire::test(NewWashJob::class)->assertSet('jobType', 'vehicle')
            ->assertViewHas('categories', fn ($categories) => $categories->contains('id', $category->id))
            ->set('vehicleCategoryId', (string) $category->id)
            ->assertViewHas('services', fn ($items) => $items->whereIn('name', ['Body', 'Under', 'Engine'])->count() === 3)
            ->set('selectedServices', [$services[0]])->assertSee('GH₵ 20.00')
            ->set('selectedServices', $services)->assertSee('GH₵ 60.00')
            ->set('jobType', 'standalone')->assertSet('selectedServices', [])
            ->set('jobType', 'vehicle')->assertSet('selectedServices', [])
            ->assertViewHas('categories', fn ($categories) => $categories->contains('id', $category->id));
    }

    public function test_price_board_template_is_prefilled_and_keeps_existing_custom_prices(): void
    {
        $this->company('board-menu@example.test');
        $page = Livewire::test(GhanaianPricing::class);
        $rows = collect($page->get('data.vehicles'))->keyBy('vehicle_type');
        $this->assertEquals(15, $rows['Motor']['body']);
        $this->assertNull($rows['Motor']['engine']);
        $this->assertEquals(60, $rows['Saloon']['body'] + $rows['Saloon']['under'] + $rows['Saloon']['engine']);
        $this->assertEquals(120, $rows['Coaster / Civilian / Benz Bus up to 33 seats']['body'] * 3);
        $page->fillForm($this->menu('ghanaian', 35))->call('save')->assertHasNoFormErrors();
        $page = Livewire::test(GhanaianPricing::class)->call('fillFromPriceBoard');
        $rows = collect($page->get('data.vehicles'))->keyBy('vehicle_type');
        $this->assertEquals(35, $rows['Saloon']['body']);
        $this->assertEquals(25, $rows['4x4']['body']);
        $this->assertEquals(40, $rows['Coaster / Civilian / Benz Bus up to 33 seats']['engine']);
    }

    public function test_standalone_household_job_requires_no_vehicle_and_credits_worker(): void
    {
        $tenant = $this->company('household-menu@example.test');
        $package = Package::create(['name' => 'Household package', 'price' => 0, 'billing_cycle' => 'monthly', 'branch_addon_price' => 0, 'worker_limit' => 10, 'sms_credits' => 0, 'is_active' => true]);
        $feature = Feature::create(['key' => 'cash_payment', 'name' => 'Cash', 'is_active' => true]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant->update(['package_id' => $package->id, 'cash_enabled' => true]);
        $worker = Worker::create(['branch_id' => $tenant->main_branch_id, 'name' => 'Kojo', 'phone' => '0244000101', 'pin' => '1234', 'type' => 'permanent', 'payout_mode' => 'instant', 'status' => 'active']);
        $menu = $this->menu('standard');
        $menu['standalone'] = [
            ['service_name' => 'Household carpet cleaning', 'price' => 100],
            ['service_name' => 'Standalone vacuuming', 'price' => 40],
        ];
        Livewire::test(GhanaianPricing::class)->fillForm($menu)->call('save')->assertHasNoFormErrors();
        $services = ServicePrice::where('pricing_system', 'standalone')->pluck('service_id')->all();
        $this->assertCount(2, $services);
        Livewire::test(NewWashJob::class)->set('jobType', 'standalone')
            ->assertDontSee('id="job-plate"', false)->assertDontSee('id="vehicle-category"', false)
            ->assertSee('Household carpet cleaning')->assertSee('Standalone vacuuming')
            ->set('selectedServices', $services)->set('workerToAdd', (string) $worker->id)->call('submit')->assertHasNoErrors()
            ->assertSet('jobType', 'vehicle');
        $job = Job::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('standalone', $job->job_type);
        $this->assertNull($job->plate);
        $this->assertNull($job->vehicle_category_id);
        $this->assertEquals(140, $job->total_amount);
        $this->assertEquals(42, $job->workers()->firstOrFail()->pivot->share_amount);
        $this->assertEquals(42, Wallet::withoutGlobalScopes()->where('worker_id', $worker->id)->firstOrFail()->available_balance);
    }

    public function test_ghanaian_job_records_combined_total_and_company_worker_shares(): void
    {
        $tenant = $this->company('job-menu@example.test');
        $package = Package::create(['name' => 'Cash package', 'price' => 0, 'billing_cycle' => 'monthly', 'branch_addon_price' => 0, 'worker_limit' => 10, 'sms_credits' => 0, 'is_active' => true]);
        $feature = Feature::create(['key' => 'cash_payment', 'name' => 'Cash', 'is_active' => true]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant->update(['package_id' => $package->id, 'cash_enabled' => true]);
        $worker = Worker::create(['branch_id' => $tenant->main_branch_id, 'name' => 'Ama', 'phone' => '0244000100', 'pin' => '1234', 'type' => 'permanent', 'payout_mode' => 'instant', 'status' => 'active']);
        Livewire::test(GhanaianPricing::class)->fillForm($this->menu())->call('save')->assertHasNoFormErrors();
        $category = VehicleCategory::where('tenant_id', $tenant->id)->where('name', 'Saloon')->firstOrFail();
        $services = Service::where('tenant_id', $tenant->id)->whereIn('name', ['Body', 'Under', 'Engine'])->pluck('id')->all();
        Livewire::test(NewWashJob::class)->set('plate', 'GR 1234-24')->set('vehicleCategoryId', (string) $category->id)
            ->set('selectedServices', $services)->set('workerToAdd', (string) $worker->id)->call('submit')->assertHasNoErrors();
        $job = Job::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEquals(60, $job->total_amount);
        $this->assertEquals(42, $job->services()->sum('company_share'));
        $this->assertEquals(18, $job->services()->sum('worker_share'));
        $this->assertEquals(18, $job->workers()->firstOrFail()->pivot->share_amount);
    }
}
