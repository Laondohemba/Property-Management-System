<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (spec §10.4)
|--------------------------------------------------------------------------
| Run by the single scheduler pod (`php artisan schedule:work`, spec §3.4).
| Every task runs on one server and never overlaps, backed by Redis locks, as
| defence in depth if a second scheduler ever starts during a rollout. Tasks
| dispatch work to the queues rather than doing heavy work here.
*/

Schedule::command('horizon:snapshot')
    ->everyFiveMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('model:prune')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();
