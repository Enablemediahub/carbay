<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_home_page_offers_worker_manager_and_company_portals(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Where would you like to go?')
            ->assertSee('Worker')
            ->assertSee('Manager')
            ->assertSee('Admin / CEO')
            ->assertSee('href="'.route('worker.login').'"', false)
            ->assertSee('href="'.url('/app/login').'"', false);

        $this->get('/app/login')->assertOk();
        $this->get('/worker/login')->assertOk();
    }

    public function test_both_filament_panels_provide_login_pages(): void
    {
        $this->get('/superadmin/login')
            ->assertOk()
            ->assertSee('Keep every wash moving.')
            ->assertSee('Carbay+ platform sign in')
            ->assertSee('rel="manifest"', false)
            ->assertSee('carbay-install-prompt');
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('Run your bay, beautifully.')
            ->assertSee('Carbay+ bay sign in')
            ->assertSee('rel="manifest"', false)
            ->assertSee('carbay-install-prompt');
    }

    public function test_filament_assets_use_https_behind_the_local_preview_proxy(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'preview.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/superadmin/login')
            ->assertOk()
            ->assertSee('https://localhost/build/assets/theme-', false);
    }

    public function test_super_admin_dashboard_has_mobile_platform_actions(): void
    {
        $superAdmin = User::withoutGlobalScopes()->create([
            'role' => 'super_admin',
            'status' => 'active',
            'name' => 'Test Super Admin',
            'email' => 'mobile-dashboard@example.test',
            'password' => 'password',
        ]);
        $this->actingAs($superAdmin);

        $this->get('/superadmin')
            ->assertOk()
            ->assertSee('Sales across all wash businesses today')
            ->assertSee('GH₵ 0.00')
            ->assertSee('Monthly recurring revenue')
            ->assertSee('Active users')
            ->assertSee('Manage tenants')
            ->assertSee('Billing');
    }

    public function test_global_reference_resources_are_available_to_super_admin(): void
    {
        $this->actingAs(new User([
            'role' => 'super_admin',
            'status' => 'active',
            'name' => 'Test Super Admin',
        ]));

        $this->get('/superadmin/vehicle-categories')->assertOk();
        $this->get('/superadmin/vehicle-makes')->assertOk();
        $this->get('/superadmin/vehicle-models')->assertOk();
        $this->get('/superadmin/global-services')->assertOk();
        $this->get('/superadmin/branch-addon-invoices')->assertOk();
    }

    public function test_super_admin_can_open_tenant_onboarding_form(): void
    {
        $this->actingAs(new User([
            'name' => 'Test Super Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]));

        $response = $this->get('/superadmin/tenants/create');

        $response->assertOk();
        $response->assertSee('CEO login email');
        $response->assertSee('CEO login password');
    }
}
