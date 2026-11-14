<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Horizon (spec §10.3)
|--------------------------------------------------------------------------
| Horizon runs only in the worker pods (`php artisan horizon`, spec §3.4).
| Each queue family has its own supervisor so bulk work never starves the
| critical queue. The worker pod's terminationGracePeriodSeconds (900 s)
| covers the longest timeout below, and the Redis queue connection's
| retry_after (config/queue.php) is longer than every timeout so a running
| job is never handed to a second worker.
*/

return [

    'name' => env('HORIZON_NAME'),

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', Str::slug((string) env('APP_NAME', 'pms'), '_').'_horizon:'),

    // Same session as the SPA; access is limited to Super Admins by the viewHorizon gate.
    'middleware' => ['web'],

    // Long-wait notifications, in seconds, per connection:queue.
    'waits' => [
        'redis:critical' => 30,
        'redis:channel-sync' => 120,
        'redis:notifications' => 120,
        'redis:webhooks' => 300,
        'redis:reports' => 900,
        'redis:default' => 120,
    ],

    // Minutes to keep job records.
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    'silenced' => [],

    'silenced_tags' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 128,

    /*
    | Supervisors. Job classes still declare their own $timeout, $tries and backoff
    | (spec §10.2); these are the ceilings for each queue family.
    */
    'defaults' => [
        // Payment webhooks, folio postings, check-out side effects: small pool, highest priority.
        'supervisor-critical' => [
            'connection' => 'redis',
            'queue' => ['critical'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],

        // Channel sync, messaging, outbound webhooks and everything else.
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['channel-sync', 'notifications', 'webhooks', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],

        // Large reports and exports: long timeout, low priority.
        'supervisor-reports' => [
            'connection' => 'redis',
            'queue' => ['reports'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'maxTime' => 3600,
            'maxJobs' => 100,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 600,
            'nice' => 10,
        ],
    ],

    // Process counts per pod; total capacity also scales with the worker replica count.
    'environments' => [
        'production' => [
            'supervisor-critical' => ['maxProcesses' => 3],
            'supervisor-default' => ['maxProcesses' => 10],
            'supervisor-reports' => ['maxProcesses' => 2],
        ],

        'staging' => [
            'supervisor-default' => ['maxProcesses' => 4],
        ],

        'local' => [],
    ],

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'routes',
        'composer.lock',
        '.env',
    ],
];
