<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\AuthSettingsRequest;
use App\Http\Requests\App\GeneralSettingsRequest;
use App\Http\Requests\App\MailSettingsRequest;
use App\Http\Requests\App\MailTestRequest;
use App\Http\Requests\App\StorageSettingsRequest;
use App\Http\Requests\App\WarrantySettingsRequest;
use App\Mail\TestMail;
use App\Mail\WarrantyExpiryDigest;
use App\Services\WarrantyScanner;
use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Settings\StorageSettings;
use App\Settings\WarrantySettings;
use App\Support\ApplySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    /** Encrypted/secret fields per group — never sent to the browser; only overwritten when non-blank. */
    private const SECRETS = [
        'mail' => ['smtp_password', 'ses_secret', 'postmark_token', 'resend_key', 'postal_key'],
        'storage' => ['secret'],
        'auth' => ['microsoft_client_secret'],
    ];

    public function index(GeneralSettings $general, MailSettings $mail, StorageSettings $storage, WarrantySettings $warranty, AuthSettings $auth): Response
    {
        return Inertia::render('settings/index', [
            't' => trans('settings'),
            'options' => [
                'mailDrivers' => $this->mapOptions([
                    'smtp', 'postal', 'ses', 'postmark', 'resend', 'sendmail', 'log',
                ], 'settings.mail.driver'),
                'mailSchemes' => [
                    ['value' => 'smtp', 'label' => trans('settings.mail.scheme.starttls')],
                    ['value' => 'smtps', 'label' => trans('settings.mail.scheme.ssl')],
                ],
            ],
            'settings' => [
                'general' => ['app_name' => $general->app_name],
                'mail' => $this->withoutSecrets([
                    'default_mailer' => $mail->default_mailer,
                    'from_address' => $mail->from_address,
                    'from_name' => $mail->from_name,
                    'smtp_host' => $mail->smtp_host,
                    'smtp_port' => $mail->smtp_port,
                    'smtp_scheme' => $mail->smtp_scheme,
                    'smtp_username' => $mail->smtp_username,
                    'ses_key' => $mail->ses_key,
                    'ses_region' => $mail->ses_region,
                    'postmark_message_stream_id' => $mail->postmark_message_stream_id,
                    'postal_domain' => $mail->postal_domain,
                ], 'mail'),
                'storage' => $this->withoutSecrets([
                    'key' => $storage->key,
                    'region' => $storage->region,
                    'bucket' => $storage->bucket,
                    'endpoint' => $storage->endpoint,
                    'use_path_style_endpoint' => $storage->use_path_style_endpoint,
                    'url' => $storage->url,
                ], 'storage'),
                'warranty' => [
                    'enabled' => $warranty->enabled,
                    'recipients' => $warranty->recipients,
                    'lead_days' => array_map('strval', $warranty->lead_days),
                ],
                'auth' => $this->withoutSecrets([
                    'multi_factor_enabled' => $auth->multi_factor_enabled,
                    'multi_factor_force' => $auth->multi_factor_force,
                    'multi_factor_recoverable' => $auth->multi_factor_recoverable,
                    'microsoft_enabled' => $auth->microsoft_enabled,
                    'microsoft_client_id' => $auth->microsoft_client_id,
                    'microsoft_redirect' => $auth->microsoft_redirect,
                    'microsoft_tenant' => $auth->microsoft_tenant,
                ], 'auth'),
            ],
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): RedirectResponse
    {
        $this->persistGeneral($request->validated());

        return back()->with('success', trans('settings.general.title'));
    }

    public function updateMail(MailSettingsRequest $request): RedirectResponse
    {
        $this->persistMail($request->validated());

        return back()->with('success', trans('settings.mail.title'));
    }

    public function updateStorage(StorageSettingsRequest $request): RedirectResponse
    {
        $this->persistStorage($request->validated());

        return back()->with('success', trans('settings.storage.title'));
    }

    public function updateAuth(AuthSettingsRequest $request): RedirectResponse
    {
        $this->persistAuth($request->validated());

        return back()->with('success', trans('settings.auth.title'));
    }

    public function updateWarranty(WarrantySettingsRequest $request): RedirectResponse
    {
        $this->persistWarranty($request->validated());

        return back()->with('success', trans('settings.warranty.title'));
    }

    public function testMail(MailTestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->persistMail($data);

        try {
            Mail::to($data['email'])->send(new TestMail);

            return back()->with('success', trans('settings.mail.test.success_body', ['email' => $data['email']]));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.mail.test.failure_title').': '.$e->getMessage());
        }
    }

    public function testStorage(StorageSettingsRequest $request): RedirectResponse
    {
        $this->persistStorage($request->validated());

        try {
            $disk = Storage::disk('s3');
            $probe = 'inventorix-connection-test-'.Str::random(16).'.txt';
            $written = $disk->put($probe, 'ok');
            $contents = $written ? $disk->get($probe) : null;
            $disk->delete($probe);

            if ($written === false || $contents !== 'ok') {
                throw new \RuntimeException(trans('settings.storage.test.probe_failed'));
            }

            return back()->with('success', trans('settings.storage.test.success_body'));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.storage.test.failure_title').': '.$e->getMessage());
        }
    }

    public function testWarranty(WarrantySettingsRequest $request): RedirectResponse
    {
        $this->persistWarranty($request->validated());
        $settings = app(WarrantySettings::class)->refresh();
        $results = (new WarrantyScanner($settings->lead_days))->scan();

        if ($results->isEmpty()) {
            return back()->with('warning', trans('settings.warranty.test.empty_body'));
        }

        try {
            Mail::to($settings->recipients)->send(new WarrantyExpiryDigest($results));

            return back()->with('success', trans('settings.warranty.test.success_body', ['count' => count($settings->recipients)]));
        } catch (Throwable $e) {
            return back()->with('error', trans('settings.warranty.test.failure_title').': '.$e->getMessage());
        }
    }

    /** @param array<string, mixed> $data */
    private function persistAuth(array $data): void
    {
        $auth = app(AuthSettings::class);
        foreach (['multi_factor_enabled', 'multi_factor_force', 'multi_factor_recoverable', 'microsoft_enabled'] as $flag) {
            $auth->{$flag} = (bool) ($data[$flag] ?? false);
        }
        foreach (['microsoft_client_id', 'microsoft_redirect', 'microsoft_tenant'] as $field) {
            $auth->{$field} = $data[$field] ?? null;
        }
        $this->assignSecrets($auth, $data, self::SECRETS['auth']);
        $auth->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistWarranty(array $data): void
    {
        $warranty = app(WarrantySettings::class);
        $warranty->enabled = (bool) ($data['enabled'] ?? false);
        $warranty->recipients = array_values($data['recipients'] ?? []);
        $warranty->lead_days = collect($data['lead_days'] ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->unique()->sortDesc()->values()->all();
        $warranty->save();
        app(ApplySettings::class)();
    }

    // ---- persist helpers (reused by test endpoints in Task 2) ----

    /** @param array<string, mixed> $data */
    private function persistGeneral(array $data): void
    {
        $general = app(GeneralSettings::class);
        $general->app_name = $data['app_name'];
        $general->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistMail(array $data): void
    {
        $mail = app(MailSettings::class);
        foreach (['default_mailer', 'from_address', 'from_name', 'smtp_host', 'smtp_port', 'smtp_scheme', 'smtp_username', 'ses_key', 'ses_region', 'postmark_message_stream_id', 'postal_domain'] as $field) {
            $mail->{$field} = $data[$field] ?? null;
        }
        $this->assignSecrets($mail, $data, self::SECRETS['mail']);
        $mail->save();
        app(ApplySettings::class)();
    }

    /** @param array<string, mixed> $data */
    private function persistStorage(array $data): void
    {
        $storage = app(StorageSettings::class);
        foreach (['key', 'region', 'bucket', 'endpoint', 'url'] as $field) {
            $storage->{$field} = $data[$field] ?? null;
        }
        $storage->use_path_style_endpoint = (bool) ($data['use_path_style_endpoint'] ?? false);
        $this->assignSecrets($storage, $data, self::SECRETS['storage']);
        $storage->save();
        app(ApplySettings::class)();
    }

    /**
     * Assign secret fields only when a non-blank value was submitted (blank keeps the stored secret).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    private function assignSecrets(object $settings, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                $settings->{$field} = $data[$field];
            }
        }
    }

    /**
     * Force secret fields to null in an outgoing settings array (defence in depth — their real
     * values aren't included above, but the key is always present so the frontend can rely on it).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $values, string $group): array
    {
        foreach (self::SECRETS[$group] ?? [] as $secret) {
            $values[$secret] = null;
        }

        return $values;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function mapOptions(array $values, string $prefix): array
    {
        return array_map(
            static fn (string $v): array => ['value' => $v, 'label' => trans("{$prefix}.{$v}")],
            $values,
        );
    }
}
