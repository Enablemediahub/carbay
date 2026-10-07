<?php

namespace App\Filament\App\Pages;

use App\Models\Job;
use App\Services\WalletService;
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
        return [
            'jobs' => $this->jobsQuery()->with(['services', 'client', 'workers'])->latest()->limit(100)->get(),
        ];
    }

    private function jobsQuery(): Builder
    {
        return Job::query()->whereDate('created_at', today())
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where('plate', 'like', '%'.strtoupper(trim($this->search)).'%'));
    }
}
