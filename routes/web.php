<?php

use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConsolidadoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeTurnoAhoraController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AusenciaController;
use App\Http\Controllers\CambioTurnoController;
use App\Http\Controllers\FormatoInicialController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\NovedadController;
use App\Http\Controllers\PlanificacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SemanaMoldeController;
use App\Http\Controllers\SecuenciaUciController;
use App\Http\Controllers\TurnoEditorController;
use App\Http\Controllers\UciController;
use App\Http\Controllers\MedicoPortalController;
use App\Http\Controllers\BurnoutController;
use App\Http\Controllers\MedicoDuplicadoController;
use App\Http\Controllers\MiTurnoController;
use App\Http\Controllers\HistorialController;
use Illuminate\Support\Facades\Route;

// ── Autenticación (públicas) ────────────────────────────────────
Route::get('/login',  [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->post('/consentimiento', [AuthController::class, 'aceptarConsentimiento'])->name('consentimiento.aceptar');

// ── Portal médico ───────────────────────────────────────────────
Route::middleware('auth')->prefix('mi-portal')->name('medico.')->group(function () {
    Route::get('/',                                        [MedicoPortalController::class, 'portal'])        ->name('portal');
    Route::post('/ofrecer-turno',                          [MedicoPortalController::class, 'ofrecerTurno'])  ->name('ofrecer-turno');
    Route::post('/aceptar-oferta/{solicitud}',             [MedicoPortalController::class, 'aceptarOferta']) ->name('aceptar-oferta');
    Route::post('/solicitar-cambio',                       [MedicoPortalController::class, 'solicitarCambio'])->name('solicitar-cambio');
    Route::patch('/responder-cambio/{solicitud}',          [MedicoPortalController::class, 'responderCambio'])->name('responder-cambio');
    Route::delete('/cancelar-cambio/{solicitud}',          [MedicoPortalController::class, 'cancelarCambio'])->name('cancelar-cambio');
    Route::get('/turnos-medico',                           [MedicoPortalController::class, 'turnosMedico'])  ->name('turnos-api');
    Route::get('/candidatos-cambio',                       [MedicoPortalController::class, 'candidatosCambio'])->name('candidatos-api');
    Route::patch('/credenciales',                          [MedicoPortalController::class, 'actualizarCredenciales'])->name('credenciales');
});

// ── Burnout: rutas accesibles por todos los usuarios autenticados ──
Route::middleware('auth')->prefix('burnout')->name('burnout.')->group(function () {
    Route::get('/verificar',  [BurnoutController::class, 'verificar']) ->name('verificar');
    Route::post('/responder', [BurnoutController::class, 'responder']) ->name('responder');
    Route::post('/posponer',  [BurnoutController::class, 'posponer'])  ->name('posponer');
});

// ── Rutas protegidas ────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::post('/calendario/descargar', [CalendarioController::class, 'descargarExcel'])->name('calendario.descargar');

    Route::get('/de-turno-ahora', [DeTurnoAhoraController::class, 'index'])->name('turno-ahora.index');

    Route::get('/ucis',       [UciController::class, 'index'])->name('ucis.index');
    Route::get('/ucis/{uci}', [UciController::class, 'show']) ->name('ucis.show');

    // ── Mi Turno (médico operativo ve su propio cuadro del mes) ────
    Route::get('/mi-turno', [MiTurnoController::class, 'index'])->name('mi-turno.index');

    // ── Novedades y cambios: accesibles por operativo (ven solo las suyas) ──
    Route::get('/novedades',       [NovedadController::class, 'index'])->name('novedades.index');
    Route::post('/novedades',      [NovedadController::class, 'store'])->name('novedades.store');
    Route::get('/cambios-turno',                    [CambioTurnoController::class, 'index'])          ->name('cambios-turno.index');
    Route::get('/cambios-turno/mis-turnos',         [CambioTurnoController::class, 'misTurnos'])      ->name('cambios-turno.mis-turnos');
    Route::get('/cambios-turno/turnos-receptor',    [CambioTurnoController::class, 'turnosReceptor']) ->name('cambios-turno.turnos-receptor');

    // Médico puede crear solicitud, responder como receptor, y cancelar la propia
    Route::post('/cambios-turno',                              [CambioTurnoController::class, 'store'])          ->name('cambios-turno.store');
    Route::patch('/cambios-turno/{cambio}/aceptar',            [CambioTurnoController::class, 'aceptar'])        ->name('cambios-turno.aceptar');
    Route::patch('/cambios-turno/{cambio}/rechazar-colega',    [CambioTurnoController::class, 'rechazarColega']) ->name('cambios-turno.rechazar-colega');
    Route::patch('/cambios-turno/{cambio}/cancelar',           [CambioTurnoController::class, 'cancelar'])       ->name('cambios-turno.cancelar');

    // ── Solo maestro ──────────────────────────────────────────────
    Route::middleware('master')->group(function () {

        // Archivos
        Route::get('/archivos',            [ArchivoController::class, 'index'])  ->name('archivos.index');
        Route::post('/archivos/upload',    [ArchivoController::class, 'upload']) ->name('archivos.upload');
        Route::delete('/archivos/{archivo}',[ArchivoController::class,'destroy'])->name('archivos.destroy');

        // Planificación
        Route::get('/planificacion',              [PlanificacionController::class, 'index'])       ->name('planificacion.index');
        Route::post('/planificacion/editar',      [PlanificacionController::class, 'editarCelda']) ->name('planificacion.editar');
        Route::get('/planificacion/resumen-medico',[PlanificacionController::class,'resumenMedico'])->name('planificacion.resumen-medico');

        // Editor de turnos
        Route::get('/turnos/editor',                        [TurnoEditorController::class, 'index'])           ->name('turno-editor.index');
        Route::post('/turnos/editor/secuencia',             [TurnoEditorController::class, 'guardarSecuencia'])->name('turno-editor.guardar-secuencia');
        Route::post('/turnos/editor/repetir',               [TurnoEditorController::class, 'repetirSecuencia'])->name('turno-editor.repetir');
        Route::post('/turnos/editor/agregar-medico',        [TurnoEditorController::class, 'agregarMedico'])   ->name('turno-editor.agregar-medico');
        Route::post('/turnos/editor/sustituir',             [TurnoEditorController::class, 'sustituir'])       ->name('turno-editor.sustituir');
        Route::patch('/turnos/editor/medico/{medico}',      [TurnoEditorController::class, 'actualizarMedico'])->name('turno-editor.actualizar-medico');
        Route::post('/turnos/editor/aprobar-cambio/{sol}',  [TurnoEditorController::class, 'aprobarCambio'])   ->name('turno-editor.aprobar-cambio');

        // Secuencias UCI
        Route::get('/secuencias',                              [SecuenciaUciController::class, 'index'])          ->name('secuencias.index');
        Route::post('/secuencias',                             [SecuenciaUciController::class, 'store'])           ->name('secuencias.store');
        Route::get('/secuencias/cargar-excel',                 [SecuenciaUciController::class, 'cargarExcelForm']) ->name('secuencias.cargar-excel');
        Route::post('/secuencias/cargar-excel',                [SecuenciaUciController::class, 'parsearExcel'])    ->name('secuencias.parsear-excel');
        Route::post('/secuencias/importar-calendario',         [SecuenciaUciController::class, 'importarCalendario'])->name('secuencias.importar-calendario');
        Route::post('/secuencias/{secuencia}/aplicar-meses',   [SecuenciaUciController::class, 'aplicarMeses'])   ->name('secuencias.aplicar-meses');
        Route::post('/secuencias/{secuencia}/aplicar-mes',    [SecuenciaUciController::class, 'aplicarMes'])      ->name('secuencias.aplicar-mes');
        Route::post('/secuencias/{secuencia}/aplicar-anio',   [SecuenciaUciController::class, 'aplicarAnio'])     ->name('secuencias.aplicar-anio');
        Route::post('/secuencias/{secuencia}/agregar-medico', [SecuenciaUciController::class, 'agregarMedico'])   ->name('secuencias.agregar-medico');
        Route::delete('/secuencias/{secuencia}',              [SecuenciaUciController::class, 'destroy'])          ->name('secuencias.destroy');
        Route::patch('/secuencias/detalle/{detalle}',         [SecuenciaUciController::class, 'actualizarDetalle'])->name('secuencias.detalle.update');
        Route::put('/secuencias/{secuencia}/celda/{medicoId}/{dia}/{semana?}', [SecuenciaUciController::class, 'setCelda'])->name('secuencias.celda.set');

        // Novedades (maestro: registrar no asistencia, actualizar, eliminar)
        Route::post('/novedades/no-asistencia/{turno}',   [NovedadController::class, 'registrarNoAsistencia'])->name('novedades.no-asistencia');
        Route::patch('/novedades/{novedad}',              [NovedadController::class, 'update'])               ->name('novedades.update');
        Route::delete('/novedades/{novedad}',             [NovedadController::class, 'destroy'])              ->name('novedades.destroy');

        // Consolidado / Excel
        Route::get('/consolidado',              [ConsolidadoController::class, 'index'])              ->name('consolidado.index');
        Route::get('/consolidado/anual',        [ConsolidadoController::class, 'anual'])              ->name('consolidado.anual');
        Route::get('/consolidado/excel',        [ConsolidadoController::class, 'descargarConsolidado'])->name('consolidado.excel');
        Route::get('/consolidado/cuadro-excel', [ConsolidadoController::class, 'descargarCuadro'])    ->name('consolidado.cuadro-excel');

        // Aprobar/rechazar cambios (maestro)
        Route::post('/cambios/aprobar/{solicitud}',  [MedicoPortalController::class, 'aprobarCambio']) ->name('cambios.aprobar');
        Route::post('/cambios/rechazar/{solicitud}', [MedicoPortalController::class, 'rechazarCambio'])->name('cambios.rechazar');

        // Formato inicial
        Route::get('/formato-inicial',             [FormatoInicialController::class, 'index'])         ->name('formato-inicial.index');
        Route::post('/formato-inicial/excel',      [FormatoInicialController::class, 'cargarExcel'])   ->name('formato-inicial.excel');
        Route::post('/formato-inicial/manual',     [FormatoInicialController::class, 'guardarManual']) ->name('formato-inicial.manual');
        Route::post('/formato-inicial/repetir',    [FormatoInicialController::class, 'repetirAnio'])   ->name('formato-inicial.repetir');
        Route::delete('/formato-inicial/{plantilla}',[FormatoInicialController::class,'destroyPlantilla'])->name('formato-inicial.destroy');

        // Semanas molde
        Route::resource('semanas-molde', SemanaMoldeController::class)->names('semanas-molde');
        Route::post('/semanas-molde/{semanaMolde}/aplicar', [SemanaMoldeController::class, 'aplicar'])->name('semanas-molde.aplicar');

        // Ausencias
        Route::resource('ausencias', AusenciaController::class)->names('ausencias');
        Route::patch('/ausencias/{ausencia}/aprobar', [AusenciaController::class, 'aprobar'])->name('ausencias.aprobar');
        Route::patch('/ausencias/{ausencia}/rechazar',[AusenciaController::class, 'rechazar'])->name('ausencias.rechazar');

        // Cambios de turno: solo maestro puede aprobar o rechazar definitivamente
        Route::patch('/cambios-turno/{cambio}/aprobar',  [CambioTurnoController::class, 'aprobar']) ->name('cambios-turno.aprobar');
        Route::patch('/cambios-turno/{cambio}/rechazar', [CambioTurnoController::class, 'rechazar'])->name('cambios-turno.rechazar');

        // Alertas
        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::patch('/alertas/{alerta}/estado', [AlertaController::class, 'cambiarEstado'])->name('alertas.estado');
        Route::delete('/alertas/{alerta}',       [AlertaController::class, 'destroy'])      ->name('alertas.destroy');
        Route::post('/alertas/ejecutar/{archivoId}', [AlertaController::class, 'ejecutarValidacion'])->name('alertas.ejecutar');

        // Reportes
        Route::get('/reportes',                  [ReporteController::class, 'index'])           ->name('reportes.index');
        Route::post('/reportes/excel',           [ReporteController::class, 'exportarExcel'])   ->name('reportes.excel');
        Route::post('/reportes/pdf',             [ReporteController::class, 'exportarPdf'])     ->name('reportes.pdf');
        Route::post('/reportes/medico/excel',    [ReporteController::class, 'exportarMedicoExcel'])->name('reportes.medico.excel');
        Route::post('/reportes/medico/pdf',      [ReporteController::class, 'exportarMedicoPdf'])  ->name('reportes.medico.pdf');

        // Burnout (solo maestro: panel admin)
        Route::get('/burnout',                               [BurnoutController::class, 'index'])            ->name('burnout.index');
        Route::get('/burnout/preguntas',                     [BurnoutController::class, 'preguntas'])        ->name('burnout.preguntas');
        Route::patch('/burnout/preguntas/{pregunta}',        [BurnoutController::class, 'actualizarPregunta'])->name('burnout.pregunta.update');
        Route::post('/burnout/configurar',                   [BurnoutController::class, 'configurar'])       ->name('burnout.configurar');
        Route::post('/burnout/toggle/{encuesta}',            [BurnoutController::class, 'toggleEncuesta'])   ->name('burnout.toggle');
        Route::get('/burnout/exportar',                      [BurnoutController::class, 'exportarExcel'])    ->name('burnout.exportar');
        Route::post('/burnout/alertas/{alerta}/atender',     [BurnoutController::class, 'atenderAlerta'])    ->name('burnout.alertas.atender');

        // Configuración
        Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        Route::put('/configuracion/cobertura/{uci}', [ConfiguracionController::class, 'actualizarCobertura'])->name('configuracion.cobertura');

        // Médicos (maestro) — rutas específicas ANTES del wildcard {medico}
        Route::get('/medicos',                  [MedicoController::class, 'index'])->name('medicos.index');
        Route::get('/medicos/duplicados',        [MedicoDuplicadoController::class, 'index'])        ->name('medicos.duplicados.index');
        Route::post('/medicos/duplicados/fusionar',      [MedicoDuplicadoController::class, 'fusionar'])     ->name('medicos.duplicados.fusionar');
        Route::post('/medicos/duplicados/fusionar-todos',[MedicoDuplicadoController::class, 'fusionarTodos'])->name('medicos.duplicados.fusionar-todos');
        Route::get('/medicos/{medico}',         [MedicoController::class, 'show']) ->name('medicos.show');

        // Usuarios
        Route::get('/usuarios',                       [AuthController::class, 'usuarios'])           ->name('usuarios.index');
        Route::post('/usuarios',                      [AuthController::class, 'crearUsuario'])        ->name('usuarios.crear');
        Route::post('/usuarios/medicos-masivo',       [AuthController::class, 'crearUsuariosMedicos'])->name('usuarios.medicos-masivo');
        Route::delete('/usuarios/{usuario}',          [AuthController::class, 'eliminarUsuario'])     ->name('usuarios.eliminar');
        Route::patch('/usuarios/{usuario}/password',  [AuthController::class, 'cambiarPassword'])     ->name('usuarios.password');
        Route::patch('/usuarios/{usuario}/toggle',    [AuthController::class, 'toggleUsuario'])       ->name('usuarios.toggle');

        // Historial de ediciones (solo maestro)
        Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');
    });
});
