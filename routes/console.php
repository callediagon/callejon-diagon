<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('transfers:expire')->everyFiveMinutes();