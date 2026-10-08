<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class StaffPhoto
{
    public static function rules(?Model $record): array
    {
        return ['nullable', function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if ($value instanceof UploadedFile) {
                $validator = validator(['photo' => $value], ['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
                if ($validator->fails()) {
                    $fail($validator->errors()->first('photo'));
                }
            } elseif ($value !== $record?->photo_path) {
                $fail('Upload an image or take a photo using your camera.');
            }
        }];
    }

    public static function store(mixed $value, ?Model $record = null): ?string
    {
        validator(['photo_path' => $value], ['photo_path' => self::rules($record)])->validate();

        return $value instanceof UploadedFile ? $value->store('staff-photos', 'local') : $value;
    }
}
