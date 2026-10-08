<?php

namespace App\Rules;

use App\Models\User;
use App\Models\Worker;
use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class UniqueStaffPhone implements ValidationRule
{
    public const MESSAGE = 'This phone number is already registered to a staff account. Use a different phone number.';

    public function __construct(private ?Model $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = PhoneNumber::normalize((string) $value);
        if ($phone === null) {
            return;
        }
        foreach ([User::class, Worker::class] as $modelClass) {
            $query = PhoneNumber::match($modelClass::withoutGlobalScopes(), $phone);
            if ($this->ignore instanceof $modelClass && $this->ignore->exists) {
                $query->whereKeyNot($this->ignore->getKey());
            }
            if ($query->exists()) {
                $fail(self::MESSAGE);

                return;
            }
        }
    }
}
