<?php

namespace Tests\Feature;

use App\Filament\Superadmin\Pages\LandingAppearance;
use App\Models\PlatformSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LandingAppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_publish_and_remove_a_wallpaper(): void
    {
        Storage::fake('local');
        $admin = User::withoutGlobalScopes()->create([
            'role' => 'super_admin', 'status' => 'active', 'name' => 'Platform owner',
            'email' => 'appearance@example.test', 'password' => 'password',
        ]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        $this->get('/superadmin/landing-appearance')->assertRedirect('/superadmin/settings');
        $this->get('/superadmin/settings')->assertOk();
        Livewire::test(LandingAppearance::class)
            ->fillForm(['wallpaper_path' => UploadedFile::fake()->image('wallpaper.jpg', 1200, 800)])
            ->call('save')->assertHasNoFormErrors();
        $settings = PlatformSetting::current()->fresh();
        $this->assertStringStartsWith('landing-wallpapers/', $settings->wallpaper_path);
        Storage::disk('local')->assertExists($settings->wallpaper_path);
        $this->get('/')->assertOk()->assertSee($settings->wallpaperUrl(), false);
        $this->get($settings->wallpaperUrl())->assertOk()->assertHeader('content-type', 'image/jpeg');
        Livewire::test(LandingAppearance::class)->fillForm(['wallpaper_path' => null])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull(PlatformSetting::current()->fresh()->wallpaper_path);
        $this->get('/')->assertOk()->assertDontSee('class="wallpaper"', false);
        $this->get('/landing-wallpaper')->assertNotFound();
    }

    public function test_company_accounts_cannot_change_landing_appearance(): void
    {
        $this->actingAs(new User(['role' => 'ceo', 'status' => 'active']));
        $this->assertFalse(LandingAppearance::canAccess());
        Livewire::test(LandingAppearance::class)->assertForbidden();
    }

    public function test_settings_publish_logos_icons_hero_and_legal_contact_details(): void
    {
        Storage::fake('local');
        $owner = User::withoutGlobalScopes()->create(['role' => 'super_admin', 'status' => 'active', 'name' => 'Owner', 'email' => 'settings@example.test', 'password' => 'password']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        Livewire::test(LandingAppearance::class)->fillForm([
            'brand_name' => 'Carbay+ Coast', 'light_logo_path' => UploadedFile::fake()->image('logo.png', 800, 300),
            'dark_logo_path' => UploadedFile::fake()->image('dark.png', 800, 300),
            'app_icon_path' => UploadedFile::fake()->image('icon.png', 512, 512),
            'hero_mode' => 'image', 'hero_image_path' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
            'hero_overlay' => 70, 'theme_color' => '#123456', 'install_popup_enabled' => false,
            'legal_email' => 'privacy@example.test', 'legal_address' => 'Example address, Accra', 'legal_reviewed' => true,
        ])->call('save')->assertHasNoFormErrors();
        $settings = PlatformSetting::current();
        foreach ([192, 512] as $size) {
            $dimensions = getimagesizefromstring(Storage::disk('local')->get($settings->{'icon_'.$size.'_path'}));
            $this->assertSame($size, $dimensions[0]);
            $this->assertSame($size, $dimensions[1]);
            $this->get($settings->assetUrl('icon-'.$size))->assertOk()->assertHeader('content-type', 'image/png');
        }
        $this->get('/pwa/landing.webmanifest')->assertOk()->assertJsonPath('name', 'Carbay+ Coast')->assertJsonPath('start_url', '/')
            ->assertJsonPath('theme_color', '#123456')->assertJsonPath('icons.0.src', $settings->assetUrl('icon-192'));
        $this->get('/pwa/manager.webmanifest')->assertJsonPath('start_url', '/manager/login')->assertJsonPath('id', '/manager');
        $this->get('/pwa/worker.webmanifest')->assertJsonPath('start_url', '/worker');
        $this->get('/')->assertOk()->assertSee($settings->assetUrl('logo'), false)->assertSee('data-popup-enabled="false"', false);
        $this->get('/superadmin')->assertOk()->assertSee('/platform-assets/hero', false);
        $this->get('/privacy')->assertOk()->assertSee('privacy@example.test')->assertSee('Example address, Accra')->assertDontSee('Draft for legal review');
        Livewire::test(LandingAppearance::class)->fillForm([
            'hero_mode' => 'gradient', 'hero_gradient_start' => '#123456', 'hero_gradient_end' => '#654321',
            'light_logo_path' => null, 'dark_logo_path' => null, 'app_icon_path' => null,
        ])->call('save')->assertHasNoFormErrors();
        $settings = PlatformSetting::current();
        $this->assertStringContainsString('linear-gradient(135deg,#123456,#654321)', $settings->heroStyle());
        $this->assertNull($settings->icon_192_path);
        $this->get('/')->assertSee('carbay-logo.png');
        $this->get('/platform-assets/icon-192')->assertNotFound();
        $this->get('/platform-assets/unknown')->assertNotFound();
    }

    public function test_settings_validate_colours_and_require_contact_details_for_legal_approval(): void
    {
        $this->actingAs(User::withoutGlobalScopes()->create(['role' => 'super_admin', 'status' => 'active', 'name' => 'Owner', 'email' => 'validate-settings@example.test', 'password' => 'password']));
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        Livewire::test(LandingAppearance::class)->fillForm(['hero_mode' => 'gradient', 'hero_gradient_start' => 'red;url(evil)'])
            ->call('save')->assertHasFormErrors(['hero_gradient_start']);
        Livewire::test(LandingAppearance::class)->fillForm(['legal_reviewed' => true, 'legal_email' => null, 'legal_address' => null])
            ->call('save')->assertHasErrors('data.legal_reviewed');
    }
}
