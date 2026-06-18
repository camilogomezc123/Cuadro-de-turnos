<?php

namespace App\Http\Controllers;

use App\Models\TurnoMedico;
use App\Models\Uci;
use App\Models\ArchivoCargado;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeTurnoAhoraController extends Controller
{
    // Rangos de turno en minutos desde medianoche
    const RANGOS = [
        'M'   => ['ini' => 420,  'fin' => 780,  'label' => '07:00–13:00'],
        'T'   => ['ini' => 780,  'fin' => 1140, 'label' => '13:00–19:00'],
        'MT'  => ['ini' => 420,  'fin' => 1140, 'label' => '07:00–19:00'],
        'N'   => ['ini' => 1140, 'fin' => 1920, 'label' => '19:00–07:00'],
        'MTN' => ['ini' => 420,  'fin' => 1920, 'label' => '07:00–07:00 (+1)'],
        'MN'  => ['ini' => 420,  'fin' => 1920, 'label' => '07:00–07:00 (+1)'],
    ];

    public function index(Request $request)
    {
        $ahora   = Carbon::now();
        $minutos = $ahora->hour * 60 + $ahora->minute;

        // Semana solicitada (por defecto: semana actual)
        $fechaRef = $request->filled('fecha')
            ? Carbon::parse($request->fecha)
            : $ahora->copy();

        $inicioSemana = $fechaRef->copy()->startOfWeek(Carbon::MONDAY);
        $finSemana    = $fechaRef->copy()->endOfWeek(Carbon::SUNDAY);

        // Días de la semana con fechas
        $diasSemana = [];
        for ($d = 0; $d < 7; $d++) {
            $dia = $inicioSemana->copy()->addDays($d);
            $diasSemana[] = [
                'fecha'       => $dia->toDateString(),
                'label_corto' => $dia->locale('es')->isoFormat('ddd D'),   // lun 16
                'label_dia'   => $dia->locale('es')->isoFormat('dddd'),     // lunes
                'es_hoy'      => $dia->isToday(),
                'es_finde'    => $dia->isWeekend(),
                'numero'      => $dia->day,
            ];
        }

        // Archivos que cubren esta semana (pueden ser de 2 meses distintos)
        $archivosIds = $this->archivosParaRango($inicioSemana, $finSemana);

        $ucis = Uci::where('activa', true)->orderBy('nombre')->get();

        // ── DATOS POR UCI ────────────────────────────────────────
        // $datosUci[$uciCodigo]['medicos'][$nombre][$fecha] = codigo_turno
        $datosUci = [];
        foreach ($ucis as $uci) {
            $datosUci[$uci->codigo] = [
                'uci'     => $uci,
                'medicos' => [],   // nombre => [fecha => codigo]
            ];
        }

        if (!empty($archivosIds)) {
            $fechas = array_column($diasSemana, 'fecha');

            $turnos = TurnoMedico::with('medico', 'uci')
                ->whereIn('archivo_id', $archivosIds)
                ->whereIn('fecha', $fechas)
                ->whereNotIn('codigo_turno', ['', 'LIBRE'])
                ->get();

            foreach ($turnos as $t) {
                $cod = $t->uci->codigo ?? null;
                if (!$cod || !isset($datosUci[$cod])) continue;

                $nombre = $t->medico->nombre ?? '?';
                $fecha  = $t->fecha->toDateString();
                $codigo = $t->codigo_turno;

                if (!isset($datosUci[$cod]['medicos'][$nombre])) {
                    $datosUci[$cod]['medicos'][$nombre] = [];
                }
                $datosUci[$cod]['medicos'][$nombre][$fecha] = $codigo;
            }

            // Ordenar médicos alfabéticamente
            foreach ($datosUci as &$datos) {
                ksort($datos['medicos']);
            }
            unset($datos);
        }

        // ── TURNO ACTIVO HOY ─────────────────────────────────────
        $hoy          = $ahora->toDateString();
        $turnoActivo  = $this->labelTurnoActivo($minutos);
        $resumenHoy   = [];

        foreach ($ucis as $uci) {
            $cod      = $uci->codigo;
            $activos  = [];
            $proximos = [];

            if (isset($datosUci[$cod]['medicos'])) {
                foreach ($datosUci[$cod]['medicos'] as $nombre => $diasMedico) {
                    $codigoHoy = $diasMedico[$hoy] ?? '';
                    if (empty($codigoHoy)) continue;

                    $estaActivo = $this->estaActivo($codigoHoy, $minutos);
                    $rango = self::RANGOS[$codigoHoy] ?? null;

                    if ($estaActivo) {
                        $activos[] = ['medico' => $nombre, 'codigo' => $codigoHoy, 'label' => $rango['label'] ?? ''];
                    } else {
                        $proximos[] = ['medico' => $nombre, 'codigo' => $codigoHoy, 'label' => $rango['label'] ?? ''];
                    }
                }
            }

            $resumenHoy[$cod] = [
                'uci'      => $uci,
                'activos'  => $activos,
                'proximos' => $proximos,
            ];
        }

        return view('de-turno-ahora.index', [
            'diasSemana'    => $diasSemana,
            'inicioSemana'  => $inicioSemana,
            'finSemana'     => $finSemana,
            'semanaAnterior'=> $inicioSemana->copy()->subWeek()->toDateString(),
            'semanaSiguiente'=> $inicioSemana->copy()->addWeek()->toDateString(),
            'datosUci'      => $datosUci,
            'ucis'          => $ucis,
            'resumenHoy'    => $resumenHoy,
            'ahora'         => $ahora,
            'turnoActivo'   => $turnoActivo,
            'hayDatos'      => !empty($archivosIds),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function archivosParaRango(Carbon $inicio, Carbon $fin): array
    {
        $ids = [];
        $cur = $inicio->copy()->startOfMonth();

        while ($cur->lte($fin)) {
            $archivo = ArchivoCargado::where('procesado', true)
                ->where('mes', $cur->month)
                ->where('anio', $cur->year)
                ->value('id');

            if ($archivo) $ids[] = $archivo;
            $cur->addMonth();
        }

        return array_unique($ids);
    }

    private function estaActivo(string $codigo, int $minutos): bool
    {
        $r = self::RANGOS[$codigo] ?? null;
        if (!$r) return false;

        if (in_array($codigo, ['N', 'MTN', 'MN'])) {
            return $minutos >= $r['ini'] || $minutos < 420;
        }
        return $minutos >= $r['ini'] && $minutos < $r['fin'];
    }

    private function labelTurnoActivo(int $min): string
    {
        if ($min >= 420  && $min < 780)  return 'Mañana · 07:00–13:00';
        if ($min >= 780  && $min < 1140) return 'Tarde · 13:00–19:00';
        return 'Noche · 19:00–07:00';
    }
}
