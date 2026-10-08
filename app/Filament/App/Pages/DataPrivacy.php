<?php

namespace App\Filament\App\Pages;

use App\Support\LegalDocuments;
use Filament\Pages\Page;

class DataPrivacy extends Page
{
    protected static string $view = 'filament.app.pages.legal-document';

    protected static ?string $navigationGroup = 'Legal';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $title = 'Data Privacy';

    public static function canAccess(): bool
    {
        return ServiceAgreement::canAccess();
    }

    protected function getViewData(): array
    {
        return ['document' => LegalDocuments::get('privacy')];
    }
}
