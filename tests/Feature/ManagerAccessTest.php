<?php

namespace Tests\Feature;

use App\Filament\App\Resources\ManagerResource\Pages\CreateManager;
use App\Filament\App\Resources\ManagerResource\Pages\EditManager;
use App\Filament\App\Resources\WorkerResource\Pages\CreateWorker;
use App\Filament\App\Resources\WorkerResource\Pages\EditWorker;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Rules\UniqueStaffPhone;
use App\Services\ManagerAccountService;
use App\Services\TenantOnboarder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $email = 'owner@example.test'): array
    {
        $tenant = app(TenantOnboarder::class)->create([
            'name' => 'Test Wash', 'email' => 'company-'.$email, 'status' => 'active',
        ], [
            'name' => 'Company Owner', 'email' => $email, 'password' => 'owner-password',
        ]);
        $owner = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'ceo')->firstOrFail();

        return [$tenant, $owner];
    }

    private function managerData(Tenant $tenant): array
    {
        return ['name' => 'Branch Manager', 'phone' => '0244000001', 'pin' => '5678', 'branch_id' => $tenant->main_branch_id, 'status' => 'active'];
    }

    public function test_onboarded_owner_can_create_and_reset_a_manager_pin_through_the_company_panel(): void
    {
        [$tenant, $owner] = $this->company();
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->get('/app/managers')->assertOk();
        Livewire::test(CreateManager::class)->fillForm($this->managerData($tenant))
            ->call('create')->assertHasNoFormErrors();

        $manager = User::query()->where('role', 'manager')->firstOrFail();
        $this->assertSame($tenant->id, $manager->tenant_id);
        $this->assertTrue(Hash::check('5678', $manager->pin));
        $this->assertSame($manager->id, Branch::query()->findOrFail($tenant->main_branch_id)->manager_id);
        Livewire::test(EditManager::class, ['record' => $manager->id])
            ->assertFormSet(['pin' => null])->fillForm(['name' => 'Updated Manager'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('5678', $manager->fresh()->pin));
        Livewire::test(EditManager::class, ['record' => $manager->id])
            ->fillForm(['pin' => '9876'])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('9876', $manager->fresh()->pin));
    }

    public function test_manager_phone_pin_login_opens_branch_dashboard_and_worker_management(): void
    {
        [$tenant, $owner] = $this->company();
        $this->actingAs($owner);
        $manager = app(ManagerAccountService::class)->save($owner, $this->managerData($tenant));
        auth()->logout();

        $this->get('/manager/login')->assertOk()->assertSee('Manager PIN');
        $this->post('/manager/login', ['company_id' => $tenant->id, 'phone' => '0244 000 001', 'pin' => '0000'])
            ->assertSessionHasErrors('credentials');
        $this->assertGuest();
        $this->post('/manager/login', ['company_id' => $tenant->id, 'phone' => '0244 000 001', 'pin' => '5678'])
            ->assertRedirect('/app');
        $this->assertAuthenticatedAs($manager);
        $this->get('/app')->assertOk()->assertSee('Manager dashboard');
        $this->get('/app/workers')->assertOk();
        $this->get('/app/managers')->assertForbidden();
        $this->get('/app/managers/create')->assertForbidden();
    }

    public function test_owner_cannot_assign_a_manager_to_another_company_branch(): void
    {
        [$tenant, $owner] = $this->company();
        [$otherTenant] = $this->company('other@example.test');
        $this->actingAs($owner);
        $data = $this->managerData($tenant);
        $data['branch_id'] = $otherTenant->main_branch_id;

        $this->expectException(ValidationException::class);
        app(ManagerAccountService::class)->save($owner, $data);
    }

    public function test_login_rejects_wrong_company_and_inactive_accounts_but_shows_suspended_subscription_notice(): void
    {
        [$tenant, $owner] = $this->company();
        [$otherTenant] = $this->company('other@example.test');
        $this->actingAs($owner);
        $manager = app(ManagerAccountService::class)->save($owner, $this->managerData($tenant));
        auth()->logout();
        $credentials = ['company_id' => $tenant->id, 'phone' => $manager->phone, 'pin' => '5678'];
        $this->post('/manager/login', [...$credentials, 'company_id' => $otherTenant->id])->assertSessionHasErrors('credentials');
        $manager->update(['status' => 'inactive']);
        $this->post('/manager/login', $credentials)->assertSessionHasErrors('credentials');
        $manager->update(['status' => 'active']);
        $tenant->update(['status' => 'suspended']);
        $this->post('/manager/login', $credentials)->assertRedirect('/app');
        $this->assertAuthenticatedAs($manager);
        $this->get('/app')->assertRedirect('/subscription-notice');
        $this->get('/subscription-notice')->assertOk()->assertSee('You are not responsible for this payment.');
    }

    public function test_manager_pin_login_is_rate_limited(): void
    {
        [$tenant] = $this->company();
        $credentials = ['company_id' => $tenant->id, 'phone' => '0244000099', 'pin' => '0000'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/manager/login', $credentials)->assertSessionHasErrors('credentials');
        }
        $this->post('/manager/login', $credentials)->assertStatus(429);
    }

    public function test_duplicate_phone_is_displayed_on_the_manager_form_across_companies_and_formats(): void
    {
        [$tenant, $owner] = $this->company();
        [, $otherOwner] = $this->company('other@example.test');
        $otherOwner->update(['phone' => '0244000001']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(CreateManager::class)
            ->fillForm([...$this->managerData($tenant), 'name' => 'Ivan', 'phone' => '+233 244 000 001'])
            ->call('create')->assertHasFormErrors(['phone'])
            ->assertSee(UniqueStaffPhone::MESSAGE);
        $this->assertFalse(User::withoutGlobalScopes()->where('name', 'Ivan')->exists());
    }

    public function test_manager_cannot_reuse_a_workers_phone_number(): void
    {
        [$tenant, $owner] = $this->company();
        Worker::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $tenant->main_branch_id,
            'name' => 'Existing Worker', 'phone' => '0244000001', 'pin' => '1234', 'status' => 'inactive',
        ]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(CreateManager::class)->fillForm($this->managerData($tenant))
            ->call('create')->assertHasFormErrors(['phone']);
    }

    public function test_worker_cannot_reuse_a_users_phone_number_even_when_inactive(): void
    {
        [$tenant, $owner] = $this->company();
        $owner->update(['phone' => '0244000001']);

        $this->expectException(ValidationException::class);
        Worker::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $tenant->main_branch_id,
            'name' => 'Duplicate Worker', 'phone' => '+233 244 000 001', 'pin' => '1234', 'status' => 'inactive',
        ]);
    }

    public function test_staff_can_keep_their_own_phone_and_login_using_ghana_international_format(): void
    {
        [$tenant, $owner] = $this->company();
        $this->actingAs($owner);
        $manager = app(ManagerAccountService::class)->save($owner, $this->managerData($tenant));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(EditManager::class, ['record' => $manager->id])
            ->fillForm(['phone' => '+233 244 000 001'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('0244000001', $manager->fresh()->phone);
        auth()->logout();
        $this->post('/manager/login', [
            'company_id' => $tenant->id, 'phone' => '+233 244 000 001', 'pin' => '5678',
        ])->assertRedirect('/app');
        $this->assertAuthenticatedAs($manager);
    }

    public function test_manager_and_worker_photos_can_be_uploaded_and_are_private_to_the_company(): void
    {
        Storage::fake('local');
        [$tenant, $owner] = $this->company();
        [, $otherOwner] = $this->company('other@example.test');
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(CreateManager::class)->fillForm([
            ...$this->managerData($tenant), 'photo_path' => UploadedFile::fake()->image('manager.jpg'),
        ])->call('create')->assertHasNoFormErrors();
        $manager = User::withoutGlobalScopes()->where('role', 'manager')->firstOrFail();
        Storage::disk('local')->assertExists($manager->photo_path);
        $this->get($manager->photo_url)->assertOk();
        Livewire::test(EditManager::class, ['record' => $manager->id])
            ->fillForm(['name' => 'Photo Manager'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($manager->photo_path, $manager->fresh()->photo_path);

        $this->actingAs($manager);
        Livewire::test(CreateWorker::class)->fillForm([
            'name' => 'Photo Worker', 'branch_id' => $tenant->main_branch_id, 'phone' => '0244000098',
            'pin' => '1234', 'type' => 'casual', 'status' => 'inactive', 'payout_mode' => 'daily',
            'photo_path' => UploadedFile::fake()->image('worker.jpg'),
        ])->call('create')->assertHasNoFormErrors();
        $worker = Worker::withoutGlobalScopes()->where('name', 'Photo Worker')->firstOrFail();
        Storage::disk('local')->assertExists($worker->photo_path);
        $this->get($worker->photo_url)->assertOk();
        Livewire::test(EditWorker::class, ['record' => $worker->id])->fillForm([
            'pin' => '1234', 'photo_path' => UploadedFile::fake()->image('new-worker.jpg'),
        ])->call('save')->assertHasNoFormErrors();
        $this->assertNotSame($worker->photo_path, $worker->fresh()->photo_path);

        $this->actingAs($otherOwner);
        $this->get($manager->photo_url)->assertForbidden();
        $this->get($worker->photo_url)->assertForbidden();
    }

    public function test_invalid_photo_is_rejected_on_the_manager_form(): void
    {
        Storage::fake('local');
        [$tenant, $owner] = $this->company();
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(CreateManager::class)->fillForm([
            ...$this->managerData($tenant), 'photo_path' => UploadedFile::fake()->create('not-an-image.pdf'),
        ])->call('create')->assertHasFormErrors(['photo_path']);
    }

    public function test_manager_login_has_mobile_install_support_and_a_same_host_form(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost:8000',
            'HTTP_X_FORWARDED_HOST' => 'example.ngrok-free.dev', 'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/manager/login')->assertOk()
            ->assertSee('action="/manager/login"', false)
            ->assertSee('/pwa/manager.webmanifest')
            ->assertSee('apple-mobile-web-app-capable')
            ->assertSee('carbay-install-prompt');
    }

    public function test_expired_manager_login_token_returns_to_fresh_login_without_replaying_submission(): void
    {
        $this->app['env'] = 'local';
        $this->post('/manager/login', ['company_id' => 1, 'phone' => '0244000001', 'pin' => '5678'])
            ->assertStatus(303)->assertRedirect('/manager/login')->assertSessionHasErrors('session');
        $this->assertGuest();
    }
}
