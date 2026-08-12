<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be applied to all ZKTeco biometric routes.
    | Leave empty to use the default routes without prefix.
    |
    */

    'route_prefix' => '',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware that should be applied to ZKTeco routes.
    | You can add authentication, throttling, or other middleware here.
    |
    */

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure request/response logging for debugging ZKTeco communication.
    |
    */

    'logging' => [
        'enabled' => env('ZKTECO_LOGGING_ENABLED', true),
        'respect_app_debug' => env('ZKTECO_LOGGING_RESPECT_APP_DEBUG', true),
        'channel' => env('ZKTECO_LOG_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Device Timeout Settings
    |--------------------------------------------------------------------------
    |
    | Configure timeout settings for device communication.
    |
    */

    'timeout' => [
        'connection' => env('ZKTECO_CONNECTION_TIMEOUT', 5),
        'command' => env('ZKTECO_COMMAND_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attendance Processing
    |--------------------------------------------------------------------------
    |
    | Configure how attendance records should be processed.
    |
    */

    'attendance' => [
        'auto_process' => env('ZKTECO_AUTO_PROCESS_ATTENDANCE', true),
        'timezone' => env('ZKTECO_TIMEZONE', config('app.timezone')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Command Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configure command queue processing for device commands.
    |
    */

    'commands' => [
        'auto_retry' => env('ZKTECO_AUTO_RETRY_COMMANDS', true),
        'max_retries' => env('ZKTECO_MAX_COMMAND_RETRIES', 3),
        'retry_delay' => env('ZKTECO_COMMAND_RETRY_DELAY', 60), // seconds
    ],
];
