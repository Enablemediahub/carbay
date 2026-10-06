<?php

namespace App\Filament\App\Pages;

use App\Models\CashReconciliation;
use App\Models\Payment;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class CashReconciliationPage extends Page
{
    protected static string $view = 'filament.app.pages.cash-reconciliation';

    protected static ?string $slug = 'cash-reconciliation';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Manager';

    protected static ?string $navigationLabel = 'Cash reconciliation';

    protected static ?string $title = 'Cash reconciliation';

    protected static ?int $navigationSort = 3;

    public string $businessDate = '';

    public string $enteredAmount = '';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
        $this->businessDate = today()->toDateString();
        $this->loadSaved();
    }

    public function updatedBusinessDate(): void
    {
        $this->loadSaved();
    }

    public function save(): void
    {
        $this->validate([
            'businessDate' => ['required', 'date', 'before_or_equal:today'],
            'enteredAmount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        CashReconciliation::query()->updateOrCreate(
            ['manager_id' => auth()->id(), 'business_date' => $this->businessDate],
            [
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => auth()->user()->branch_id,
                'expected_amount' => $this->expectedAmount(),
                'entered_amount' => $this->enteredAmount,
                'notes' => $this->notes ?: null,
            ],
        );
        Notification::make()->title('Cash reconciliation saved')->success()->send();
    }

    public function expectedAmount(): float
    {
        return (float) Payment::query()
            ->whereHas('job', fn ($query) => $query->where('manager_id', auth()->id()))
            ->where('method', 'cash')
            ->where('status', 'paid')
            ->whereDate('created_at', $this->businessDate ?: today())
            ->sum('amount');
    }

    public function render(): View
    {
        return view(static::$view, ['expected' => $this->expectedAmount()]);
    }

    private function loadSaved(): void
    {
        if (! $this->businessDate) {
            return;
        }
        $saved = CashReconciliation::query()
            ->where('manager_id', auth()->id())
            ->whereDate('business_date', $this->businessDate)->first();
        $this->enteredAmount = $saved ? (string) $saved->entered_amount : '';
        $this->notes = $saved?->notes ?? '';
    }
}
