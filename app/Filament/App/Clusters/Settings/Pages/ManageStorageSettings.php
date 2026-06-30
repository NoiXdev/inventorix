<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\StorageSettings;
use App\Support\ApplySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ManageStorageSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = StorageSettings::class;

    protected bool $suppressSavedNotification = false;

    protected static ?int $navigationSort = 50;

    /**
     * Secret fields are never sent to the browser; a blank submit keeps the stored value.
     */
    protected array $secretFields = ['secret'];

    public static function getNavigationLabel(): string
    {
        return trans('settings.storage.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.storage.title');
    }

    public function getSavedNotification(): ?Notification
    {
        if ($this->suppressSavedNotification) {
            return null;
        }

        return parent::getSavedNotification();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('settings.storage.section.s3'))
                    ->schema([
                        TextInput::make('key')->label(trans('settings.storage.field.key')),
                        TextInput::make('secret')
                            ->label(trans('settings.storage.field.secret'))
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('region')->label(trans('settings.storage.field.region'))->default('us-east-1'),
                        TextInput::make('bucket')->label(trans('settings.storage.field.bucket')),
                        TextInput::make('endpoint')
                            ->label(trans('settings.storage.field.endpoint'))
                            ->url()
                            ->helperText(trans('settings.storage.field.endpoint_help')),
                        Toggle::make('use_path_style_endpoint')
                            ->label(trans('settings.storage.field.use_path_style_endpoint'))
                            ->helperText(trans('settings.storage.field.use_path_style_endpoint_help')),
                        TextInput::make('url')
                            ->label(trans('settings.storage.field.url'))
                            ->url()
                            ->helperText(trans('settings.storage.field.url_help')),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label(trans('settings.storage.test.action'))
                ->icon(Heroicon::OutlinedSignal)
                ->action(function (): void {
                    // Persist current form state (suppress the "Saved" toast — we show our own
                    // result below), then apply it so the test uses what is on screen.
                    $this->suppressSavedNotification = true;
                    try {
                        $this->save();
                    } finally {
                        $this->suppressSavedNotification = false;
                    }
                    app(ApplySettings::class)();

                    try {
                        $disk = Storage::disk('s3');
                        $probe = 'inventorix-connection-test-'.Str::random(16).'.txt';

                        $written = $disk->put($probe, 'ok');
                        $contents = $written ? $disk->get($probe) : null;
                        $disk->delete($probe); // best-effort cleanup, runs regardless

                        if ($written === false || $contents !== 'ok') {
                            throw new \RuntimeException(trans('settings.storage.test.probe_failed'));
                        }

                        Notification::make()
                            ->title(trans('settings.storage.test.success_title'))
                            ->body(trans('settings.storage.test.success_body'))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(trans('settings.storage.test.failure_title'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
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
