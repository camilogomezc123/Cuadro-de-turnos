<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchivoCargado;
use App\Models\TurnoMedico;
use App\Models\Uci;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class TurnosController extends Controller
{
    // MN tiene una interrupción de 13:00 a 19:00.
    private const SEGMENTOS = [
        'M' => [[7, 13]], 'T' => [[13, 19]], 'MT' => [[7, 19]],
        'N' => [[19, 31]], 'TN' => [[13, 31]], 'MTN' => [[7, 31]],
        'MN' => [[7, 13], [19, 31]],
    ];

    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'uci' => ['sometimes', 'required', 'string', 'max:100'],
        ]);
        $day = isset($data['fecha'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $data['fecha'], 'America/Bogota')
            : CarbonImmutable::now('America/Bogota')->startOfDay();
        $end = $day->addDay();
        $previous = $day->subDay();
        $ucis = Uci::where('activa', true)
            ->when(isset($data['uci']), fn ($q) => $q->where('codigo', $data['uci']))
            ->orderBy('nombre')->get();
        if (isset($data['uci']) && $ucis->isEmpty()) {
            return response()->json(['message' => 'UCI activa no encontrada.'], 404);
        }

        // Última programación procesada de cada mes; nunca mezclar versiones.
        $files = [];
        foreach ([$previous, $day] as $date) {
            $month = $date->format('Y-m');
            if (!array_key_exists($month, $files)) {
                $files[$month] = ArchivoCargado::where('procesado', true)
                    ->where('mes', $date->month)->where('anio', $date->year)
                    ->orderByDesc('id')->value('id');
            }
        }
        $rows = TurnoMedico::with('medico:id,nombre,apellido')
            ->whereIn('archivo_id', array_filter($files))
            ->whereIn('uci_id', $ucis->modelKeys())
            ->whereBetween('fecha', [$previous->toDateString(), $day->toDateString()])
            ->whereIn('codigo_turno', array_keys(self::SEGMENTOS))
            ->where('fue_laborado', true)
            ->whereNotIn('estado_turno', ['no_asistido', 'cancelado', 'descubierto', 'reemplazado'])
            ->orderBy('fecha')->orderBy('medico_id')->get();
        $groups = [];
        foreach ($rows as $row) {
            if (!$row->medico) continue;
            $base = CarbonImmutable::parse($row->fecha->toDateString(), 'America/Bogota')->startOfDay();
            // Cada fila debe pertenecer a la versión elegida de su propio mes.
            if ((int) $row->archivo_id !== (int) ($files[$base->format('Y-m')] ?? 0)) continue;
            foreach (self::SEGMENTOS[$row->codigo_turno] as [$from, $to]) {
                $start = $base->addHours($from);
                $finish = $base->addHours($to);
                if ($start >= $end || $finish <= $day) continue;
                $groups[$row->uci_id][] = [
                    'medico' => $row->medico->nombre_completo,
                    'codigo_turno' => $row->codigo_turno,
                    'fecha_programacion' => $base->toDateString(),
                    'inicio' => $start->toIso8601String(),
                    'fin' => $finish->toIso8601String(),
                    'estado' => $row->estado_turno,
                ];
            }
        }

        return response()->json([
            'fecha' => $day->toDateString(),
            'zona_horaria' => 'America/Bogota',
            'consultado_en' => CarbonImmutable::now('America/Bogota')->toIso8601String(),
            'programacion_cargada' => (bool) $files[$day->format('Y-m')],
            'programacion_dia_anterior_cargada' => (bool) $files[$previous->format('Y-m')],
            'criterio' => 'Intervalos que se superponen con el día civil (00:00 a 24:00); incluye noche del día anterior. No confirma asistencia en tiempo real.',
            'ucis' => $ucis->map(function ($uci) use ($groups) {
                $shifts = collect($groups[$uci->id] ?? [])->sortBy('inicio')->values();
                return [
                    'codigo' => $uci->codigo, 'nombre' => $uci->nombre,
                    'estado' => $shifts->isEmpty() ? 'sin_turnos_registrados' : 'con_turnos',
                    'turnos' => $shifts,
                ];
            }),
        ]);
    }
}
