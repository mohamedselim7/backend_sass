<?php

use Illuminate\Support\Facades\Schedule;

// Publishes any scheduled post whose time has arrived.
Schedule::command('iden:publish-due-posts')->everyMinute()->withoutOverlapping();

// Tops up credits for active subscriptions at the start of each month.
Schedule::command('iden:grant-monthly-credits')->monthlyOn(1, '00:10');
