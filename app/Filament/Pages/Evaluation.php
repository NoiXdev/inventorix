<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class Evaluation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.evaluation';

    public static function getNavigationGroup(): ?string
    {
        return __('menu.assets');
    }

    public static function getNavigationLabel(): string
    {
        return __('evaluation.label-plural');
    }

    public function getHeading(): string|Htmlable
    {
        return __('evaluation.label');
    }
}
