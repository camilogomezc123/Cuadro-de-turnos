<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'activo'        => env('WHATSAPP_ACTIVO', false),
        'driver'        => env('WHATSAPP_DRIVER', 'local'), // 'local' (whatsapp-web.js) o 'evolution' (Evolution API)
        'url'           => env('WHATSAPP_API_URL', 'http://127.0.0.1:3001'),
        'api_key'       => env('WHATSAPP_API_KEY'),
        // Si está lleno, TODOS los mensajes (sin importar el destinatario real) se redirigen
        // aquí. Para probar en producción sin arriesgarse a notificar a médicos reales.
        'test_override' => env('WHATSAPP_TEST_OVERRIDE'),
        // Destinatario de los resúmenes de turnos (número o, más adelante, JID de grupo).
        'resumen_destino' => env('WHATSAPP_RESUMEN_DESTINO'),
        // Clave para que automatizaciones externas (p.ej. Codex/ChatGPT) pidan el envío
        // de un WhatsApp a través de esta app, sin necesitar su propia sesión de WhatsApp.
        'inbound_key' => env('WHATSAPP_INBOUND_KEY'),
    ],

    'evolution' => [
        'url'            => env('EVOLUTION_URL', 'http://127.0.0.1:8088'),
        'instance'       => env('EVOLUTION_INSTANCE'),
        'token'          => env('EVOLUTION_TOKEN'),
        'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
    ],

];
