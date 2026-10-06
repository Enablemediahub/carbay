<?php

namespace App\Filament\Auth;

use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
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

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email address or phone number')
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $identifier = trim((string) $data['email']);

        return [
            str_contains($identifier, '@') ? 'email' : 'phone' => $identifier,
            'password' => $data['password'],
        ];
    }
}
