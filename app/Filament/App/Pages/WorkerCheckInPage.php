<?php

namespace App\Filament\App\Pages;

use App\Models\Worker;
use App\Models\WorkerCheckIn;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class WorkerCheckInPage extends Page
{
    protected static string $view = 'filament.app.pages.worker-check-in';

    protected static ?string $slug = 'worker-check-in';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Manager';

    protected static ?string $navigationLabel = 'Casual check-in';

    protected static ?string $title = 'Casual worker check-in';

    protected static ?int $navigationSort = 4;

    public ?int $workerId = null;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
    }

    public function checkIn(): void
    {
        $worker = $this->casualWorkers()->findOrFail($this->workerId);
        $record = WorkerCheckIn::query()->firstOrCreate(
            ['worker_id' => $worker->id, 'work_date' => today()->toDateString()],
            [
                'tenant_id' => $worker->tenant_id,
                'branch_id' => $worker->branch_id,
                'recorded_by' => auth()->id(),
                'checked_in_at' => now(),
            ],
        );
        if (! $record->wasRecentlyCreated && $record->checked_out_at) {
            throw ValidationException::withMessages(['workerId' => 'This worker has already checked out today.']);
        }
        Notification::make()->title($record->wasRecentlyCreated ? 'Worker checked in' : 'Worker is already checked in')->success()->send();
    }

    public function checkOut(int $workerId): void
    {
        $worker = $this->casualWorkers()->findOrFail($workerId);
        WorkerCheckIn::query()
            ->where('worker_id', $worker->id)
            ->whereDate('work_date', today())
            ->whereNull('checked_out_at')
            ->firstOrFail()
            ->update(['checked_out_at' => now()]);
        Notification::make()->title('Worker checked out')->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'workers' => $this->casualWorkers()->with(['branch'])->orderBy('name')->get(),
            'checkIns' => WorkerCheckIn::query()->with('worker')->whereDate('work_date', today())->latest('checked_in_at')->get(),
        ];
    }

    private function casualWorkers()
    {
        return Worker::query()->where('type', 'casual')->where('status', 'active');
    }
}
