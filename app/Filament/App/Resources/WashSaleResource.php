<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WashSaleResource\Pages;
use App\Models\Job;
use App\Models\WashSale;
use App\Models\Worker;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WashSaleResource extends Resource
{
    protected static ?string $model = Job::class;

    protected static ?string $slug = 'wash-sales';

    protected static ?string $modelLabel = 'wash sale';

    protected static ?string $navigationLabel = 'Wash Sales';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Company';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', WashSale::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('jobs.status', 'completed')
            ->with(['workers', 'services', 'branch'])
            ->withSum('services as worker_share_total', 'worker_share')
            ->withSum('services as company_share_total', 'company_share');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Job')->formatStateUsing(fn ($state): string => 'JOB-'.$state)->sortable(),
                TextColumn::make('created_at')->label('Job date')->dateTime()->sortable(),
                TextColumn::make('plate')->label('Vehicle / job')->searchable()->default('Standalone cleaning'),
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('services.service_name')->label('Services')->listWithLineBreaks(),
                TextColumn::make('worker_allocations')->label('Worker earnings')
                    ->getStateUsing(fn (Job $record): array => $record->workers->map(
                        fn (Worker $worker): string => $worker->name.' — GHS '.number_format((float) $worker->pivot->share_amount, 2)
                    )->all())->listWithLineBreaks(),
                TextColumn::make('payment_method')->label('Payment')->badge(),
                TextColumn::make('payment_status')->label('Payment status')->badge()
                    ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'warning'),
                TextColumn::make('total_amount')->label('Sale total')->money('GHS')->sortable()
                    ->summarize(Sum::make()->label('Sales total')->money('GHS')),
                TextColumn::make('worker_share_total')->label('Worker share')->money('GHS')
                    ->summarize(Sum::make()->label('Worker earnings')->money('GHS')),
                TextColumn::make('company_share_total')->label('Company share')->money('GHS')
                    ->summarize(Sum::make()->label('Company retains')->money('GHS')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
                Tables\Filters\Filter::make('job_date')->form([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until'),
                ])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('jobs.created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('jobs.created_at', '<=', $date))),
                Tables\Filters\SelectFilter::make('payment_status')->options(['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed']),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(null)
            ->actions([])
            ->emptyStateHeading('No completed jobs yet')
            ->emptyStateDescription('Click Complete in Today\'s Jobs to show a job here.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWashSales::route('/')];
    }
}
