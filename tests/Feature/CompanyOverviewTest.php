<?php

namespace Tests\Feature;

use App\Filament\Superadmin\Pages\CompanyOverview;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CompanyFinancials;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CompanyOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_totals_do_not_multiply_related_rows_and_include_inactive_companies(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));
        $admin = User::withoutGlobalScopes()->create(['role' => 'super_admin', 'status' => 'active', 'name' => 'Owner', 'email' => 'overview@example.test', 'password' => 'password']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        [$tenant, $branch, $worker] = $this->company('Alpha');
        [$other, $otherBranch] = $this->company('Beta');
        [$empty] = $this->company('Empty');
        $empty->update(['status' => 'suspended']);
        $first = $this->job($tenant, $branch, $admin, 40);
        $this->job($tenant, $branch, $admin, 60);
        $this->job($tenant, $branch, $admin, 100, date: now()->subMonth());
        $this->job($tenant, $branch, $admin, 90, status: 'open');
        $this->job($tenant, $branch, $admin, 80, status: 'cancelled');
        $this->job($tenant, $branch, $admin, 50, payment: 'pending');
        $this->job($other, $otherBranch, $admin, 200);
        foreach ([15, 5] as $amount) {
            DB::table('expenses')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'category' => 'Supplies', 'amount' => $amount, 'spent_at' => now()]);
        }
        DB::table('expenses')->insert(['tenant_id' => $other->id, 'branch_id' => $otherBranch, 'category' => 'Fuel', 'amount' => 45, 'spent_at' => now()]);
        DB::table('wash_sales')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'reference' => 'OLD-SALE', 'status' => 'completed', 'payment_method' => 'cash', 'total_amount' => 25, 'sold_at' => now()]);
        foreach ([4, 6] as $amount) {
            DB::table('payouts')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'worker_id' => $worker, 'amount' => $amount, 'payout_mode' => 'instant', 'status' => 'paid', 'paid_at' => now()]);
        }
        $wallet = DB::table('wallets')->insertGetId(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'worker_id' => $worker]);
        foreach ([['credit', 'paid', 30], ['payout', 'paid', 10], ['payout', 'pending', 3], ['credit', 'failed', 99]] as [$type, $status, $amount]) {
            DB::table('wallet_transactions')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'worker_id' => $worker, 'wallet_id' => $wallet, 'type' => $type, 'status' => $status, 'amount' => $amount]);
        }
        $record = app(CompanyFinancials::class)->query(today()->startOfDay(), today()->endOfDay())->findOrFail($tenant->id);
        foreach (['sales_total' => 125, 'job_count' => 2, 'expenses' => 20, 'company_share' => 70, 'worker_share' => 30, 'company_after_expenses' => 50, 'payouts' => 10, 'wallet_owed' => 20, 'unpaid_sales' => 50, 'legacy_sales' => 25] as $column => $expected) {
            $this->assertEquals($expected, $record->$column, $column);
        }
        $this->get('/superadmin/company-overview')->assertOk()->assertSee('Company financial overview')->assertSee('Wallet owed now');
        Livewire::test(CompanyOverview::class)->assertCanSeeTableRecords([$tenant, $other, $empty])
            ->set('period', 'today')->assertTableColumnStateSet('sales_total', 125, $record)
            ->searchTable('Alpha')->assertCanSeeTableRecords([$tenant])->assertCanNotSeeTableRecords([$other, $empty])
            ->assertSee('GHS 125.00');
        $page = Livewire::test(CompanyOverview::class)->set('period', 'all');
        $this->assertEquals(425, $page->instance()->totals()['sales']);
        $page->set('period', 'today');
        $this->assertEquals(325, $page->instance()->totals()['sales']);
        $page->set('until', '2026-10-01')->assertHasErrors('until')->assertCountTableRecords(0);
        $page->set('until', '2026-10-08')->assertHasNoErrors()->assertCountTableRecords(3);
    }

    public function test_company_accounts_cannot_access_platform_financials(): void
    {
        $this->actingAs(new User(['role' => 'ceo', 'status' => 'active']));
        $this->assertFalse(CompanyOverview::canAccess());
        Livewire::test(CompanyOverview::class)->assertForbidden();
        $this->expectException(HttpException::class);
        app(CompanyFinancials::class)->query(null, null);
    }

    private function company(string $name): array
    {
        $tenant = Tenant::withoutGlobalScopes()->create(['name' => $name, 'email' => strtolower($name).'@example.test', 'status' => 'active']);
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Main', 'status' => 'active']);
        $worker = DB::table('workers')->insertGetId(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'name' => $name.' worker', 'phone' => '02440000'.str_pad($tenant->id, 2, '0', STR_PAD_LEFT), 'pin' => bcrypt('1234'), 'type' => 'permanent', 'status' => 'active']);

        return [$tenant, $branch, $worker];
    }

    private function job(Tenant $tenant, int $branch, User $admin, int $amount, string $status = 'completed', string $payment = 'paid', ?Carbon $date = null): int
    {
        $id = DB::table('jobs')->insertGetId(['tenant_id' => $tenant->id, 'branch_id' => $branch, 'manager_id' => $admin->id, 'plate' => 'GR'.$amount, 'total_amount' => $amount, 'status' => $status, 'payment_status' => $payment, 'payment_method' => 'cash', 'created_at' => $date ?? now()]);
        for ($line = 0; $line < 2; $line++) {
            DB::table('job_services')->insert(['job_id' => $id, 'service_name' => 'Service '.$line, 'unit_price' => $amount / 2, 'total_amount' => $amount / 2, 'company_share' => $amount * .35, 'worker_share' => $amount * .15]);
        }

        return $id;
    }
}
