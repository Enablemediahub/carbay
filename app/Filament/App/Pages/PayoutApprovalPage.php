<?php

namespace App\Filament\App\Pages;

use App\Models\Payout;
use App\Services\PaystackService;
use App\Services\WalletService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class PayoutApprovalPage extends Page
{
    protected static string $view = 'filament.app.pages.payout-approval';

    protected static ?string $slug = 'payout-approvals';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Manager';

    protected static ?string $navigationLabel = 'Payout approvals';

    protected static ?string $title = 'Payout approvals';

    protected static ?int $navigationSort = 5;

    public array $methods = [];

    public array $references = [];

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
    }

    public function pay(int $payoutId, WalletService $walletService, PaystackService $paystack): void
    {
        $payout = Payout::query()->findOrFail($payoutId);
        $method = $this->methods[$payoutId] ?? 'cash';
        $reference = trim((string) ($this->references[$payoutId] ?? ''));
        if (
            $payout->reference
            && $method !== 'paystack'
            && (
                data_get($payout->gateway_response_json, 'status') === 'initializing'
                || data_get($payout->gateway_response_json, 'data.transfer_code')
            )
        ) {
            throw ValidationException::withMessages(["references.$payoutId" => 'A Paystack transfer is awaiting confirmation. Do not issue a second payout.']);
        }
        if ($method === 'momo' && $reference === '') {
            throw ValidationException::withMessages(["references.$payoutId" => 'Enter the Mobile Money reference.']);
        }

        if ($method === 'paystack') {
            $paystack->initiateTransfer($payout, $reference, (int) auth()->id());
            Notification::make()->title('Paystack transfer initiated')->body('The payout will be settled after Paystack confirms it.')->success()->send();

            return;
        }

        $walletService->settlePayout($payout, $method, $reference, auth()->id());
        Notification::make()->title('Payout marked paid')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'payouts' => Payout::query()->with('worker')
                ->whereIn('status', ['queued', 'pending'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('available_at')
                ->get(),
        ];
    }
}
