<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'wallpaper_path', 'light_logo_path', 'dark_logo_path', 'app_icon_path', 'icon_192_path', 'icon_512_path', 'hero_image_path',
        'brand_name', 'landing_title', 'landing_subtitle', 'hero_mode', 'hero_gradient_start', 'hero_gradient_end', 'hero_overlay', 'theme_color',
        'install_popup_enabled', 'install_popup_delay', 'legal_email', 'legal_address', 'legal_reviewed',
        'billing_momo_number', 'billing_momo_name', 'billing_momo_network', 'billing_paystack_enabled', 'billing_paystack_secret_key',
    ];

    protected function casts(): array
    {
        return ['install_popup_enabled' => 'boolean', 'legal_reviewed' => 'boolean', 'billing_paystack_enabled' => 'boolean', 'billing_paystack_secret_key' => 'encrypted'];
    }

    protected $hidden = ['billing_paystack_secret_key'];

    public static function appearance(): self
    {
        if (! request()->attributes->has('platform.appearance')) {
            request()->attributes->set('platform.appearance', static::query()->first() ?? new static);
        }

        return request()->attributes->get('platform.appearance');
    }

    public function assetUrl(string $kind): string
    {
        $columns = ['logo' => 'light_logo_path', 'dark-logo' => 'dark_logo_path', 'icon-192' => 'icon_192_path', 'icon-512' => 'icon_512_path', 'hero' => 'hero_image_path'];
        $path = $this->{$columns[$kind] ?? 'light_logo_path'};
        if ($kind === 'dark-logo' && ! $path && $this->light_logo_path) {
            return $this->assetUrl('logo');
        }
        if ($path) {
            return route('platform.asset', ['kind' => $kind, 'v' => substr(hash('sha256', $path), 0, 12)], false);
        }

        return asset(match ($kind) {
            'dark-logo' => 'carbay-logo-dark.png', 'icon-192' => 'carbay-favicon-192.png', 'icon-512' => 'carbay-favicon-512.png', default => 'carbay-logo.png',
        });
    }

    public function heroStyle(): string
    {
        if ($this->hero_mode === 'image' && $this->hero_image_path) {
            $opacity = max(20, min(90, (int) ($this->hero_overlay ?? 60))) / 100;

            return 'background-image:linear-gradient(rgba(5,39,70,'.$opacity.'),rgba(5,39,70,'.$opacity.')),url("'.$this->assetUrl('hero').'");background-size:cover;background-position:center;';
        }
        if ($this->hero_mode === 'gradient') {
            $start = preg_match('/^#[0-9a-f]{6}$/i', $this->hero_gradient_start ?? '') ? $this->hero_gradient_start : '#07518e';
            $end = preg_match('/^#[0-9a-f]{6}$/i', $this->hero_gradient_end ?? '') ? $this->hero_gradient_end : '#052746';

            return 'background:linear-gradient(135deg,'.$start.','.$end.');';
        }

        return '';
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1])->refresh();
    }

    public function wallpaperUrl(): ?string
    {
        return $this->wallpaper_path
            ? route('landing.wallpaper', ['v' => substr(hash('sha256', $this->wallpaper_path), 0, 12)], false)
            : null;
    }
}
