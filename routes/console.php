<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envía el recordatorio de turno 12h antes de que inicie (revisa cada 15 min).
Schedule::command('turnos:recordatorios')->everyFifteenMinutes();
