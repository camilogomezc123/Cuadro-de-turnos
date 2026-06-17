<?php

namespace App\Http\Controllers;

use App\Models\ArchivoCargado;
use App\Models\Medico;
use App\Models\Plantilla;
use App\Models\Uci;
use App\Services\ExcelParserService;
use App\Services\PlantillaService;
use App\Services\TurnoCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FormatoInicialController extends Controller
{
    public function __construct(
        private PlantillaService       $plantillaService,
        private ExcelParserService     $parser,
        private TurnoCalculatorService $calculator,
    ) {}

    public function index()
    {
        $ucis      = Uci::orderBy('nombre')->get();
        $archivos  = ArchivoCargado::where('procesado', true)
                         ->orderByDesc('anio')->orderByDesc('mes')->get();
        $plantillas = Plantilla::with('uci')->orderByDesc('created_at')->get();
        $medicos   = Medico::orderBy('nombre')->get();

        return view('formato-inicial.index', compact('ucis', 'archivos', 'plantillas', 'medicos'));
    }

    /**
     * Cargar Excel como base de una plantilla.
     */
    public function cargarExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:20480',
            'mes'     => 'required|integer|between:1,12',
            'anio'    => 'required|integer|between:2020,2035',
            'nombre'  => 'required|string|max:100',
        ]);

        $mes  = (int)$request->mes;
        $anio = (int)$request->anio;
        $file = $request->file('archivo');

        $ruta    = $file->storeAs('uploads', "plantilla_{$mes}_{$anio}_{$file->getClientOriginalName()}");
        $rutaReal = Storage::path($ruta);

        $resultado = $this->parser->parsear($rutaReal, $mes, $anio);

        if (!empty($resultado['errores'])) {
            return back()->with('error', implode(' | ', $resultado['errores']));
        }
        if (empty($resultado['ucis'])) {
            return back()->with('error', 'No se reconocieron UCIs en el archivo Excel.');
        }

        // Buscar o crear ArchivoCargado base
        $archivoBase = ArchivoCargado::where('mes', $mes)->where('anio', $anio)->first();
        if (!$archivoBase) {
            $archivoBase = ArchivoCargado::create([
                'nombre_archivo' => $file->getClientOriginalName(),
                'ruta'           => $ruta,
                'mes'            => $mes,
                'anio'           => $anio,
                'procesado'      => false,
            ]);
            $this->calculator->procesarYPersistir($archivoBase, $resultado['ucis'], $mes, $anio);
        }

        Plantilla::create([
            'nombre'          => $request->nombre,
            'descripcion'     => "Cargado desde Excel: {$file->getClientOriginalName()}",
            'archivo_base_id' => $archivoBase->id,
            'mes_base'        => $mes,
            'anio_base'       => $anio,
            'activa'          => true,
        ]);

        return back()->with('success', "Plantilla '{$request->nombre}' creada correctamente.");
    }

    /**
     * Guardar cuadro creado de forma manual como plantilla base.
     */
    public function guardarManual(Request $request)
    {
        $request->validate([
            'nombre'  => 'required|string|max:100',
            'uci_id'  => 'required|integer|exists:ucis,id',
            'mes'     => 'required|integer|between:1,12',
            'anio'    => 'required|integer|between:2020,2035',
            'turnos'  => 'required|array',
        ]);

        $mes  = (int)$request->mes;
        $anio = (int)$request->anio;

        // turnos[medicoId][dia] = codigo
        $turnosRaw = $request->input('turnos', []);

        $archivo = $this->plantillaService->crearDesdeManual(
            (int)$request->uci_id, $mes, $anio, $turnosRaw
        );

        Plantilla::create([
            'nombre'          => $request->nombre,
            'descripcion'     => 'Creado manualmente',
            'uci_id'          => $request->uci_id,
            'archivo_base_id' => $archivo->id,
            'mes_base'        => $mes,
            'anio_base'       => $anio,
            'activa'          => true,
        ]);

        return back()->with('success', "Plantilla '{$request->nombre}' guardada. {$archivo->total_turnos} turnos registrados.");
    }

    /**
     * Repetir la secuencia de una plantilla durante todo el año destino.
     */
    public function repetirAnio(Request $request)
    {
        $request->validate([
            'plantilla_id' => 'required|integer|exists:plantillas,id',
            'anio_destino' => 'required|integer|between:2024,2035',
        ]);

        $plantilla   = Plantilla::findOrFail($request->plantilla_id);
        $anioDestino = (int)$request->anio_destino;

        if (!$plantilla->archivo_base_id) {
            return back()->with('error', 'La plantilla no tiene archivo base definido.');
        }

        $archivosCreados = $this->plantillaService->repetirAnio(
            $plantilla->archivo_base_id, $anioDestino
        );

        $plantilla->update([
            'anios_generados' => array_merge(
                (array)$plantilla->anios_generados, [$anioDestino]
            ),
        ]);

        $count = count($archivosCreados);
        return back()->with('success',
            "Se generaron {$count} meses para el año {$anioDestino} con el patrón semanal de la plantilla '{$plantilla->nombre}'."
        );
    }

    public function destroyPlantilla(Plantilla $plantilla)
    {
        $plantilla->delete();
        return back()->with('success', 'Plantilla eliminada.');
    }
}
