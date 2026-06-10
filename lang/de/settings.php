<?php

return [
    'nav' => [
        'cluster' => 'Einstellungen',
    ],
    'mail' => [
        'nav' => 'E-Mail',
        'title' => 'E-Mail-Einstellungen',
        'section' => [
            'from' => 'Absender',
            'smtp' => 'SMTP',
            'ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'resend' => 'Resend',
            'postal' => 'Postal',
        ],
        'field' => [
            'from_address' => 'Absenderadresse',
            'from_name' => 'Absendername',
            'driver' => 'Mail-Treiber',
            'smtp_host' => 'Host',
            'smtp_port' => 'Port',
            'smtp_scheme' => 'Verschlüsselung',
            'smtp_scheme_placeholder' => 'Automatisch (anhand des Ports)',
            'smtp_scheme_help' => 'Lassen Sie die Auswahl auf „Automatisch“, sofern Ihr Server kein bestimmtes Schema verlangt. Port 465 nutzt SSL/TLS, andere Ports nutzen STARTTLS.',
            'smtp_username' => 'Benutzername',
            'smtp_password' => 'Passwort',
            'ses_key' => 'Access Key ID',
            'ses_secret' => 'Secret Access Key',
            'ses_region' => 'Region',
            'postmark_token' => 'Server-Token',
            'postmark_message_stream_id' => 'Message-Stream-ID',
            'resend_key' => 'API-Schlüssel',
            'postal_domain' => 'Server-URL',
            'postal_domain_help' => 'Die HTTPS-URL Ihres Postal-Servers.',
            'postal_key' => 'API-Schlüssel',
        ],
        'driver' => [
            'smtp' => 'SMTP',
            'postal' => 'Postal',
            'ses' => 'Amazon SES',
            'postmark' => 'Postmark',
            'resend' => 'Resend',
            'sendmail' => 'Sendmail',
            'log' => 'Log (kein Versand)',
        ],
        'scheme' => [
            'starttls' => 'STARTTLS',
            'ssl' => 'SSL/TLS',
        ],
        'test' => [
            'action' => 'Test-E-Mail senden',
            'recipient' => 'Senden an',
            'success_title' => 'Test-E-Mail gesendet',
            'success_body' => 'Gesendet an :email.',
            'failure_title' => 'Test-E-Mail fehlgeschlagen',
        ],
    ],
    'general' => [
        'nav' => 'Allgemein',
        'title' => 'Allgemeine Einstellungen',
        'field' => [
            'app_name' => 'Anwendungsname',
        ],
    ],
    'auth' => [
        'nav' => 'Authentifizierung',
        'title' => 'Authentifizierung',
        'multi_factor' => [
            'section' => 'Zwei-Faktor-Authentifizierung',
            'field' => [
                'enabled' => 'Aktiviert',
                'force' => 'Für alle Benutzer erzwingen',
                'recoverable' => 'Wiederherstellungscodes erlauben',
            ],
        ],
        'microsoft' => [
            'section' => 'Microsoft Azure / Entra ID',
            'field' => [
                'enabled' => 'Anmeldung aktiviert',
                'client_id' => 'Client-ID',
                'client_secret' => 'Client-Secret',
                'redirect' => 'Redirect-URI',
                'tenant' => 'Tenant-ID',
            ],
        ],
    ],
];
