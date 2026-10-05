<?php

namespace App\Filament\App\Pages;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\WashSale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
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

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
