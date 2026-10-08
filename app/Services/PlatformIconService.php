<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformIconService
{
    public function generate(string $path): array
    {
        $bytes = Storage::disk('local')->get($path);
        $dimensions = @getimagesizefromstring($bytes);
        if (! $dimensions || $dimensions[0] !== $dimensions[1] || $dimensions[0] < 512 || $dimensions[0] > 4096 || $dimensions[2] !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['data.app_icon_path' => 'Use a square PNG between 512 and 4096 pixels.']);
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image || imagesx($image) !== imagesy($image) || imagesx($image) < 512 || imagesx($image) > 4096) {
            if ($image) {
                imagedestroy($image);
            }
            throw ValidationException::withMessages(['data.app_icon_path' => 'Use a square PNG between 512 and 4096 pixels.']);
        }
        $icons = [];
        try {
            foreach ([192, 512] as $size) {
                $icon = imagecreatetruecolor($size, $size);
                imagealphablending($icon, false);
                imagesavealpha($icon, true);
                imagecopyresampled($icon, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));
                ob_start();
                imagepng($icon);
                $png = ob_get_clean();
                imagedestroy($icon);
                $output = 'platform-branding/icon-'.$size.'-'.Str::uuid().'.png';
                Storage::disk('local')->put($output, $png);
                $icons['icon_'.$size.'_path'] = $output;
            }
        } finally {
            imagedestroy($image);
        }

        return $icons;
    }
}
