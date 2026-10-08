<?php

namespace App\Filament\App\Pages;

use App\Models\Job;
use App\Models\Worker;
use App\Services\WalletService;
use App\Support\WorkerSettlement;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class TodayJobs extends Page
{
    protected static string $view = 'filament.app.pages.today-jobs';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Manager';

    protected static ?string $navigationLabel = "Today's jobs";

    protected static ?string $title = "Today's jobs";

    protected static ?int $navigationSort = 2;

    public string $search = '';

    public string $workerId = '';

    public array $paymentMethods = [];

    public array $paymentReferences = [];

    public function payWorker(int $workerId, float $amount, WalletService $walletService): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
        $worker = Worker::query()->findOrFail($workerId);
        $walletService->payToday($worker, $amount, $this->paymentMethods[$workerId] ?? 'cash',
            trim($this->paymentReferences[$workerId] ?? ''), (int) auth()->id());
        Notification::make()->title('Worker payment confirmed')->success()->send();
    }

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
    }

    public function complete(int $jobId): void
    {
        $job = $this->jobsQuery()->whereKey($jobId)->firstOrFail();
        $job->update(['status' => 'completed']);
    }

    public function cancel(int $jobId, WalletService $walletService): void
    {
        $job = $this->jobsQuery()->whereKey($jobId)->firstOrFail();
        $walletService->cancelJob($job);
    }

    protected function getViewData(): array
    {
        $workers = Worker::query()->orderBy('name')->get();

        return [
            'workers' => $workers,
            'workerTotals' => $workers->when($this->workerId !== '', fn ($workers) => $workers->where('id', $this->workerId))
                ->map(fn (Worker $worker): array => ['worker' => $worker, ...app(WorkerSettlement::class)->today($worker)])
                ->filter(fn (array $summary): bool => $summary['earned'] > 0),
            'jobs' => $this->jobsQuery()->with(['services', 'client', 'workers'])->latest()->limit(100)->get(),
        ];
    }

    private function jobsQuery(): Builder
    {
        return Job::query()->whereDate('created_at', today())
            ->when($this->workerId !== '', fn (Builder $query) => $query->whereHas('workers', fn (Builder $workers) => $workers->where('workers.id', $this->workerId)))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where('plate', 'like', '%'.strtoupper(trim($this->search)).'%'));
    }
}
