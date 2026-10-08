<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Rules\UniqueStaffPhone;
use App\Support\PhoneNumber;
use App\Support\StaffPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagerAccountService
{
    public function save(User $owner, array $data, ?User $manager = null): User
    {
        abort_unless($owner->role === 'ceo' && $owner->status === 'active' && $owner->tenant_id, 403);
        if ($manager) {
            abort_unless($manager->role === 'manager' && $manager->tenant_id === $owner->tenant_id, 403);
        }

        $data['phone'] = PhoneNumber::normalize($data['phone'] ?? null);
        $validated = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+?[0-9]{7,15}$/', new UniqueStaffPhone($manager)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $owner->tenant_id)->where('status', 'active')],
            'pin' => [$manager ? 'nullable' : 'required', 'digits_between:4,12'],
            'status' => ['required', 'in:active,inactive'],
            'photo_path' => StaffPhoto::rules($manager),
        ])->validate();

        return DB::transaction(function () use ($owner, $validated, $manager): User {
            if (array_key_exists('photo_path', $validated)) {
                $validated['photo_path'] = StaffPhoto::store($validated['photo_path'], $manager);
            }
            if ($manager) {
                $manager = User::query()->whereKey($manager->id)->lockForUpdate()->firstOrFail();
                Branch::query()->where('manager_id', $manager->id)->update(['manager_id' => null]);
            } else {
                $manager = new User([
                    'tenant_id' => $owner->tenant_id,
                    'role' => 'manager',
                    'email' => Str::uuid().'@managers.carbayplus.test',
                    'password' => Str::random(64),
                ]);
            }

            if (empty($validated['pin'])) {
                unset($validated['pin']);
            }
            $manager->fill($validated)->save();
            if ($manager->status === 'active') {
                Branch::query()->whereKey($manager->branch_id)->update(['manager_id' => $manager->id]);
            }

            return $manager;
        });
    }
}
