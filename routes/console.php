<?php

use Illuminate\Support\Facades\Schedule;

// Publishes any scheduled post whose time has arrived.
Schedule::command('iden:publish-due-posts')->everyMinute()->withoutOverlapping();

// Tops up credits for active subscriptions at the start of each month.
Schedule::command('iden:grant-monthly-credits')->monthlyOn(1, '00:10');

// Recovers AI jobs that were charged but never queued, or got stuck.
Schedule::command('iden:reconcile-ai-jobs')->everyFiveMinutes()->withoutOverlapping();

// Keeps usage/activity/failed-job tables from growing unbounded.
Schedule::command('iden:prune-logs')->dailyAt('02:30');

// Rolls the prior month's usage into cheap pre-aggregated rows.
Schedule::command('iden:aggregate-monthly-usage')->monthlyOn(1, '01:00');
