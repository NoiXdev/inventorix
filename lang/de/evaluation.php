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
    ],
];
