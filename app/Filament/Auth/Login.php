<?php

namespace App\Filament\Auth;

use Filament\Facades\Filament;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return Filament::getCurrentPanel()->getId() === 'superadmin'
            ? 'Carbay+ platform sign in'
            : 'Carbay+ bay sign in';
    }

    public function getHeading(): string
    {
        return 'Welcome back';
    }

    public function getSubheading(): string
    {
        return 'Sign in to keep your car wash moving.';
    }
}
