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
    public function test_the_home_page_redirects_to_the_tenant_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app');
    }

    public function test_both_filament_panels_provide_login_pages(): void
    {
        $this->get('/superadmin/login')
            ->assertOk()
            ->assertSee('Keep every wash moving.')
            ->assertSee('Carbay+ platform sign in');
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('Run your bay, beautifully.')
            ->assertSee('Carbay+ bay sign in');
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
