<?php

namespace App\Filament\Superadmin\Pages;

use App\Support\CompanyFinancials;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.superadmin.pages.company-overview';

    protected static ?string $navigationLabel = 'Company financial overview';

    protected static ?string $title = 'Company financial overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 1;

    public string $period = 'month';

    public string $from = '';

    public string $until = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->from = now()->startOfMonth()->toDateString();
        $this->until = today()->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->validate(['period' => 'required|in:today,week,month,year,all,custom']);
        if ($this->period !== 'custom') {
            $this->from = match ($this->period) {
                'today' => today()->toDateString(), 'week' => now()->startOfWeek()->toDateString(),
                'month' => now()->startOfMonth()->toDateString(), 'year' => now()->startOfYear()->toDateString(), default => '',
            };
            $this->until = $this->period === 'all' ? '' : today()->toDateString();
        }
        $this->resetPage();
    }

    public function updatedFrom(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    public function updatedUntil(): void
    {
        $this->updatedFrom();
    }

    public function financialQuery(): Builder
    {
        abort_unless(static::canAccess(), 403);
        $validator = Validator::make(['period' => $this->period, 'from' => $this->from, 'until' => $this->until], [
            'period' => 'required|in:today,week,month,year,all,custom',
            'from' => 'required_unless:period,all|nullable|date_format:Y-m-d',
            'until' => 'required_unless:period,all|nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());

            return app(CompanyFinancials::class)->query(null, null)->whereRaw('1 = 0');
        }
        $this->resetValidation(['from', 'until', 'period']);

        return app(CompanyFinancials::class)->query(
            $this->period === 'all' ? null : Carbon::parse($this->from)->startOfDay(),
            $this->period === 'all' ? null : Carbon::parse($this->until)->endOfDay(),
        );
    }

    public function table(Table $table): Table
    {
        return $table->query(fn (): Builder => $this->financialQuery())->columns([
            TextColumn::make('name')->label('Company')->description(fn ($record) => $record->email)->searchable()->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('job_count')->label('Paid jobs')->sortable(),
            TextColumn::make('sales_total')->label('Sales')->money('GHS')->sortable(),
            TextColumn::make('expenses')->label('Expenses')->money('GHS')->sortable(),
            TextColumn::make('company_share')->label('Job company share')->money('GHS')->sortable(),
            TextColumn::make('worker_share')->label('Job worker share')->money('GHS')->sortable(),
            TextColumn::make('company_after_expenses')->label('Company share − expenses')->money('GHS')->sortable(),
            TextColumn::make('payouts')->label('Workers paid in period')->money('GHS')->sortable(),
            TextColumn::make('wallet_owed')->label('Wallet owed now')->money('GHS')->sortable(),
            TextColumn::make('unpaid_sales')->label('Completed but unpaid')->money('GHS')->sortable(),
            TextColumn::make('legacy_sales')->label('Historical manual sales')->money('GHS')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'])])
            ->defaultSort('name')->recordUrl(null)->actions([])->paginated([10, 25, 50, 100]);
    }

    public function totals(): array
    {
        $query = $this->getFilteredTableQuery();

        return (array) DB::query()->fromSub($query->toBase(), 'companies')->selectRaw('COUNT(*) as companies, COALESCE(SUM(sales_total), 0) as sales, COALESCE(SUM(expenses), 0) as expenses, COALESCE(SUM(company_share), 0) as company, COALESCE(SUM(worker_share), 0) as workers, COALESCE(SUM(payouts), 0) as payouts')->first();
    }
}
