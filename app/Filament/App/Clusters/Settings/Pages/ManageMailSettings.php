<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Mail\TestMail;
use App\Settings\MailSettings;
use App\Support\ApplySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ManageMailSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $cluster = Settings::class;

    protected static string $settings = MailSettings::class;

    protected bool $suppressSavedNotification = false;

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return trans('settings.mail.nav');
    }

    public function getTitle(): string
    {
        return trans('settings.mail.title');
    }

    public function getSavedNotification(): ?Notification
    {
        if ($this->suppressSavedNotification) {
            return null;
        }

        return parent::getSavedNotification();
    }

    /**
     * Secret fields are never sent to the browser; a blank submit keeps the stored value.
     */
    protected array $secretFields = [
        'smtp_password',
        'ses_secret',
        'postmark_token',
        'resend_key',
        'postal_key',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('settings.mail.section.from'))
                    ->schema([
                        TextInput::make('from_address')->label(trans('settings.mail.field.from_address'))->email()->required(),
                        TextInput::make('from_name')->label(trans('settings.mail.field.from_name'))->required(),
                    ])
                    ->columns(2),

                Select::make('default_mailer')
                    ->label(trans('settings.mail.field.driver'))
                    ->options([
                        'smtp' => trans('settings.mail.driver.smtp'),
                        'postal' => trans('settings.mail.driver.postal'),
                        'ses' => trans('settings.mail.driver.ses'),
                        'postmark' => trans('settings.mail.driver.postmark'),
                        'resend' => trans('settings.mail.driver.resend'),
                        'sendmail' => trans('settings.mail.driver.sendmail'),
                        'log' => trans('settings.mail.driver.log'),
                    ])
                    ->required()
                    ->live(),

                Section::make(trans('settings.mail.section.smtp'))
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'smtp')
                    ->schema([
                        TextInput::make('smtp_host')->label(trans('settings.mail.field.smtp_host'))->required(),
                        TextInput::make('smtp_port')->label(trans('settings.mail.field.smtp_port'))->numeric()->required(),
                        Select::make('smtp_scheme')
                            ->label(trans('settings.mail.field.smtp_scheme'))
                            ->options([
                                'smtp' => trans('settings.mail.scheme.starttls'),
                                'smtps' => trans('settings.mail.scheme.ssl'),
                            ])
                            ->placeholder(trans('settings.mail.field.smtp_scheme_placeholder'))
                            ->helperText(trans('settings.mail.field.smtp_scheme_help')),
                        TextInput::make('smtp_username')->label(trans('settings.mail.field.smtp_username')),
                        TextInput::make('smtp_password')->label(trans('settings.mail.field.smtp_password'))->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),

                Section::make(trans('settings.mail.section.ses'))
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'ses')
                    ->schema([
                        TextInput::make('ses_key')->label(trans('settings.mail.field.ses_key')),
                        TextInput::make('ses_secret')->label(trans('settings.mail.field.ses_secret'))->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('ses_region')->label(trans('settings.mail.field.ses_region'))->default('us-east-1'),
                    ])
                    ->columns(2),

                Section::make(trans('settings.mail.section.postmark'))
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postmark')
                    ->schema([
                        TextInput::make('postmark_token')->label(trans('settings.mail.field.postmark_token'))->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('postmark_message_stream_id')->label(trans('settings.mail.field.postmark_message_stream_id')),
                    ])
                    ->columns(2),

                Section::make(trans('settings.mail.section.resend'))
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'resend')
                    ->schema([
                        TextInput::make('resend_key')->label(trans('settings.mail.field.resend_key'))->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make(trans('settings.mail.section.postal'))
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postal')
                    ->schema([
                        TextInput::make('postal_domain')->label(trans('settings.mail.field.postal_domain'))->helperText(trans('settings.mail.field.postal_domain_help'))->url(),
                        TextInput::make('postal_key')->label(trans('settings.mail.field.postal_key'))->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(trans('settings.mail.test.action'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->schema([
                    TextInput::make('email')
                        ->label(trans('settings.mail.test.recipient'))
                        ->email()
                        ->required()
                        ->default(fn (): ?string => Auth::user()?->email),
                ])
                ->action(function (array $data): void {
                    // Persist current form state (suppress the "Saved" toast — we show our own result below),
                    // then apply it so the test uses what is on screen.
                    $this->suppressSavedNotification = true;
                    try {
                        $this->save();
                    } finally {
                        $this->suppressSavedNotification = false;
                    }
                    app(ApplySettings::class)();

                    try {
                        Mail::to($data['email'])->send(new TestMail);

                        Notification::make()
                            ->title(trans('settings.mail.test.success_title'))
                            ->body(trans('settings.mail.test.success_body', ['email' => $data['email']]))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title(trans('settings.mail.test.failure_title'))
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
