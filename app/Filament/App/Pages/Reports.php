<?php

namespace App\Filament\App\Pages;

use App\Exports\ArrayReportExport;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Job;
use App\Models\Payout;
use App\Models\WashSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Reports & exports';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Reports & data';

    protected static string $view = 'filament.app.pages.reports';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return in_array($user?->role, ['ceo', 'manager'], true)
            && $user?->tenant?->hasFeature('reports_export');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => today()->toDateString(),
            'branch_id' => Auth::user()?->role === 'manager'
                ? Auth::user()->branch_id
                : null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('start_date')
                    ->label('From')
                    ->required()
                    ->native(false),
                DatePicker::make('end_date')
                    ->label('To')
                    ->required()
                    ->native(false)
                    ->minDate(fn ($get) => $get('start_date')),
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('All branches')
                    ->visible(fn (): bool => Auth::user()?->role === 'ceo')
                    ->rules([
                        Rule::exists('branches', 'id')
                            ->where('tenant_id', Auth::user()?->tenant_id),
                    ]),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function export(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        validator($data, [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')
                    ->where('tenant_id', Auth::user()?->tenant_id)
                    ->when(
                        Auth::user()?->role === 'manager',
                        fn ($rule) => $rule->where('id', Auth::user()?->branch_id),
                    ),
            ],
        ])->validate();

        $tenant = Auth::user()->tenant;
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->endOfDay();
        $branchId = $data['branch_id'] ?? null;
        $filename = sprintf(
            'carbayplus-report-%s-to-%s.csv',
            $start->toDateString(),
            $end->toDateString(),
        );

        return response()->streamDownload(function () use ($tenant, $start, $end, $branchId): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                throw new \RuntimeException('Unable to open the report output stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Date',
                'Type',
                'Reference',
                'Branch',
                'Worker',
                'Payment method',
                'Description',
                'Quantity',
                'Income (GHS)',
                'Expense (GHS)',
            ], ',', '"', '');

            $sales = WashSale::query()
                ->with(['branch', 'worker', 'items'])
                ->where('status', 'completed')
                ->whereBetween('sold_at', [$start, $end])
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));

            $sales->chunkById(200, function ($batch) use ($stream): void {
                foreach ($batch as $sale) {
                    foreach ($sale->items as $item) {
                        $this->writeCsvRow($stream, [
                            $sale->sold_at->toDateTimeString(),
                            'Sale',
                            $sale->reference ?: 'SALE-'.$sale->id,
                            $sale->branch?->name,
                            $sale->worker?->name,
                            $sale->payment_method,
                            $item->service_name,
                            $item->quantity,
                            number_format((float) $item->total_amount, 2, '.', ''),
                            '0.00',
                        ]);
                    }
                }
            });

            $jobs = Job::query()
                ->with(['branch', 'workers', 'services'])
                ->where('status', 'completed')
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$start, $end])
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
            $jobs->chunkById(200, function ($batch) use ($stream): void {
                foreach ($batch as $job) {
                    foreach ($job->services as $item) {
                        $this->writeCsvRow($stream, [
                            $job->created_at->toDateTimeString(),
                            'Wash job',
                            'JOB-'.$job->id,
                            $job->branch?->name,
                            $job->workers->pluck('name')->join(', '),
                            $job->payment_method,
                            $job->plate.' · '.$item->service_name,
                            $item->quantity,
                            number_format((float) $item->total_amount, 2, '.', ''),
                            '0.00',
                        ]);
                    }
                }
            });

            if ($tenant->hasFeature('expense_tracking')) {
                $expenses = Expense::query()
                    ->with('branch')
                    ->whereBetween('spent_at', [$start, $end])
                    ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));

                $expenses->chunkById(200, function ($batch) use ($stream): void {
                    foreach ($batch as $expense) {
                        $this->writeCsvRow($stream, [
                            $expense->spent_at->toDateTimeString(),
                            'Expense',
                            'EXP-'.$expense->id,
                            $expense->branch?->name,
                            null,
                            null,
                            $expense->category.($expense->note ? ': '.$expense->note : ''),
                            null,
                            '0.00',
                            number_format((float) $expense->amount, 2, '.', ''),
                        ]);
                    }
                });
            }

            $payouts = Payout::query()
                ->with(['worker', 'branch'])
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
            $payouts->chunkById(200, function ($batch) use ($stream): void {
                foreach ($batch as $payout) {
                    $this->writeCsvRow($stream, [
                        $payout->paid_at?->toDateTimeString(),
                        'Worker payout',
                        'PAY-'.$payout->id,
                        $payout->branch?->name,
                        $payout->worker?->name,
                        $payout->method,
                        $payout->reference,
                        null,
                        '0.00',
                        number_format((float) $payout->amount, 2, '.', ''),
                    ]);
                }
            });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportExcel(): BinaryFileResponse
    {
        [$start, $end, $branchId] = $this->validatedFilters();

        return Excel::download(
            new ArrayReportExport($this->reportRows($start, $end, $branchId)),
            'carbayplus-report-'.$start->toDateString().'-to-'.$end->toDateString().'.xlsx',
        );
    }

    public function exportPdf(): Response
    {
        [$start, $end, $branchId] = $this->validatedFilters();
        $rows = $this->reportRows($start, $end, $branchId);

        return Pdf::loadView('filament.app.pages.reports-pdf', [
            'rows' => $rows,
            'start' => $start,
            'end' => $end,
            'summary' => [
                'income' => array_sum(array_column($rows, 8)),
                'expenses' => array_sum(array_column($rows, 9)),
            ],
        ])->setPaper('a4', 'landscape')->download(
            'carbayplus-report-'.$start->toDateString().'-to-'.$end->toDateString().'.pdf',
        );
    }

    public function reportSummary(): array
    {
        abort_unless(static::canAccess(), 403);
        [$start, $end, $branchId] = $this->validatedFilters();
        $jobs = Job::query()
            ->where('jobs.status', 'completed')->where('jobs.payment_status', 'paid')
            ->whereBetween('jobs.created_at', [$start, $end])
            ->when($branchId, fn (Builder $query) => $query->where('jobs.branch_id', $branchId));
        $legacySales = WashSale::query()->where('wash_sales.status', 'completed')
            ->whereBetween('wash_sales.sold_at', [$start, $end])
            ->when($branchId, fn (Builder $query) => $query->where('wash_sales.branch_id', $branchId));
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $weekExpression = $sqlite ? "strftime('%Y-%W', jobs.created_at)" : 'YEARWEEK(jobs.created_at, 1)';
        $monthExpression = $sqlite ? "strftime('%Y-%m', jobs.created_at)" : "DATE_FORMAT(jobs.created_at, '%Y-%m')";

        return [
            'by_day' => $this->mergeAggregates(
                (clone $jobs)->selectRaw('DATE(jobs.created_at) as label, SUM(jobs.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
                (clone $legacySales)->selectRaw('DATE(wash_sales.sold_at) as label, SUM(wash_sales.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
            )->sortBy('label')->values(),
            'by_week' => $this->mergeAggregates(
                (clone $jobs)->selectRaw($weekExpression.' as label, SUM(jobs.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
                (clone $legacySales)->selectRaw(str_replace('jobs.created_at', 'wash_sales.sold_at', $weekExpression).' as label, SUM(wash_sales.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
            )->sortBy('label')->values(),
            'by_month' => $this->mergeAggregates(
                (clone $jobs)->selectRaw($monthExpression.' as label, SUM(jobs.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
                (clone $legacySales)->selectRaw(str_replace('jobs.created_at', 'wash_sales.sold_at', $monthExpression).' as label, SUM(wash_sales.total_amount) as total, COUNT(*) as count')->groupBy('label')->get(),
            )->sortBy('label')->values(),
            'by_branch' => $this->mergeAggregates(
                (clone $jobs)->join('branches', 'jobs.branch_id', '=', 'branches.id')->selectRaw('branches.name as label, SUM(jobs.total_amount) as total, COUNT(*) as count')->groupBy('branches.name')->get(),
                (clone $legacySales)->join('branches', 'wash_sales.branch_id', '=', 'branches.id')->selectRaw('branches.name as label, SUM(wash_sales.total_amount) as total, COUNT(*) as count')->groupBy('branches.name')->get(),
            )->sortBy('label')->values(),
            'by_worker' => $this->mergeAggregates(
                DB::table('job_workers')->join('jobs', 'job_workers.job_id', '=', 'jobs.id')->join('workers', 'job_workers.worker_id', '=', 'workers.id')
                    ->where('jobs.tenant_id', Auth::user()->tenant_id)->where('jobs.status', 'completed')->where('jobs.payment_status', 'paid')
                    ->whereBetween('jobs.created_at', [$start, $end])->when($branchId, fn ($query) => $query->where('jobs.branch_id', $branchId))
                    ->selectRaw('workers.name as label, SUM(job_workers.share_amount) as total, COUNT(DISTINCT jobs.id) as count')->groupBy('workers.name')->get(),
                (clone $legacySales)->join('workers', 'wash_sales.worker_id', '=', 'workers.id')
                    ->whereNotNull('wash_sales.worker_id')
                    ->selectRaw('workers.name as label, SUM(wash_sales.total_amount) as total, COUNT(*) as count')
                    ->groupBy('workers.name')->get(),
            )->sortByDesc('total')->values(),
            'by_service' => $this->mergeAggregates(
                DB::table('job_services')->join('jobs', 'job_services.job_id', '=', 'jobs.id')
                    ->where('jobs.tenant_id', Auth::user()->tenant_id)->where('jobs.status', 'completed')->where('jobs.payment_status', 'paid')
                    ->whereBetween('jobs.created_at', [$start, $end])->when($branchId, fn ($query) => $query->where('jobs.branch_id', $branchId))
                    ->selectRaw('job_services.service_name as label, SUM(job_services.total_amount) as total, SUM(job_services.quantity) as count')->groupBy('job_services.service_name')->get(),
                DB::table('wash_sale_items')->join('wash_sales', 'wash_sale_items.wash_sale_id', '=', 'wash_sales.id')
                    ->where('wash_sales.tenant_id', Auth::user()->tenant_id)->where('wash_sales.status', 'completed')
                    ->whereBetween('wash_sales.sold_at', [$start, $end])->when($branchId, fn ($query) => $query->where('wash_sales.branch_id', $branchId))
                    ->selectRaw('wash_sale_items.service_name as label, SUM(wash_sale_items.total_amount) as total, SUM(wash_sale_items.quantity) as count')->groupBy('wash_sale_items.service_name')->get(),
            )->sortByDesc('total')->values(),
            'payouts' => Payout::query()->where('status', 'paid')->whereBetween('paid_at', [$start, $end])
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->with('worker')->get()->groupBy(fn (Payout $payout) => $payout->worker->name)
                ->map(fn ($items, $name) => ['label' => $name, 'total' => $items->sum('amount'), 'count' => $items->count()])->values(),
            'expenses' => Auth::user()->tenant->hasFeature('expense_tracking')
                ? Expense::query()->whereBetween('spent_at', [$start, $end])->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
                    ->selectRaw('category as label, SUM(amount) as total, COUNT(*) as count')->groupBy('category')->orderByDesc('total')->get()
                : collect(),
        ];
    }

    private function validatedFilters(): array
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        validator($data, [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', Auth::user()?->tenant_id)
                ->when(Auth::user()?->role === 'manager', fn ($rule) => $rule->where('id', Auth::user()?->branch_id))],
        ])->validate();

        return [
            Carbon::parse($data['start_date'])->startOfDay(),
            Carbon::parse($data['end_date'])->endOfDay(),
            $data['branch_id'] ?? null,
        ];
    }

    private function reportRows(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = [[
            'Date', 'Type', 'Reference', 'Branch', 'Worker', 'Payment method',
            'Description', 'Quantity', 'Income (GHS)', 'Expense (GHS)',
        ]];
        foreach ($this->exportRows($start, $end, $branchId) as $row) {
            $rows[] = $this->sanitizeSpreadsheetRow($row);
        }

        return $rows;
    }

    private function exportRows(Carbon $start, Carbon $end, ?int $branchId): array
    {
        $rows = [];
        $jobs = Job::query()->with(['branch', 'workers', 'services'])
            ->where('status', 'completed')->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->get();
        foreach ($jobs as $job) {
            foreach ($job->services as $item) {
                $rows[] = [
                    $job->created_at->toDateTimeString(), 'Wash job', 'JOB-'.$job->id,
                    $job->branch?->name ?? '', $job->workers->pluck('name')->join(', '),
                    $job->payment_method, $job->plate.' · '.$item->service_name, $item->quantity,
                    (float) $item->total_amount, 0,
                ];
            }
        }
        $legacySales = WashSale::query()->with(['branch', 'worker', 'items'])
            ->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->get();
        foreach ($legacySales as $sale) {
            foreach ($sale->items as $item) {
                $rows[] = [
                    $sale->sold_at->toDateTimeString(), 'Sale', $sale->reference ?: 'SALE-'.$sale->id,
                    $sale->branch?->name ?? '', $sale->worker?->name ?? '', $sale->payment_method ?? '',
                    $item->service_name, (int) $item->quantity, (float) $item->total_amount, 0,
                ];
            }
        }
        $payouts = Payout::query()->with(['worker', 'branch'])->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->get();
        foreach ($payouts as $payout) {
            $rows[] = [
                $payout->paid_at?->toDateTimeString() ?? '', 'Worker payout', 'PAY-'.$payout->id,
                $payout->branch?->name ?? '', $payout->worker?->name ?? '', $payout->method ?? '',
                $payout->reference ?? '', '', 0, (float) $payout->amount,
            ];
        }
        if (Auth::user()->tenant->hasFeature('expense_tracking')) {
            $expenses = Expense::query()->with('branch')->whereBetween('spent_at', [$start, $end])
                ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->get();
            foreach ($expenses as $expense) {
                $rows[] = [
                    $expense->spent_at->toDateTimeString(), 'Expense', 'EXP-'.$expense->id,
                    $expense->branch?->name ?? '', '', '',
                    $expense->category.($expense->note ? ': '.$expense->note : ''), '', 0, (float) $expense->amount,
                ];
            }
        }

        return $rows;
    }

    private function mergeAggregates($first, $second)
    {
        $groups = collect();
        foreach ($first->concat($second) as $row) {
            $key = (string) $row->label;
            $existing = $groups->get($key, ['label' => $row->label, 'total' => 0.0, 'count' => 0]);
            $existing['total'] += (float) $row->total;
            $existing['count'] += (int) $row->count;
            $groups->put($key, $existing);
        }

        return $groups->values();
    }

    private function sanitizeSpreadsheetRow(array $row): array
    {
        return array_map(static function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'".$value : $value;
        }, $row);
    }

    private function writeCsvRow($stream, array $values): void
    {
        $safeValues = array_map(static function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            return preg_match('/^[\x00-\x20]*[=+\-@]/', $value)
                ? "'".$value
                : $value;
        }, $values);

        fputcsv($stream, $safeValues, ',', '"', '');
    }
}
