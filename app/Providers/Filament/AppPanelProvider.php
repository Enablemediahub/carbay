<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\CashReconciliationPage;
use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Pages\NewWashJob;
use App\Filament\App\Pages\PayoutApprovalPage;
use App\Filament\App\Pages\PromoBlast;
use App\Filament\App\Pages\Reports;
use App\Filament\App\Pages\Subscription;
use App\Filament\App\Pages\TenantSettings;
use App\Filament\App\Pages\TodayJobs;
use App\Filament\App\Pages\WorkerCheckInPage;
use App\Filament\App\Widgets\BranchSales;
use App\Filament\App\Widgets\FraudFlagsWidget;
use App\Filament\App\Widgets\SalesOverview;
use App\Filament\App\Widgets\TopServices;
use App\Filament\App\Widgets\TopWorkers;
use App\Filament\Auth\Login;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\RedirectSuperAdminFromTenantPanel;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default(true)
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->brandName('Carbay+')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('4rem')
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): View => view('filament.pwa-head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.pwa-register'))
            ->defaultThemeMode(ThemeMode::Light)
            ->colors(['primary' => Color::hex('#0096FF')])
            ->viteTheme('resources/css/filament/app/theme.css')
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): View => view('filament.auth.login-story', [
                    'isSuperAdmin' => Filament::getCurrentPanel()->getId() === 'superadmin',
                ]),
                scopes: Login::class,
            )
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\\Filament\\App\\Resources')
            ->pages([Dashboard::class])
            ->pages([TenantSettings::class])
            ->pages([Reports::class])
            ->pages([Subscription::class])
            ->pages([
                NewWashJob::class,
                TodayJobs::class,
                CashReconciliationPage::class,
                WorkerCheckInPage::class,
                PayoutApprovalPage::class,
                PromoBlast::class,
            ])
            ->widgets([
                Widgets\AccountWidget::class,
                SalesOverview::class,
                BranchSales::class,
                TopWorkers::class,
                TopServices::class,
                FraudFlagsWidget::class,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Stop impersonating')
                    ->url(fn (): string => route('impersonation.stop'))
                    ->visible(fn (): bool => session()->has('impersonator_id')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                RedirectSuperAdminFromTenantPanel::class,
                EnsureTenantSubscriptionActive::class,
            ]);
    }
}
