<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\ArchivoCargado;
use Carbon\Carbon;

class DeTurnoAhoraController extends Controller
{
    public function index()
    {
        $ahora    = Carbon::now();
        $hoy      = $ahora->toDateString();
        $horaAhora= $ahora->hour * 60 + $ahora->minute; // minutos desde medianoche
        $ucis     = Uci::where('activa', true)->orderBy('nombre')->get();

        // Buscar el archivo del mes actual (o el más reciente)
        $archivo = ArchivoCargado::where('procesado', true)
            ->where('mes', $ahora->month)
            ->where('anio', $ahora->year)
            ->first()
            ?? ArchivoCargado::where('procesado', true)->orderByDesc('anio')->orderByDesc('mes')->first();

        $tarjetas = [];

        if ($archivo) {
            foreach ($ucis as $uci) {
                // Turnos de hoy y ayer en esta UCI (para cubrir noches que empezaron ayer)
                $turnosHoy  = TurnoMedico::with('medico')
                    ->where('archivo_id', $archivo->id)
                    ->where('uci_id', $uci->id)
                    ->where('fecha', $hoy)
                    ->whereNotIn('codigo_turno', ['', 'LIBRE'])
                    ->where('horas_total', '>', 0)
                    ->orderBy('codigo_turno')
                    ->get();

                $turnosAyer = TurnoMedico::with('medico')
                    ->where('archivo_id', $archivo->id)
                    ->where('uci_id', $uci->id)
                    ->where('fecha', $ahora->copy()->subDay()->toDateString())
                    ->whereIn('codigo_turno', ['N', 'MTN', 'MN'])
                    ->get();

                // Determinar qué turno está activo AHORA
                // M:   07:00–13:00  (420–780 min)
                // T:   13:00–19:00  (780–1140 min)
                // MT:  07:00–19:00  (420–1140 min)
                // N:   19:00–07:00  (1140+ o 0–420 del día siguiente)
                // MTN: 07:00–07:00 siguiente (420 en adelante, todo el día)
                $ahora_turno = $this->calcularTurnoActivo($horaAhora);

                $turnosActivos   = [];
                $turnosProximos  = [];

                // Noches de ayer que siguen activas (hasta las 07:00)
                if ($horaAhora < 420) { // antes de 7am
                    foreach ($turnosAyer as $t) {
                        $turnosActivos[] = [
                            'medico'      => $t->medico->nombre,
                            'codigo'      => $t->codigo_turno,
                            'hora_inicio' => '19:00',
                            'hora_fin'    => '07:00',
                            'horas'       => $t->horas_total,
                        ];
                    }
                }

                foreach ($turnosHoy as $t) {
                    $info = $this->infoTurnoActivo($t->codigo_turno, $horaAhora);
                    if ($info['activo']) {
                        $turnosActivos[] = [
                            'medico'      => $t->medico->nombre,
                            'codigo'      => $t->codigo_turno,
                            'hora_inicio' => $info['hora_inicio'],
                            'hora_fin'    => $info['hora_fin'],
                            'horas'       => $t->horas_total,
                        ];
                    } else {
                        $turnosProximos[] = [
                            'medico'      => $t->medico->nombre,
                            'codigo'      => $t->codigo_turno,
                            'hora_inicio' => $info['hora_inicio'],
                        ];
                    }
                }

                $tarjetas[] = [
                    'uci'             => $uci,
                    'turno_actual'    => $ahora_turno,
                    'activos'         => $turnosActivos,
                    'proximos'        => $turnosProximos,
                    'cobertura_ok'    => !empty($turnosActivos),
                ];
            }
        }

        return view('de-turno-ahora.index', compact(
            'tarjetas', 'ahora', 'ucis', 'archivo'
        ));
    }

    private function calcularTurnoActivo(int $minutos): string
    {
        if ($minutos >= 420 && $minutos < 780)  return 'Mañana (07:00–13:00)';
        if ($minutos >= 780 && $minutos < 1140) return 'Tarde (13:00–19:00)';
        return 'Noche (19:00–07:00)';
    }

    private function infoTurnoActivo(string $codigo, int $minutos): array
    {
        $rangos = [
            'M'   => ['inicio' => 420,  'fin' => 780,  'h_inicio' => '07:00', 'h_fin' => '13:00'],
            'T'   => ['inicio' => 780,  'fin' => 1140, 'h_inicio' => '13:00', 'h_fin' => '19:00'],
            'MT'  => ['inicio' => 420,  'fin' => 1140, 'h_inicio' => '07:00', 'h_fin' => '19:00'],
            'N'   => ['inicio' => 1140, 'fin' => 1920, 'h_inicio' => '19:00', 'h_fin' => '07:00'],
            'MTN' => ['inicio' => 420,  'fin' => 1920, 'h_inicio' => '07:00', 'h_fin' => '07:00'],
            'MN'  => ['inicio' => 420,  'fin' => 780,  'h_inicio' => '07:00', 'h_fin' => '07:00'],
        ];

        $r = $rangos[$codigo] ?? null;
        if (!$r) return ['activo' => false, 'hora_inicio' => '--:--', 'hora_fin' => '--:--'];

        $activo = $minutos >= $r['inicio'] && $minutos < $r['fin'];
        // Para N: también activo si es antes de 7am (minutos < 420 del día siguiente)
        if ($codigo === 'N' && $minutos < 420) $activo = true;
        if ($codigo === 'MTN' && $minutos < 420) $activo = true;

        return ['activo' => $activo, 'hora_inicio' => $r['h_inicio'], 'hora_fin' => $r['h_fin']];
    }
}
