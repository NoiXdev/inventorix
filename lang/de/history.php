<?php

return [
    'label'        => 'Historie',
    'label-plural' => 'Historie',
    'tab'          => 'Historie',
    'empty_state'  => 'Noch keine Historie — Änderungen an diesem Gegenstand erscheinen hier.',

    'add_note'      => 'Notiz hinzufügen',
    'add_note_body' => 'Notiz',
    'add_note_save' => 'Speichern',

    'event' => [
        'created'        => 'Erstellt',
        'updated'        => 'Geändert',
        'deleted'        => 'Gelöscht',
        'note'           => 'Notiz',
        'owner_changed'  => 'Besitzer geändert',
        'place_changed'  => 'Standort geändert',
        'state_changed'  => 'Status geändert',
    ],

    'summary' => [
        'created'         => 'Erstellt',
        'deleted'         => 'Gelöscht',
        'fields_changed'  => ':count Feld(er) geändert',
        'set'             => ':attr gesetzt auf :value',
        'cleared'         => ':attr geleert',
        'incident_prefix' => 'Vorfall #:id: ',
        'incident_removed' => 'Vorfall (gelöscht): ',
    ],

    'causer' => [
        'system'        => 'System',
        'former_user'   => 'System (ehemaliger Nutzer)',
    ],

    'column' => [
        'user'  => 'Benutzer',
        'event' => 'Ereignis',
    ],

    'filter' => [
        'event' => 'Ereignis',
        'from'  => 'Von',
        'until' => 'Bis',
    ],
];
