<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    |
    | Define qué canal usará Laravel por defecto al enviar notificaciones.
    | Podés cambiarlo en tu archivo .env con NOTIFICATION_DRIVER.
    |
    | Canales soportados: "mail", "database", "broadcast"
    |
    */

    'default' => env('NOTIFICATION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Available Notification Channels
    |--------------------------------------------------------------------------
    |
    | Aquí definís los canales disponibles para enviar notificaciones.
    |
    */

    'channels' => [
        'database' => [
            'driver' => 'database',
        ],

        'mail' => [
            'driver' => 'mail',
        ],

        'broadcast' => [
            'driver' => 'broadcast',
        ],
    ],

];
