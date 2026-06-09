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
        'asset_value' => [
            'label' => 'Asset-Wert / Finanzen',
            'description' => 'Kaufwert gruppiert nach Mitarbeiter, Typ oder Status – aggregiert oder im Detail.',
            'filter' => [
                'group_by' => 'Gruppieren nach',
                'detailed' => 'Detailansicht (pro Asset)',
            ],
            'group_by' => [
                'employee' => 'Mitarbeiter',
                'asset_type' => 'Typ',
                'state' => 'Status',
            ],
            'columns' => [
                'group' => 'Gruppe',
                'assets_count' => 'Anzahl',
                'total_price' => 'Gesamtwert',
                'owner' => 'Mitarbeiter',
                'asset_type' => 'Typ',
                'model' => 'Modell',
                'serial_number' => 'Seriennummer',
                'state' => 'Status',
                'buy_price' => 'Kaufpreis',
            ],
            'total' => 'Gesamt',
        ],
        'state_overview' => [
            'label' => 'Status-Übersicht',
            'description' => 'Anzahl und Gesamtwert der Assets je Status.',
            'columns' => [
                'state' => 'Status',
                'assets_count' => 'Anzahl',
                'total_price' => 'Gesamtwert',
            ],
        ],
        'incident_history' => [
            'label' => 'Reparatur-Historie',
            'description' => 'Vorfälle/Reparaturen je Asset im Zeitraum.',
            'filter' => [
                'from' => 'Von',
                'to' => 'Bis',
                'status' => 'Status',
            ],
            'status' => [
                'open' => 'Offen',
                'closed' => 'Geschlossen',
            ],
            'columns' => [
                'asset' => 'Asset',
                'owner' => 'Mitarbeiter',
                'title' => 'Titel',
                'open_date' => 'Geöffnet',
                'closed_date' => 'Geschlossen',
                'state' => 'Status',
            ],
        ],
        'asset_aging' => [
            'label' => 'Alter / Ersatzplanung',
            'description' => 'Assets, die älter als eine gewählte Anzahl Jahre sind.',
            'filter' => [
                'min_age_years' => 'Mindestalter (Jahre)',
            ],
            'columns' => [
                'model' => 'Modell',
                'serial_number' => 'Seriennummer',
                'owner' => 'Mitarbeiter',
                'buy_date' => 'Kaufdatum',
                'age_years' => 'Alter (Jahre)',
                'buy_price' => 'Kaufpreis',
                'state' => 'Status',
            ],
        ],
    ],
];
