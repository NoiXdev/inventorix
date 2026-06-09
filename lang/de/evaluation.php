<?php

return [
    'label' => 'Auswertung',
    'label-plural' => 'Auswertungen',

    'actions' => [
        'pdf' => 'PDF herunterladen',
        'export' => 'Exportieren',
    ],

    'pdf' => [
        'no_data' => 'Keine Daten für die gewählten Filter.',
    ],

    'reports' => [
        'assets_per_employee' => [
            'label' => 'Assets pro Mitarbeiter',
            'description' => 'Alle Assets eines oder mehrerer Mitarbeiter.',
            'filter' => [
                'employees' => 'Mitarbeiter',
            ],
            'pdf' => [
                'no_owner' => 'Ohne Mitarbeiter zugeordnet',
            ],
            'columns' => [
                'owner' => 'Mitarbeiter',
                'asset_type' => 'Typ',
                'model' => 'Modell',
                'serial_number' => 'Seriennummer',
                'state' => 'Status',
                'guarantee_end' => 'Garantie bis',
                'buy_price' => 'Kaufpreis',
            ],
        ],
        'guarantee_status' => [
            'label' => 'Garantie-Status',
            'description' => 'Geräte mit Garantieablauf; abgelaufen und bald ablaufend hervorgehoben.',
            'filter' => [
                'status' => 'Status',
                'employees' => 'Mitarbeiter',
            ],
            'status' => [
                'expired' => 'Abgelaufen',
                'expiring_soon' => 'Läuft bald ab',
                'valid' => 'Gültig',
                'none' => 'Keine Garantie',
            ],
            'columns' => [
                'owner' => 'Mitarbeiter',
                'model' => 'Modell',
                'serial_number' => 'Seriennummer',
                'guarantee_end' => 'Garantie bis',
                'state' => 'Garantie-Status',
                'days_left' => 'Tage verbleibend',
            ],
        ],
        'inventory_by_location' => [
            'label' => 'Bestand nach Standort',
            'description' => 'Alle Assets an einem oder mehreren Standorten.',
            'filter' => [
                'places' => 'Standorte',
            ],
            'columns' => [
                'place' => 'Standort',
                'asset_type' => 'Typ',
                'model' => 'Modell',
                'serial_number' => 'Seriennummer',
                'state' => 'Status',
                'owner' => 'Mitarbeiter',
            ],
        ],
    ],
];
