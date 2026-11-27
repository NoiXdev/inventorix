<?php

return [
    'reminder' => [
        'subject' => 'Rückgabe fällig: :count Gerät(e)',
        'greeting' => 'Hallo :name,',
        'intro' => 'die folgenden Geräte sind dir ausgegeben und sollen bald bzw. sollten bereits zurückgegeben werden:',
        'col' => [
            'asset' => 'Gerät',
            'tag' => 'Asset-Tag',
            'serial' => 'Seriennummer',
            'due' => 'Rückgabe bis',
        ],
        'overdue' => 'überfällig',
        'outro' => 'Bitte melde dich bei der IT, um die Rückgabe zu vereinbaren.',
    ],
    'digest' => [
        'subject' => 'Inventorix: :count fällige Rückgaben und Audits',
        'greeting' => 'Übersicht fälliger Rückgaben und Audits',
        'section' => [
            'checkin_overdue' => 'Rückgabe überfällig',
            'checkin_due' => 'Rückgabe bald fällig',
            'audit_overdue' => 'Audit überfällig',
            'audit_due' => 'Audit bald fällig',
        ],
        'col' => [
            'asset' => 'Asset',
            'tag' => 'Asset-Tag',
            'assignee' => 'Zugewiesen an',
            'location' => 'Standort',
            'date' => 'Datum',
        ],
        'outro' => 'Diese Nachricht wurde automatisch von Inventorix versendet.',
    ],
];
