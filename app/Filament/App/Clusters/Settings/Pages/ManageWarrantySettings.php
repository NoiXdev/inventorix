<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Mail\WarrantyExpiryDigest;
use App\Services\WarrantyScanner;
use App\Settings\WarrantySettings;
use App\Support\ApplySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ManageWarrantySettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = WarrantySettings::class;

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return trans('settings.warranty.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.warranty.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('settings.warranty.section'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(trans('settings.warranty.field.enabled')),
                        TagsInput::make('recipients')
                            ->label(trans('settings.warranty.field.recipients'))
                            ->nestedRecursiveRules(['email'])
                            ->placeholder('ops@example.de'),
                        TagsInput::make('lead_days')
                            ->label(trans('settings.warranty.field.lead_days'))
                            ->helperText(trans('settings.warranty.field.lead_days_help'))
                            ->nestedRecursiveRules(['integer', 'min:0'])
                            ->placeholder('90'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestDigest')
                ->label(trans('settings.warranty.test.action'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->action(function (): void {
                    $this->save();
                    app(ApplySettings::class)();

                    $settings = app(WarrantySettings::class)->refresh();
                    $results = (new WarrantyScanner($settings->lead_days))->scan();

                    if ($results->isEmpty()) {
                        Notification::make()
                            ->title(trans('settings.warranty.test.empty_title'))
                            ->body(trans('settings.warranty.test.empty_body'))
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        Mail::to($settings->recipients)->send(new WarrantyExpiryDigest($results));

                        Notification::make()
                            ->title(trans('settings.warranty.test.success_title'))
                            ->body(trans('settings.warranty.test.success_body', ['count' => count($settings->recipients)]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(trans('settings.warranty.test.failure_title'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * Settings store lead_days as ints; the TagsInput yields strings. Normalise on save.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['lead_days'] = collect($data['lead_days'] ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $data;
    }
}
