<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditTrailObserver
{
    private const SENSITIVE_ATTRIBUTES = [
        'password',
        'pin',
        'remember_token',
        'paystack_secret_key',
        'gateway_response_json',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $newValues = $this->safeAttributes($model->getChanges());
        unset($newValues['updated_at']);

        if ($newValues === []) {
            return;
        }

        $oldValues = array_intersect_key($model->getOriginal(), $newValues);

        foreach (array_keys($newValues) as $attribute) {
            if (array_key_exists($attribute, $oldValues)) {
                $oldValues[$attribute] = $model->getRawOriginal($attribute);
            }
        }

        $this->record($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), null);
    }

    private function record(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $tenantId = $model instanceof Tenant
            ? $model->getKey()
            : $model->getAttribute('tenant_id');

        if (! $tenantId) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (! $tenant || ! $tenant->hasFeature('audit_trail')) {
            return;
        }

        $attributes = $model->getAttributes();
        $branchId = $attributes['branch_id'] ?? null;

        if ($model instanceof Branch) {
            $branchId = $model->getKey();
        } elseif ($model instanceof Tenant) {
            $branchId = $attributes['main_branch_id'] ?? null;
        }

        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => Auth::id(),
            'event' => class_basename($model).'.'.$action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues === null ? null : $this->safeAttributes($oldValues),
            'new_values' => $newValues === null ? null : $this->safeAttributes($newValues),
            'ip_address' => request()->ip(),
        ]);
    }

    private function safeAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::SENSITIVE_ATTRIBUTES));
    }
}
