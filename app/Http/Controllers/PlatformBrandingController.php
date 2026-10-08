<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;

class PlatformBrandingController extends Controller
{
    public function asset(string $kind)
    {
        if ($kind === 'icon-maskable') {
            $path = PlatformSetting::query()->first()?->icon_512_path;
            if ($path) {
                abort_unless(str_starts_with($path, 'platform-branding/') && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);
            }
            $bytes = $path ? Storage::disk('local')->get($path) : file_get_contents(public_path('carbay-favicon-512.png'));
            $image = imagecreatefromstring($bytes);
            $canvas = imagecreatetruecolor(512, 512);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $image, 76, 76, 0, 0, 360, 360, imagesx($image), imagesy($image));
            ob_start();
            imagepng($canvas);
            $png = ob_get_clean();
            imagedestroy($canvas);
            imagedestroy($image);

            return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
        }
        $columns = ['logo' => 'light_logo_path', 'dark-logo' => 'dark_logo_path', 'icon-192' => 'icon_192_path', 'icon-512' => 'icon_512_path', 'hero' => 'hero_image_path'];
        abort_unless(isset($columns[$kind]), 404);
        $path = PlatformSetting::query()->first()?->{$columns[$kind]};
        abort_unless($path && str_starts_with($path, 'platform-branding/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function manifest(string $workspace)
    {
        abort_unless(in_array($workspace, ['landing', 'admin', 'manager', 'worker'], true), 404);
        $settings = PlatformSetting::appearance();
        $name = $settings->brand_name ?: 'Carbay+';
        [$id, $start] = match ($workspace) {
            'admin' => ['/app', '/app'], 'manager' => ['/manager', '/manager/login'], 'worker' => ['/worker', '/worker'], default => ['/', '/'],
        };

        return response()->json([
            'id' => $id, 'name' => $name, 'short_name' => $name, 'start_url' => $start, 'scope' => '/', 'display' => 'standalone',
            'background_color' => '#edf6ff', 'theme_color' => $settings->theme_color ?: '#0096FF',
            'description' => 'Manage your washing bay, jobs and worker earnings.', 'prefer_related_applications' => false,
            'icons' => [
                ...array_map(fn ($size): array => ['src' => $settings->{'icon_'.$size.'_path'} ? $settings->assetUrl('icon-'.$size) : '/carbay-favicon-'.$size.'.png', 'sizes' => $size.'x'.$size, 'type' => 'image/png', 'purpose' => 'any'], [192, 512]),
                ['src' => route('platform.asset', ['kind' => 'icon-maskable', 'v' => substr(hash('sha256', $settings->icon_512_path ?? 'carbay-default-v1'), 0, 12)], false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }
}
