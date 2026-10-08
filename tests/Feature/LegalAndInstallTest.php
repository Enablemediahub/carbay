<?php

namespace Tests\Feature;

use App\Filament\App\Pages\DataPrivacy;
use App\Filament\App\Pages\ServiceAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalAndInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_and_legal_documents_are_accessible_before_sign_in(): void
    {
        $this->get('/')->assertOk()->assertSee('Install Carbay+')
            ->assertSee('/pwa/landing.webmanifest')->assertSee('landing-install.js')
            ->assertSee('Add to Home Screen')->assertSee(route('legal.agreement'), false)->assertSee(route('legal.privacy'), false);
        foreach (['agreement', 'privacy'] as $page) {
            $this->get('/'.$page)->assertOk()->assertSee('Abidale Group')->assertSee('Enabl Technologies')->assertSee('Draft for legal review');
        }
        config(['legal.email' => 'privacy@example.test', 'legal.address' => 'Example office, Accra', 'legal.reviewed' => true]);
        $this->get('/privacy')->assertOk()->assertSee('privacy@example.test')->assertSee('Example office, Accra')
            ->assertDontSee('awaiting confirmation')->assertDontSee('Draft for legal review');
        $manifest = json_decode(file_get_contents(public_path('landing-manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->get('/pwa/landing.webmanifest')->assertOk()->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('icons.0.src', '/carbay-favicon-192.png')
            ->assertJsonPath('icons.1.src', '/carbay-favicon-512.png')
            ->assertJsonPath('icons.2.purpose', 'maskable');
        $icon = $this->get('/platform-assets/icon-maskable')->assertOk()->assertHeader('content-type', 'image/png');
        $dimensions = getimagesizefromstring($icon->getContent());
        $this->assertSame(512, $dimensions[0]);
        $this->assertSame(512, $dimensions[1]);
    }

    public function test_legal_navigation_is_available_to_company_administrators(): void
    {
        $this->actingAs(new User(['role' => 'ceo']));
        $this->assertTrue(ServiceAgreement::canAccess());
        $this->assertTrue(DataPrivacy::canAccess());
        $this->actingAs(new User(['role' => 'worker']));
        $this->assertFalse(ServiceAgreement::canAccess());
    }
}
