<?php

return [
    'disk' => env('HANDOVER_DISK', 'local'),

    'company' => [
        'name' => env('APP_COMPANY_NAME', config('app.name')),
        'logo' => env('APP_COMPANY_LOGO'),
    ],

    'terms' => <<<'TXT'
Ich bestätige, dass ich die oben aufgeführten Gegenstände in einwandfreiem
Zustand übernommen habe und für deren sachgemäßen Gebrauch und Rückgabe
verantwortlich bin.
TXT,

    'pdf' => [
        'paper' => 'a4',
        'orientation' => 'portrait',
    ],

    'signature' => [
        'max_bytes' => 200 * 1024,
        'width' => 600,
        'height' => 200,
    ],
];
