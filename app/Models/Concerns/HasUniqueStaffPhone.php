<?php

namespace App\Models\Concerns;

use App\Rules\UniqueStaffPhone;
use App\Support\PhoneNumber;

trait HasUniqueStaffPhone
{
    public static function bootHasUniqueStaffPhone(): void
    {
        static::saving(function ($model): void {
            if (! $model->exists || $model->isDirty('phone')) {
                $model->phone = PhoneNumber::normalize($model->phone);
                validator(['phone' => $model->phone], [
                    'phone' => ['nullable', 'regex:/^\+?[0-9]{7,15}$/', new UniqueStaffPhone($model)],
                ])->validate();
            }
        });
    }
}
