<?php

use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeTurnoAhoraController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AusenciaController;
use App\Http\Controllers\CambioTurnoController;
use App\Http\Controllers\FormatoInicialController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\PlanificacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SemanaMoldeController;
use App\Http\Controllers\UciController;
use Illuminate\Support\Facades\Route;

// ── Autenticación (públicas) ────────────────────────────────────
Route::get('/login',  [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// ── Rutas protegidas (requieren login) ─────────────────────────
Route::middleware('auth')->group(function () {

    Route::redirect('/', '/dashboard');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    // Calendario visual (vista amigable mes/UCI)
    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::post('/calendario/descargar', [CalendarioController::class, 'descargarExcel'])->name('calendario.descargar');

    // Archivos / Importación — solo master puede subir/borrar
    Route::get('/archivos', [ArchivoController::class, 'index'])->name('archivos.index');
    Route::post('/archivos/upload', [ArchivoController::class, 'upload'])
        ->middleware('master')->name('archivos.upload');
    Route::delete('/archivos/{archivo}', [ArchivoController::class, 'destroy'])
        ->middleware('master')->name('archivos.destroy');

    // Planificación mensual — editar solo master
    Route::get('/planificacion', [PlanificacionController::class, 'index'])->name('planificacion.index');
    Route::post('/planificacion/editar', [PlanificacionController::class, 'editarCelda'])
        ->middleware('master')->name('planificacion.editar');
    Route::get('/planificacion/resumen-medico', [PlanificacionController::class, 'resumenMedico'])
        ->name('planificacion.resumen-medico');

    // Formato inicial + repetir secuencia — solo master
    Route::get('/formato-inicial', [FormatoInicialController::class, 'index'])->name('formato-inicial.index');
    Route::post('/formato-inicial/excel', [FormatoInicialController::class, 'cargarExcel'])
        ->middleware('master')->name('formato-inicial.excel');
    Route::post('/formato-inicial/manual', [FormatoInicialController::class, 'guardarManual'])
        ->middleware('master')->name('formato-inicial.manual');
    Route::post('/formato-inicial/repetir', [FormatoInicialController::class, 'repetirAnio'])
        ->middleware('master')->name('formato-inicial.repetir');
    Route::delete('/formato-inicial/{plantilla}', [FormatoInicialController::class, 'destroyPlantilla'])
        ->middleware('master')->name('formato-inicial.destroy');

    // De turno ahora
    Route::get('/de-turno-ahora', [DeTurnoAhoraController::class, 'index'])->name('turno-ahora.index');

    // Médicos
    Route::get('/medicos', [MedicoController::class, 'index'])->name('medicos.index');
    Route::get('/medicos/{medico}', [MedicoController::class, 'show'])->name('medicos.show');

    // UCIs
    Route::get('/ucis', [UciController::class, 'index'])->name('ucis.index');
    Route::get('/ucis/{uci}', [UciController::class, 'show'])->name('ucis.show');

    // Semanas molde — solo master
    Route::resource('semanas-molde', SemanaMoldeController::class)->names('semanas-molde')
        ->middleware('master');
    Route::post('/semanas-molde/{semanaMolde}/aplicar', [SemanaMoldeController::class, 'aplicar'])
        ->middleware('master')->name('semanas-molde.aplicar');

    // Ausencias
    Route::resource('ausencias', AusenciaController::class)->names('ausencias');
    Route::patch('/ausencias/{ausencia}/aprobar', [AusenciaController::class, 'aprobar'])
        ->middleware('master')->name('ausencias.aprobar');
    Route::patch('/ausencias/{ausencia}/rechazar', [AusenciaController::class, 'rechazar'])
        ->middleware('master')->name('ausencias.rechazar');

    // Cambios de turno
    Route::resource('cambios-turno', CambioTurnoController::class)->names('cambios-turno');
    Route::patch('/cambios-turno/{cambio}/aceptar', [CambioTurnoController::class, 'aceptar'])
        ->name('cambios-turno.aceptar');
    Route::patch('/cambios-turno/{cambio}/rechazar-colega', [CambioTurnoController::class, 'rechazarColega'])
        ->name('cambios-turno.rechazar-colega');
    Route::patch('/cambios-turno/{cambio}/aprobar', [CambioTurnoController::class, 'aprobar'])
        ->middleware('master')->name('cambios-turno.aprobar');
    Route::patch('/cambios-turno/{cambio}/rechazar', [CambioTurnoController::class, 'rechazar'])
        ->middleware('master')->name('cambios-turno.rechazar');

    // Alertas
    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::patch('/alertas/{alerta}/estado', [AlertaController::class, 'cambiarEstado'])
        ->middleware('master')->name('alertas.estado');
    Route::delete('/alertas/{alerta}', [AlertaController::class, 'destroy'])
        ->middleware('master')->name('alertas.destroy');
    Route::post('/alertas/ejecutar/{archivoId}', [AlertaController::class, 'ejecutarValidacion'])
        ->middleware('master')->name('alertas.ejecutar');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::post('/reportes/excel', [ReporteController::class, 'exportarExcel'])->name('reportes.excel');
    Route::post('/reportes/pdf', [ReporteController::class, 'exportarPdf'])->name('reportes.pdf');
    Route::post('/reportes/medico/excel', [ReporteController::class, 'exportarMedicoExcel'])->name('reportes.medico.excel');
    Route::post('/reportes/medico/pdf', [ReporteController::class, 'exportarMedicoPdf'])->name('reportes.medico.pdf');

    // Configuración — solo master
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    Route::put('/configuracion/cobertura/{uci}', [ConfiguracionController::class, 'actualizarCobertura'])
        ->middleware('master')->name('configuracion.cobertura');

    // Usuarios — solo master
    Route::get('/usuarios', [AuthController::class, 'usuarios'])
        ->middleware('master')->name('usuarios.index');
    Route::post('/usuarios', [AuthController::class, 'crearUsuario'])
        ->middleware('master')->name('usuarios.crear');
    Route::delete('/usuarios/{usuario}', [AuthController::class, 'eliminarUsuario'])
        ->middleware('master')->name('usuarios.eliminar');
    Route::patch('/usuarios/{usuario}/password', [AuthController::class, 'cambiarPassword'])
        ->middleware('master')->name('usuarios.password');
});
