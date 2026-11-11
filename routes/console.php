<?php

use Illuminate\Support\Facades\Schedule;

// Unpaid holds lapse at their deadline (availability already ignores them;
// this flips their status and tells the customer)
Schedule::command('bookings:expire-holds')->everyMinute()->withoutOverlapping();

// Tomorrow's drop-offs get a reminder
Schedule::command('bookings:remind')->dailyAt('18:00')->withoutOverlapping();
