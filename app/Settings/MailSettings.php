<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class MailSettings extends Settings
{
    public string $default_mailer;

    public string $from_address;

    public string $from_name;

    // SMTP
    public ?string $smtp_host;

    public ?int $smtp_port;

    public ?string $smtp_scheme;

    public ?string $smtp_username;

    public ?string $smtp_password;

    // SES
    public ?string $ses_key;

    public ?string $ses_secret;

    public ?string $ses_region;

    // Postmark
    public ?string $postmark_token;

    public ?string $postmark_message_stream_id;

    // Resend
    public ?string $resend_key;

    // Postal (synergitech/laravel-postal)
    public ?string $postal_domain;

    public ?string $postal_key;

    public static function group(): string
    {
        return 'mail';
    }

    public static function encrypted(): array
    {
        return [
            'smtp_password',
            'ses_secret',
            'postmark_token',
            'resend_key',
            'postal_key',
        ];
    }
}
