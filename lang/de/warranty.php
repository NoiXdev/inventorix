<?php

return [
    'mail' => [
        'subject' => 'Garantie-Ablauf: :count Asset(s) benötigen Aufmerksamkeit',
        'greeting' => 'Garantie-Übersicht',
        'intro' => 'Die folgenden Assets nähern sich dem Garantieende oder haben es bereits erreicht.',
        'section' => [
            'expired' => 'Garantie abgelaufen',
            'lead' => 'Garantie endet in :days Tagen',
        ],
        'col' => [
            'owner' => 'Besitzer',
            'model' => 'Modell',
            'serial' => 'Seriennummer',
            'guarantee_end' => 'Garantieende',
            'days_left' => 'Verbleibende Tage',
        ],
        'outro' => 'Diese Nachricht wurde automatisch von Inventorix versendet.',
    ],
    'widget' => [
        'stats' => [
            'expired' => 'Garantie abgelaufen',
            'soon_30' => 'Läuft in ≤ 30 Tagen ab',
            'soon_90' => 'Läuft in ≤ 90 Tagen ab',
        ],
        'table' => [
            'heading' => 'Demnächst ablaufende Garantien',
            'owner' => 'Besitzer',
            'model' => 'Modell',
            'serial' => 'Seriennummer',
            'guarantee_end' => 'Garantieende',
            'days_left' => 'Tage',
        ],
    ],
];
