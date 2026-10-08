<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Storage;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing', ['wallpaperUrl' => PlatformSetting::query()->first()?->wallpaperUrl()]);
    }

    public function wallpaper()
    {
        $path = PlatformSetting::query()->first()?->wallpaper_path;
        abort_unless($path && str_starts_with($path, 'landing-wallpapers/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=3600']);
    }
}
