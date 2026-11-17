<?php

use App\Models\IndicadorMedico;
use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/v1/turnos', \App\Http\Controllers\Api\TurnosController::class)
    ->middleware(['throttle:60,1', \App\Http\Middleware\TurnosApiKey::class])
    ->name('api.v1.turnos');

Route::post('/whatsapp/webhook', \App\Http\Controllers\Api\WhatsappWebhookController::class . '@handle')
    ->name('api.whatsapp.webhook');

Route::post('/whatsapp/enviar', \App\Http\Controllers\Api\WhatsappEnviarController::class . '@handle')
    ->middleware('throttle:30,1')
    ->name('api.whatsapp.enviar');

Route::get('/medicos', function (Request $request) {
    $archivoId = $request->get('archivo_id');

    if (!$archivoId) return response()->json([]);

    $medicos = IndicadorMedico::with(['medico', 'uci'])
        ->where('archivo_id', $archivoId)
        ->get()
        ->map(fn($i) => [
            'id'    => $i->medico_id,
            'nombre'=> $i->medico->nombre,
            'uci'   => $i->uci->nombre,
        ])
        ->sortBy('nombre')
        ->values();

    return response()->json($medicos);
});
