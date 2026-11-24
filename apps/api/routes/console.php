<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('suppliers:check-health')->everyFiveMinutes();
Schedule::command('subscriptions:expire')->hourly();
