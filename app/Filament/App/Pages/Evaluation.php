<?php

namespace App\Filament\App\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Evaluation extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected string $view = 'filament.app.pages.evaluation';

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
