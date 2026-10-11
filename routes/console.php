<?php

use Illuminate\Support\Facades\Schedule;

/*
| Expiry alerts depend on the calendar, not only on stock movements, so they
| are re-evaluated every hour (and just after midnight when dates roll over).
| Run `php artisan schedule:work` locally to keep the scheduler running.
*/

Schedule::command('inventory:refresh-alerts')->hourly()->withoutOverlapping();
Schedule::command('inventory:refresh-alerts')->dailyAt('00:01')->withoutOverlapping();
