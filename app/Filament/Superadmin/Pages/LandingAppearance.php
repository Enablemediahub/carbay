<?php

namespace App\Filament\Superadmin\Pages;

use App\Models\PlatformSetting;
use App\Services\PlatformIconService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

class LandingAppearance extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Superadmin Settings';

    protected static ?string $slug = 'settings';

    protected static string $view = 'filament.superadmin.pages.landing-appearance';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = PlatformSetting::current();
        $this->form->fill(array_merge($settings->attributesToArray(), [
            'legal_email' => $settings->legal_email ?? config('legal.email'),
            'legal_address' => $settings->legal_address ?? config('legal.address'),
            'legal_reviewed' => $settings->legal_reviewed ?? config('legal.reviewed'),
            'billing_paystack_secret_key' => null,
        ]));
    }

    private function image(string $column, string $label, string $kind): FileUpload
    {
        return FileUpload::make($column)->label($label)->disk('local')->directory('platform-branding')->visibility('private')
            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)
            ->getUploadedFileUsing(fn (string $file): array => [
                'name' => basename($file), 'size' => null, 'type' => null,
                'url' => PlatformSetting::current()->assetUrl($kind),
            ])->helperText('PNG, JPG or WebP, up to 5 MB. Remove to use the default.');
    }

    private function color(string $column, string $label): ColorPicker
    {
        return ColorPicker::make($column)->label($label)->required()->rules(['regex:/^#[0-9a-fA-F]{6}$/']);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Web app branding')->description('Shared across the landing page, sign-in pages, dashboards and install popup.')->schema([
                TextInput::make('brand_name')->label('App name')->required()->maxLength(40),
                $this->color('theme_color', 'Browser and app theme colour'),
                $this->image('light_logo_path', 'Main logo', 'logo'),
                $this->image('dark_logo_path', 'Logo for dark mode', 'dark-logo')->helperText('Optional. If empty, the main uploaded logo is used.'),
                $this->image('app_icon_path', 'Home Screen icon', 'icon-512')->acceptedFileTypes(['image/png'])
                    ->rules(['dimensions:ratio=1,min_width=512,min_height=512,max_width=4096,max_height=4096'])
                    ->helperText('Square PNG, 512–4096 pixels, up to 5 MB. App icons are generated in 192px and 512px sizes.'),
            ])->columns(2),
            Section::make('Landing page')->schema([
                TextInput::make('landing_title')->label('Headline')->required()->maxLength(150),
                TextInput::make('landing_subtitle')->label('Introduction')->required()->maxLength(255),
                FileUpload::make('wallpaper_path')->label('Wallpaper')->disk('local')->directory('landing-wallpapers')->visibility('private')
                    ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)
                    ->getUploadedFileUsing(fn (string $file): array => ['name' => basename($file), 'size' => null, 'type' => null, 'url' => PlatformSetting::current()->wallpaperUrl()])
                    ->helperText('A soft overlay keeps the text and cards readable. Remove to restore the default background.'),
            ]),
            Section::make('Dashboard heroes')->description('One shared style for Admin / CEO, Manager, Worker and Superadmin dashboards.')->schema([
                Select::make('hero_mode')->label('Hero background')->options(['default' => 'Original design', 'gradient' => 'Colour gradient', 'image' => 'Uploaded image'])->required()->live(),
                $this->image('hero_image_path', 'Hero image', 'hero')->visible(fn ($get) => $get('hero_mode') === 'image')->required(fn ($get) => $get('hero_mode') === 'image'),
                TextInput::make('hero_overlay')->label('Image darkening')->numeric()->integer()->minValue(20)->maxValue(90)->suffix('%')->required()->visible(fn ($get) => $get('hero_mode') === 'image')
                    ->helperText('Higher values make white text easier to read.'),
                $this->color('hero_gradient_start', 'Gradient start')->visible(fn ($get) => $get('hero_mode') === 'gradient'),
                $this->color('hero_gradient_end', 'Gradient end')->visible(fn ($get) => $get('hero_mode') === 'gradient')->helperText('Choose darker colours so the white text stays readable.'),
            ])->columns(2),
            Section::make('App installation')->schema([
                Toggle::make('install_popup_enabled')->label('Show the automatic install popup on the landing page'),
                TextInput::make('install_popup_delay')->label('Popup delay')->numeric()->integer()->minValue(0)->maxValue(30)->suffix('seconds')->required(),
            ]),
            Section::make('Legal contact details')->description('Abidale Group through Enabl Technologies. These details appear on the Agreement and Data Privacy pages.')->schema([
                TextInput::make('legal_email')->label('Legal / privacy contact email')->email()->maxLength(255),
                TextInput::make('legal_address')->label('Business address')->maxLength(255),
                Toggle::make('legal_reviewed')->label('Documents have been legally reviewed and approved')->helperText('Enable only after professional review and confirmation of the stated operational arrangements.'),
            ]),
            Section::make('Abidale Group subscription payments')->description('Tenant software subscriptions are paid to Abidale Group. These settings are separate from each washing bay\'s customer payments.')->schema([
                TextInput::make('billing_momo_number')->label('MoMo receiving number')->tel()->maxLength(30),
                TextInput::make('billing_momo_name')->label('MoMo registered account name')->maxLength(255),
                Select::make('billing_momo_network')->label('MoMo network')->options(['MTN' => 'MTN', 'Telecel' => 'Telecel', 'ATMoney' => 'ATMoney']),
                Toggle::make('billing_paystack_enabled')->label('Enable Abidale Group Paystack checkout'),
                TextInput::make('billing_paystack_secret_key')->label('Abidale Group Paystack secret key')->password()->maxLength(255)
                    ->helperText('Leave blank to retain the saved key. The saved key is encrypted and is never sent back to this form.'),
                Placeholder::make('billing_webhook')->label('Paystack webhook URL')->content(fn () => route('subscription.payment.webhook')),
            ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        foreach (['light_logo_path', 'dark_logo_path', 'app_icon_path', 'hero_image_path', 'wallpaper_path'] as $column) {
            $path = $data[$column] ?? null;
            $prefix = $column === 'wallpaper_path' ? 'landing-wallpapers/' : 'platform-branding/';
            if ($path && (! str_starts_with($path, $prefix) || str_contains($path, '..'))) {
                throw ValidationException::withMessages(['data.'.$column => 'Choose an uploaded platform image.']);
            }
        }
        if (($data['legal_reviewed'] ?? false) && (empty($data['legal_email']) || empty($data['legal_address']))) {
            throw ValidationException::withMessages(['data.legal_reviewed' => 'Add the provider contact email and business address before marking documents approved.']);
        }
        $settings = PlatformSetting::current();
        if (empty($data['billing_paystack_secret_key'])) {
            unset($data['billing_paystack_secret_key']);
        } elseif (! preg_match('/^sk_(test|live)_/', $data['billing_paystack_secret_key'])) {
            throw ValidationException::withMessages(['data.billing_paystack_secret_key' => 'Enter a Paystack secret key beginning sk_test_ or sk_live_.']);
        }
        if (($data['billing_paystack_enabled'] ?? false) && empty($data['billing_paystack_secret_key']) && ! $settings->billing_paystack_secret_key) {
            throw ValidationException::withMessages(['data.billing_paystack_enabled' => 'Add Abidale Group\'s Paystack secret key before enabling checkout.']);
        }
        if (($data['app_icon_path'] ?? null) !== $settings->app_icon_path) {
            $data = array_merge($data, ! empty($data['app_icon_path'])
                ? app(PlatformIconService::class)->generate($data['app_icon_path'])
                : ['icon_192_path' => null, 'icon_512_path' => null]);
        }
        $settings->update($data);
        request()->attributes->remove('platform.appearance');
        Notification::make()->title('Superadmin settings saved')->success()->send();
    }
}
