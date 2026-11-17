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

// Resumen de quién está de turno hoy por WhatsApp, lunes a viernes 07:00 (America/Bogota).
Schedule::command('turnos:resumen-diario-whatsapp')->weekdays()->at('07:00')->timezone('America/Bogota');

// Anticipo del turno nocturno por WhatsApp: lunes a jueves 16:00, viernes 15:30 (America/Bogota).
Schedule::command('turnos:anticipo-nocturno-whatsapp')->days([1, 2, 3, 4])->at('16:00')->timezone('America/Bogota');
Schedule::command('turnos:anticipo-nocturno-whatsapp')->fridays()->at('15:30')->timezone('America/Bogota');
