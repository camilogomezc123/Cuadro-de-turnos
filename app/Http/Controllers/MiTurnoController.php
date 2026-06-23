<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\ArchivoCargado;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MiTurnoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (!$user->medico_id) {
            return view('mi-turno.index', [
                'turnos'    => collect(),
                'archivos'  => collect(),
                'archivoId' => null,
                'archivo'   => null,
                'resumen'   => [],
            ]);
        }

        $archivos  = ArchivoCargado::orderByDesc('anio')->orderByDesc('mes')->get();
        $archivoId = (int)($request->archivo_id ?? $archivos->first()?->id ?? 0);
        $archivo   = $archivos->firstWhere('id', $archivoId);

        $turnos = collect();
        $resumen = ['total_h' => 0, 'diurnas' => 0, 'nocturnas' => 0, 'por_codigo' => []];

        if ($archivo) {
            $turnos = TurnoMedico::where('medico_id', $user->medico_id)
                ->where('archivo_id', $archivoId)
                ->orderBy('fecha')
                ->with('uci')
                ->get();

            foreach ($turnos as $t) {
                $resumen['total_h']   += $t->horas_total    ?? 0;
                $resumen['diurnas']   += $t->horas_diurnas  ?? 0;
                $resumen['nocturnas'] += $t->horas_nocturnas ?? 0;
                $codigo = $t->codigo_turno ?? 'LIBRE';
                $resumen['por_codigo'][$codigo] = ($resumen['por_codigo'][$codigo] ?? 0) + 1;
            }
        }

        return view('mi-turno.index', compact('turnos','archivos','archivoId','archivo','resumen'));
    }
}