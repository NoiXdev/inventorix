<?php

namespace App\Filament\App\Clusters\Settings\Pages;

use App\Filament\App\Clusters\Settings;
use App\Settings\MailSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageMailSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mail';

    protected static ?string $title = 'Mail settings';

    protected static ?string $cluster = Settings::class;

    protected static string $settings = MailSettings::class;

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
                Section::make('From')
                    ->schema([
                        TextInput::make('from_address')->label('From address')->email()->required(),
                        TextInput::make('from_name')->label('From name')->required(),
                    ])
                    ->columns(2),

                Select::make('default_mailer')
                    ->label('Mail driver')
                    ->options([
                        'smtp' => 'SMTP',
                        'postal' => 'Postal',
                        'ses' => 'Amazon SES',
                        'postmark' => 'Postmark',
                        'resend' => 'Resend',
                        'sendmail' => 'Sendmail',
                        'log' => 'Log (no delivery)',
                    ])
                    ->required()
                    ->live(),

                Section::make('SMTP')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'smtp')
                    ->schema([
                        TextInput::make('smtp_host')->label('Host')->required(),
                        TextInput::make('smtp_port')->label('Port')->numeric()->required(),
                        Select::make('smtp_scheme')->label('Encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL'])->placeholder('None'),
                        TextInput::make('smtp_username')->label('Username'),
                        TextInput::make('smtp_password')->label('Password')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),

                Section::make('Amazon SES')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'ses')
                    ->schema([
                        TextInput::make('ses_key')->label('Access key ID'),
                        TextInput::make('ses_secret')->label('Secret access key')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('ses_region')->label('Region')->default('us-east-1'),
                    ])
                    ->columns(2),

                Section::make('Postmark')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postmark')
                    ->schema([
                        TextInput::make('postmark_token')->label('Server token')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('postmark_message_stream_id')->label('Message stream ID'),
                    ])
                    ->columns(2),

                Section::make('Resend')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'resend')
                    ->schema([
                        TextInput::make('resend_key')->label('API key')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make('Postal')
                    ->visible(fn (Get $get): bool => $get('default_mailer') === 'postal')
                    ->schema([
                        TextInput::make('postal_domain')->label('Server URL')->helperText('The HTTPS URL of your Postal server.')->url(),
                        TextInput::make('postal_key')->label('API key')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state)),
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
