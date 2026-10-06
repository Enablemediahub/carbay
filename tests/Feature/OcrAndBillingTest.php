<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Job;
use App\Models\Package;
use App\Models\PlateScan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlateOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OcrAndBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ocr_endpoint_is_tenant_scoped_and_logs_recognition_and_correction(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.platerecognizer.com/*' => Http::response([
                'results' => [['plate' => 'gr1234-24', 'score' => 0.64]],
            ]),
        ]);
        config(['services.plate_recognizer.api_token' => 'test-ocr-key']);
        [$tenant, $manager] = $this->createTenantAndManager('OCR company');
        $this->actingAs($manager);

        $response = $this->postJson(route('manager.plate-scan'), [
            'photo' => $this->fakeJpeg('plate.jpg'),
        ]);

        $response->assertOk()
            ->assertJsonPath('raw', 'GR1234-24')
            ->assertJsonPath('confidence', 0.64)
            ->assertJsonPath('confirmation_required', true);
        $scan = PlateScan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('GR1234-24', $scan->ocr_raw);
        Storage::disk('local')->assertExists($scan->image_path);

        $job = Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $manager->branch_id,
            'manager_id' => $manager->id,
            'plate' => 'GR1234-24',
            'total_amount' => 30,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'open',
        ]);
        app(PlateOcrService::class)->confirmCorrection($scan->id, $job, 'GR 1234-24');

        $this->assertDatabaseHas('plate_scans', [
            'id' => $scan->id,
            'tenant_id' => $tenant->id,
            'job_id' => $job->id,
            'corrected_value' => 'GR 1234-24',
        ]);
    }

    public function test_ocr_service_enforces_a_per_tenant_rate_limit(): void
    {
        Storage::fake('local');
        Http::fake([
            'api.platerecognizer.com/*' => Http::response(['results' => []]),
        ]);
        config(['services.plate_recognizer.api_token' => 'test-ocr-key']);
        [$tenant, $manager] = $this->createTenantAndManager('OCR rate company');
        $service = app(PlateOcrService::class);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $service->scan($this->fakeJpeg('plate-'.$attempt.'.jpg'), $tenant, $manager->branch_id, $manager->id);
        }

        try {
            $service->scan($this->fakeJpeg('plate-over-limit.jpg'), $tenant, $manager->branch_id, $manager->id);
            $this->fail('The eleventh scan should be rejected by the tenant limit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('photo', $exception->errors());
        }
    }

    public function test_billing_generates_idempotent_yearly_invoice_and_applies_grace_period(): void
    {
        [$tenant] = $this->createTenantAndManager('Billing company', 'yearly');

        $this->artisan('carbay:billing-cycle')->assertExitCode(0);
        $this->artisan('carbay:billing-cycle')->assertExitCode(0);
        $this->assertSame(1, SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $invoice = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(1200.0, (float) $invoice->amount);
        $this->assertTrue($invoice->period_ends_at->isSameDay($invoice->period_starts_at->copy()->addYear()->subDay()));

        $invoice->update(['due_at' => now()->subDays(8)]);
        $this->artisan('carbay:billing-cycle')->assertExitCode(0);
        $this->assertSame('suspended', $tenant->fresh()->status);

        $invoice->markPaid();
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertNull($tenant->fresh()->grace_period_ends_at);
    }

    private function createTenantAndManager(string $name, string $cycle = 'monthly'): array
    {
        $package = Package::query()->create([
            'name' => $name.' package '.uniqid(),
            'price' => $cycle === 'yearly' ? 1200 : 100,
            'billing_cycle' => $cycle,
            'branch_addon_price' => 10,
            'worker_limit' => 10,
            'sms_credits' => 100,
            'is_active' => true,
        ]);
        $feature = Feature::query()->firstOrCreate(
            ['key' => 'paystack'],
            ['name' => 'Paystack', 'is_active' => true],
        );
        $package->features()->sync([$feature->id => ['enabled' => true]]);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => $name,
            'email' => str_replace(' ', '-', strtolower($name)).'-'.uniqid().'@example.test',
            'package_id' => $package->id,
            'status' => 'active',
            'billing_anchor_at' => now()->subDay(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Main branch',
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenant->update(['main_branch_id' => $branchId]);
        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'manager',
            'name' => 'Test Manager',
            'email' => 'manager-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant->fresh(), User::withoutGlobalScopes()->findOrFail($managerId)];
    }

    private function fakeJpeg(string $name): UploadedFile
    {
        $bytes = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true);

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }
}
