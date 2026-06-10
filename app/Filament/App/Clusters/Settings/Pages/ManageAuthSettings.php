<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\AuthSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageAuthSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = AuthSettings::class;

    protected static ?int $navigationSort = 20;

    /**
     * Secret fields are never sent to the browser; a blank submit keeps the stored value.
     */
    protected array $secretFields = [
        'microsoft_client_secret',
    ];

    public static function getNavigationLabel(): string
    {
        return trans('settings.auth.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.auth.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('settings.auth.microsoft.section'))
                    ->schema([
                        Toggle::make('microsoft_enabled')
                            ->label(trans('settings.auth.microsoft.field.enabled')),
                        TextInput::make('microsoft_client_id')
                            ->label(trans('settings.auth.microsoft.field.client_id'))
                            ->requiredIf('microsoft_enabled', true),
                        TextInput::make('microsoft_client_secret')
                            ->label(trans('settings.auth.microsoft.field.client_secret'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('microsoft_redirect')
                            ->label(trans('settings.auth.microsoft.field.redirect'))
                            ->url()
                            ->requiredIf('microsoft_enabled', true),
                        TextInput::make('microsoft_tenant')
                            ->label(trans('settings.auth.microsoft.field.tenant'))
                            ->requiredIf('microsoft_enabled', true),
                    ])
                    ->columns(2),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Never expose stored secrets to the browser.
        foreach ($this->secretFields as $field) {
            $data[$field] = null;
        }

        return $data;
    }
}
