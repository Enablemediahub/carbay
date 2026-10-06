<?php

namespace App\Filament\App\Pages;

use App\Models\Tenant;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Arr;

class TenantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Company settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Company';

    protected static string $view = 'filament.app.pages.tenant-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'ceo'
            && auth()->user()?->tenant_id !== null;
    }

    public function mount(): void
    {
        $tenant = $this->tenant();

        $this->form->fill([
            'name' => $tenant->name,
            'logo' => $tenant->logo,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'momo_number' => $tenant->momo_number,
            'momo_name' => $tenant->momo_name,
            'paystack_public_key' => $tenant->paystack_public_key,
            'paystack_secret_key' => null,
            'cash_enabled' => $tenant->cash_enabled,
            'momo_enabled' => $tenant->momo_enabled,
            'paystack_enabled' => $tenant->paystack_enabled,
            'paystack_transfers_enabled' => $tenant->paystack_transfers_enabled,
            'loyalty_points_per_10' => $tenant->loyalty_points_per_10,
            'loyalty_reward_points' => $tenant->loyalty_reward_points,
            'loyalty_reward_value' => $tenant->loyalty_reward_value,
            'fraud_discount_threshold_pct' => $tenant->fraud_discount_threshold_pct,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Company information')->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    FileUpload::make('logo')->image()->directory('tenant-logos'),
                    TextInput::make('phone')->tel()->maxLength(30),
                    TextInput::make('email')->email()->required()->unique(table: 'tenants', ignorable: $this->tenant()),
                ])->columns(2),
                Section::make('Mobile Money')->schema([
                    TextInput::make('momo_number')->tel()->maxLength(30),
                    TextInput::make('momo_name')->maxLength(255),
                ])->columns(2),
                Section::make('Paystack')->schema([
                    TextInput::make('paystack_public_key')->maxLength(255),
                    TextInput::make('paystack_secret_key')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText('Leave blank to retain the current secret key.'),
                ])->columns(2),
                Section::make('Payments accepted')->schema([
                    Toggle::make('cash_enabled'),
                    Toggle::make('momo_enabled'),
                    Toggle::make('paystack_enabled'),
                ])->columns(3),
                Section::make('Worker Paystack transfers')
                    ->description('Allow approved worker payouts to be sent through Paystack. The manager must supply a Paystack transfer recipient code.')
                    ->schema([Toggle::make('paystack_transfers_enabled')])
                    ->visible(fn (): bool => $this->tenant()->hasFeature('paystack')),
                Section::make('Loyalty rewards')
                    ->description('Earn one point per GH₵10 spent by default. Set the required points and reward value for booking redemptions.')
                    ->schema([
                        TextInput::make('loyalty_points_per_10')->numeric()->minValue(0)->required(),
                        TextInput::make('loyalty_reward_points')->numeric()->minValue(1)->required(),
                        TextInput::make('loyalty_reward_value')->numeric()->prefix('GH₵')->minValue(0)->required(),
                    ])
                    ->columns(3)
                    ->visible(fn (): bool => $this->tenant()->hasFeature('loyalty')),
                Section::make('Fraud review')
                    ->schema([
                        TextInput::make('fraud_discount_threshold_pct')->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
                    ])
                    ->visible(fn (): bool => $this->tenant()->hasFeature('fraud_flags')),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $secret = Arr::pull($data, 'paystack_secret_key');

        if (blank($secret)) {
            unset($data['paystack_secret_key']);
        } else {
            $data['paystack_secret_key'] = $secret;
        }

        $this->tenant()->fill($data)->save();

        Notification::make()->success()->title('Company settings saved')->send();
    }

    private function tenant(): Tenant
    {
        return auth()->user()->tenant()->firstOrFail();
    }
}
