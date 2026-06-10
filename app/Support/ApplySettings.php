<?php

namespace App\Support;

use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Support\Facades\Mail;

class ApplySettings
{
    public function __invoke(): void
    {
        $this->applyGeneral(app(GeneralSettings::class));
        $this->applyMail(app(MailSettings::class));
        $this->applyAuth(app(AuthSettings::class));
    }

    protected function applyAuth(AuthSettings $auth): void
    {
        // Reload from repository so long-lived Octane/Horizon workers never serve stale values.
        $auth->refresh();

        config([
            'services.microsoft-azure.enabled' => $auth->microsoft_enabled,
            'services.microsoft-azure.client_id' => $auth->microsoft_client_id,
            'services.microsoft-azure.client_secret' => $auth->microsoft_client_secret,
            'services.microsoft-azure.redirect' => $auth->microsoft_redirect,
            'services.microsoft-azure.tenant' => $auth->microsoft_tenant,
        ]);
    }

    protected function applyGeneral(GeneralSettings $general): void
    {
        // Reload from repository so long-lived Octane/Horizon workers never serve stale values.
        $general->refresh();

        config(['app.name' => $general->app_name]);
    }

    protected function applyMail(MailSettings $mail): void
    {
        $mail->refresh();

        config([
            'mail.default' => $mail->default_mailer,
            'mail.from.address' => $mail->from_address,
            'mail.from.name' => $mail->from_name,
        ]);

        match ($mail->default_mailer) {
            'smtp' => config([
                'mail.mailers.smtp.host' => $mail->smtp_host,
                'mail.mailers.smtp.port' => $mail->smtp_port,
                'mail.mailers.smtp.scheme' => $mail->smtp_scheme,
                'mail.mailers.smtp.username' => $mail->smtp_username,
                'mail.mailers.smtp.password' => $mail->smtp_password,
            ]),
            'ses' => config([
                'services.ses.key' => $mail->ses_key,
                'services.ses.secret' => $mail->ses_secret,
                'services.ses.region' => $mail->ses_region,
            ]),
            'postmark' => config([
                'services.postmark.token' => $mail->postmark_token,
                'mail.mailers.postmark.message_stream_id' => $mail->postmark_message_stream_id,
            ]),
            'resend' => config([
                'services.resend.key' => $mail->resend_key,
            ]),
            'postal' => config([
                'mail.mailers.postal' => ['transport' => 'postal'],
                'postal.domain' => $mail->postal_domain,
                'postal.key' => $mail->postal_key,
            ]),
            default => null,
        };

        // Drop any mailer instance resolved with the previous config so the next send rebuilds it.
        Mail::purge($mail->default_mailer);
    }
}
