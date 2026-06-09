<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = GeneralSettings::class;

    public static function getNavigationLabel(): string
    {
        return trans('settings.general.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.general.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label(trans('settings.general.field.app_name'))
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
