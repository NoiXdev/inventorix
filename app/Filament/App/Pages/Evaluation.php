<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Pages\Evaluation\ReportRegistry;
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

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    public function getReportsProperty(): array
    {
        return array_map(
            fn (string $page): array => [
                'label' => $page::reportLabel(),
                'description' => $page::reportDescription(),
                'icon' => $page::reportIcon(),
                'url' => $page::getUrl(),
            ],
            ReportRegistry::all(),
        );
    }
}
