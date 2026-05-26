<?php

return [
    'nav' => [
        'label' => 'Übergaben',
        'group' => 'Inventar',
    ],
    'resource' => [
        'label' => 'Übergabe',
        'plural' => 'Übergaben',
    ],
    'type' => [
        'issue' => 'Ausgabe',
        'lend' => 'Leihgabe',
        'return' => 'Rückgabe',
        'return_defect' => 'Rückgabe (defekt)',
    ],
    'recipient_kind' => [
        'internal' => 'Interner Mitarbeiter',
        'external' => 'Externe Person',
    ],
    'recipient' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'select_user' => 'Mitarbeiter auswählen',
    ],
    'form' => [
        'accessories' => 'Zubehör',
        'accessories_placeholder' => 'z. B. Ladegerät, USB-C-Kabel, Tasche',
        'condition_notes' => 'Zustandsnotizen',
        'condition_notes_placeholder' => 'z. B. Kratzer auf Deckel',
        'terms_header' => 'Vereinbarung',
    ],
    'sign' => [
        'pad_label' => 'Unterschrift',
        'clear' => 'Löschen',
        'required' => 'Bitte unterschreiben Sie, um die Übergabe abzuschließen.',
        'submit_with_email' => 'Übergeben und per E-Mail senden',
        'submit_without_email' => 'Übergeben',
    ],
    'wizard' => [
        'step' => [
            'type' => 'Art & Gegenstände',
            'recipient' => 'Empfänger',
            'details' => 'Details',
            'sign' => 'Unterschrift',
        ],
    ],
    'action' => [
        'handover' => 'Übergabe',
        'return' => 'Rückgabe',
        'bulk' => 'Übergabe (mehrere)',
        'new' => 'Neue Übergabe',
        'retry_pdf' => 'PDF erneut erstellen',
    ],
    'notification' => [
        'success' => 'Übergabe unterzeichnet. PDF wird erstellt.',
        'pdf_failed' => 'PDF-Erstellung fehlgeschlagen — bitte erneut versuchen.',
        'email_sent' => 'E-Mail an :email gesendet.',
        'state_conflict' => 'Der Status eines oder mehrerer Gegenstände hat sich geändert. Bitte Übergabe neu starten.',
        'invalid_signature' => 'Ungültige Unterschrift — bitte erneut versuchen.',
        'view_handover' => 'Übergabe öffnen',
        'pdf_retry_dispatched' => 'PDF-Erstellung gestartet — bitte Seite neu laden.',
    ],
    'pdf' => [
        'title' => 'Übergabeprotokoll',
        'type' => 'Art',
        'recipient' => 'Empfänger',
        'recipient_internal' => 'Interner Mitarbeiter',
        'recipient_external' => 'Externe Person',
        'email' => 'E-Mail',
        'handed_by' => 'Übergeben von',
        'signed_at' => 'Unterzeichnet am',
        'signed_ip' => 'IP-Adresse',
        'assets' => 'Gegenstände',
        'accessories' => 'Zubehör',
        'condition' => 'Zustand',
        'terms' => 'Vereinbarung',
        'signature' => 'Unterschrift',
        'state_transition' => ':from → :to',
    ],
    'mail' => [
        'subject' => 'Übergabeprotokoll — :type',
        'intro' => 'Hallo :name,',
        'body' => 'anbei finden Sie das Übergabeprotokoll zu den am :date an Sie übergebenen Gegenständen.',
        'outro' => 'Bei Fragen wenden Sie sich bitte an uns.',
    ],
    'history' => [
        'event' => [
            'handover_completed' => 'Übergabe abgeschlossen',
        ],
        'summary' => [
            'handover_completed' => ':type — :recipient',
        ],
    ],
    'list' => [
        'column' => [
            'signed_at' => 'Datum',
            'type' => 'Art',
            'recipient' => 'Empfänger',
            'asset_count' => 'Gegenstände',
            'created_by' => 'Bearbeiter',
            'pdf' => 'PDF',
        ],
        'pdf_pending' => 'Wird erstellt …',
        'pdf_download' => 'Herunterladen',
    ],
    'view' => [
        'recipient_section' => 'Empfänger',
        'details_section' => 'Details',
        'assets_section' => 'Gegenstände',
        'signature_section' => 'Unterschrift',
        'meta_section' => 'Metadaten',
    ],
];
