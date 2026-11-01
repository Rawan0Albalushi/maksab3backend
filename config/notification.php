<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery mode
    |--------------------------------------------------------------------------
    |
    | Controls when email and push notifications are actually delivered.
    | This is the only switch the application reads. Change it here, or with
    | "php artisan notification:mode <mode>", and everything else follows.
    |
    | Available modes:
    |
    |   after_response  Response is flushed to the browser first, then the
    |                   notifications are sent in the same PHP process.
    |                   Fastest response. Needs no cron, worker or Supervisor.
    |                   No automatic retry, and the PHP process stays busy
    |                   until delivery finishes.
    |
    |   cron            Notifications are stored in the "jobs" table and drained
    |                   every minute by the scheduler. Fast response, with retry
    |                   and failed-job tracking. Needs only the schedule:run
    |                   cron entry the admin panel already documents.
    |
    |   queue           Notifications are stored in the "jobs" table and picked
    |                   up immediately by a long-running worker. Fast response,
    |                   retry, and the process is freed at once.
    |                   Needs Supervisor or an equivalent worker.
    |
    |   sync            Delivered during the request. The response waits for
    |                   every email and push to finish. Slowest, but the
    |                   simplest to debug.
    |
    | Run "php artisan notification:doctor" to check the current mode is
    | correctly set up.
    |
    */

    'mode' => env('NOTIFICATION_MODE', 'after_response'),

    'available_modes' => ['after_response', 'cron', 'queue', 'sync'],

    'setting_key' => 'notification_mode',

    'connections' => [
        'sync' => 'sync',
        'after_response' => 'sync',
        'cron' => 'database',
        'queue' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),
    ],

    'queues' => [
        'push' => 'notifications-high',
        'mail' => 'notifications',
        'bulk' => 'notifications-bulk',
    ],

    'retry' => [
        'tries' => 3,
        'backoff' => [10, 60, 300],
        'timeout' => 30,
    ],

    'drain' => [
        'max_time' => 55,
        'sleep' => 1,
    ],

    'token_columns' => [
        'users' => 'cm_firebase_token',
        'vendors' => 'firebase_token',
        'delivery_men' => 'fcm_token',
        'vendor_employees' => 'firebase_token',
        'guests' => 'fcm_token',
    ],

    'sms' => [
        'connect_timeout' => 5,
        'timeout' => 30,
    ],

    'fcm' => [
        'connect_timeout' => 5,
        'timeout' => 10,
        'batch_size' => 500,
        'prune_dead_tokens' => true,
        'android_channel' => env('FCM_ANDROID_CHANNEL', '6ammart'),
        'sound' => 'notification.wav',
    ],

    'log_channel' => env('NOTIFICATION_LOG_CHANNEL', 'notifications'),

];
