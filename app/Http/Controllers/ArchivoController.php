<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\AlertaTurno;
use App\Services\ExcelParserService;
use App\Services\TurnoCalculatorService;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArchivoController extends Controller
{
    public function __construct(
        private ExcelParserService     $parser,
        private TurnoCalculatorService $calculator,
        private AlertService           $alertService,
    ) {}

    public function index()
    {
        $archivos = ArchivoCargado::orderByDesc('anio')->orderByDesc('mes')->get();
        return view('archivos.index', compact('archivos'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:20480',
            'mes'     => 'nullable|integer|between:1,12',
            'anio'    => 'nullable|integer|between:2020,2035',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo Excel.',
            'archivo.mimes'    => 'Solo se permiten archivos .xlsx o .xls.',
        ]);

        $mesFiltro  = $request->mes  ? (int)$request->mes  : null;
        $anioFiltro = $request->anio ? (int)$request->anio : null;

        $file    = $request->file('archivo');
        $nombre  = $file->getClientOriginalName();
        $ruta    = $file->storeAs('uploads', "turnos_" . time() . "_{$nombre}");
        $rutaReal = Storage::path($ruta);

        try {
            $resultado = $this->parser->parsear($rutaReal, $mesFiltro, $anioFiltro);
        } catch (\Exception $e) {
            Storage::delete($ruta);
            return back()->with('error', 'Error al leer el archivo: ' . $e->getMessage());
        }

        if (!empty($resultado['errores'])) {
            Storage::delete($ruta);
            return back()->with('error', implode(' | ', $resultado['errores']));
        }

        // ── MODO MULTI-MES (hojas mensuales) ──────────────────────
        if ($resultado['modo'] === 'multi_mes') {
            return $this->procesarMultiMes($resultado, $nombre, $ruta, $request);
        }

        // ── MODO ANTIGUO (hoja por UCI) ───────────────────────────
        return $this->procesarHojaUci($resultado, $nombre, $ruta, $mesFiltro, $anioFiltro, $request);
    }

    // ── Multi-mes ─────────────────────────────────────────────────

    private function procesarMultiMes(array $resultado, string $nombre, string $ruta, Request $request): \Illuminate\Http\RedirectResponse
    {
        if (empty($resultado['meses'])) {
            $msg = 'No se encontraron hojas mensuales válidas en el archivo.';
            if (!empty($resultado['advertencias'])) {
                $msg .= ' Advertencias: ' . implode(' | ', array_slice($resultado['advertencias'], 0, 3));
            }
            return back()->with('error', $msg);
        }

        $resumen       = [];
        $totalAlertas  = 0;

        foreach ($resultado['meses'] as $clave => $datosMes) {
            $mes  = $datosMes['mes'];
            $anio = $datosMes['anio'];
            $ucis = $datosMes['ucis'];

            if (empty($ucis)) continue;

            // Sobreescribir si existe
            $existente = ArchivoCargado::where('mes', $mes)->where('anio', $anio)->first();
            if ($existente) {
                if (!$request->boolean('sobreescribir')) {
                    $resumen[] = "⚠ {$datosMes['nombre_hoja']}: ya existe — omitido (active 'Sobreescribir' para reemplazar).";
                    continue;
                }
                $existente->turnoMedicos()->delete();
                $existente->indicadorMedicos()->delete();
                $existente->indicadorUcis()->delete();
                $existente->delete();
            }

            $archivo = ArchivoCargado::create([
                'nombre_archivo' => "{$datosMes['nombre_hoja']} — {$nombre}",
                'ruta'           => $ruta,
                'mes'            => $mes,
                'anio'           => $anio,
                'procesado'      => false,
            ]);

            try {
                $this->calculator->procesarYPersistir($archivo, $ucis, $mes, $anio);

                // Alertas de MTN y códigos no oficiales
                $nAlertas = $this->alertService->validarArchivo($archivo->id);

                // Crear alertas específicas desde el parser
                $this->crearAlertasParser($archivo->id, $resultado);

                $totalAlertas += $nAlertas;

                $resumen[] = "✓ {$datosMes['nombre_hoja']}: {$archivo->total_medicos} médicos, {$archivo->total_turnos} turnos.";
            } catch (\Exception $e) {
                $archivo->update(['errores' => [$e->getMessage()]]);
                $resumen[] = "✗ {$datosMes['nombre_hoja']}: error — {$e->getMessage()}";
            }
        }

        $msg = implode("\n", $resumen);
        if (!empty($resultado['advertencias'])) {
            $msg .= "\n" . count($resultado['advertencias']) . ' advertencia(s) de importación.';
        }
        if ($totalAlertas > 0) {
            $msg .= "\nSe generaron {$totalAlertas} alerta(s). Revise la sección Alertas.";
        }
        if (!empty($resultado['alertas_mtn'])) {
            $msg .= "\n⚠ " . count($resultado['alertas_mtn']) . ' turno(s) MTN en día hábil detectado(s).';
        }
        if (!empty($resultado['alertas_no_oficial'])) {
            $msg .= "\n⚠ " . count($resultado['alertas_no_oficial']) . ' código(s) no oficial(es) (MN) detectado(s).';
        }

        $tipo = str_contains($msg, '✓') ? 'success' : 'warning';
        return redirect()->route('archivos.index')->with($tipo, $msg);
    }

    // ── Formato antiguo ──────────────────────────────────────────

    private function procesarHojaUci(
        array $resultado, string $nombre, string $ruta,
        ?int $mesFiltro, ?int $anioFiltro, Request $request
    ): \Illuminate\Http\RedirectResponse {
        if (empty($resultado['ucis'])) {
            $msg = 'No se encontraron UCIs reconocidas. '
                 . 'El archivo usa el formato antiguo (hoja por UCI). '
                 . 'Verifique que el nombre de la hoja o la celda A1 contengan el nombre de la UCI.';
            return back()->with('error', $msg);
        }

        if (!$mesFiltro || !$anioFiltro) {
            return back()->with('error', 'Para el formato antiguo (una hoja por UCI) debe indicar Mes y Año.');
        }

        $existente = ArchivoCargado::where('mes', $mesFiltro)->where('anio', $anioFiltro)->first();
        if ($existente && !$request->boolean('sobreescribir')) {
            return back()->with('error', "Ya existe un registro para {$mesFiltro}/{$anioFiltro}. Active 'Sobreescribir'.");
        }
        if ($existente) {
            $existente->turnoMedicos()->delete();
            $existente->indicadorMedicos()->delete();
            $existente->indicadorUcis()->delete();
            $existente->delete();
        }

        $archivo = ArchivoCargado::create([
            'nombre_archivo' => $nombre,
            'ruta'           => $ruta,
            'mes'            => $mesFiltro,
            'anio'           => $anioFiltro,
            'procesado'      => false,
        ]);

        $this->calculator->procesarYPersistir($archivo, $resultado['ucis'], $mesFiltro, $anioFiltro);
        $nAlertas = $this->alertService->validarArchivo($archivo->id);
        $this->crearAlertasParser($archivo->id, $resultado);

        $msg = "Archivo procesado: {$archivo->total_medicos} médicos, {$archivo->total_turnos} turnos.";
        if ($nAlertas > 0) $msg .= " {$nAlertas} alerta(s) generada(s).";

        return redirect()->route('archivos.index')->with('success', $msg);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function crearAlertasParser(int $archivoId, array $resultado): void
    {
        foreach ($resultado['alertas_mtn'] ?? [] as $a) {
            AlertaTurno::create([
                'archivo_id' => $archivoId,
                'fecha'      => $a['fecha'],
                'tipo'       => 'MTN_DIA_HABIL',
                'prioridad'  => 'alta',
                'mensaje'    => $a['mensaje'],
                'estado'     => 'abierta',
            ]);
        }

        foreach ($resultado['alertas_no_oficial'] ?? [] as $a) {
            AlertaTurno::create([
                'archivo_id' => $archivoId,
                'fecha'      => $a['fecha'],
                'tipo'       => 'CODIGO_NO_PARAMETRIZADO',
                'prioridad'  => 'media',
                'mensaje'    => $a['mensaje'],
                'estado'     => 'abierta',
            ]);
        }
    }

    public function destroy(ArchivoCargado $archivo)
    {
        Storage::delete($archivo->ruta);
        $archivo->delete();
        return back()->with('success', 'Archivo y datos eliminados correctamente.');
    }
}
