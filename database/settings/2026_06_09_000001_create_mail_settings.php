<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('mail.default_mailer', env('MAIL_MAILER', 'log'));
        $this->migrator->add('mail.from_address', env('MAIL_FROM_ADDRESS', 'hello@example.com'));
        $this->migrator->add('mail.from_name', env('MAIL_FROM_NAME', env('APP_NAME', 'Inventorix')));

        // SMTP
        $this->migrator->add('mail.smtp_host', env('MAIL_HOST', '127.0.0.1'));
        $this->migrator->add('mail.smtp_port', (int) env('MAIL_PORT', 2525));
        $this->migrator->add('mail.smtp_scheme', env('MAIL_SCHEME'));
        $this->migrator->add('mail.smtp_username', env('MAIL_USERNAME'));
        $this->migrator->addEncrypted('mail.smtp_password', env('MAIL_PASSWORD'));

        // SES
        $this->migrator->add('mail.ses_key', env('AWS_ACCESS_KEY_ID'));
        $this->migrator->addEncrypted('mail.ses_secret', env('AWS_SECRET_ACCESS_KEY'));
        $this->migrator->add('mail.ses_region', env('AWS_DEFAULT_REGION', 'us-east-1'));

        // Postmark
        $this->migrator->addEncrypted('mail.postmark_token', env('POSTMARK_API_KEY'));
        $this->migrator->add('mail.postmark_message_stream_id', env('POSTMARK_MESSAGE_STREAM_ID'));

        // Resend
        $this->migrator->addEncrypted('mail.resend_key', env('RESEND_API_KEY'));

        // Postal
        $this->migrator->add('mail.postal_domain', env('POSTAL_DOMAIN'));
        $this->migrator->addEncrypted('mail.postal_key', env('POSTAL_KEY'));
    }
};
