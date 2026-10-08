<?php

namespace App\Filament\App\Pages;

use App\Support\LegalDocuments;
use Filament\Pages\Page;

class ServiceAgreement extends Page
{
    protected static string $view = 'filament.app.pages.legal-document';

    protected static ?string $navigationGroup = 'Legal';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $title = 'Service Agreement';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['ceo', 'manager'], true);
    }

    protected function getViewData(): array
    {
        return ['document' => LegalDocuments::get('agreement')];
    }
}
