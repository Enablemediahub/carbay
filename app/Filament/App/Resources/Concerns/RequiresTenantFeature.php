<?php

namespace App\Filament\App\Resources\Concerns;

trait RequiresTenantFeature
{
    abstract protected static function featureKey(): string;

    public static function canViewAny(): bool
    {
        return parent::canViewAny()
            && auth()->user()?->tenant?->hasFeature(static::featureKey());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }
}
