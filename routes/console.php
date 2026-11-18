<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envía el recordatorio de turno 12h antes de que inicie (revisa cada 15 min).
Schedule::command('turnos:recordatorios')->everyFifteenMinutes();

// Envía y archiva el consolidado mensual el último día de cada mes a las 22:30.
Schedule::command('turnos:enviar-mensual')->lastDayOfMonth('22:30');

// Resumen de quién está de turno hoy (día y noche) al grupo de la UCI, todos los días 06:30 (America/Bogota).
Schedule::command('turnos:resumen-diario-whatsapp')->dailyAt('06:30')->timezone('America/Bogota');

// Anticipo del turno nocturno de hoy al grupo de la UCI, todos los días 18:30 (America/Bogota).
Schedule::command('turnos:anticipo-nocturno-whatsapp')->dailyAt('18:30')->timezone('America/Bogota');
