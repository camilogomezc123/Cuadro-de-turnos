@extends('layouts.app')
@section('title', 'Calendario de Turnos')
@section('page-title', 'Calendario de Turnos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Calendario</li>
@endsection

@push('head-styles')
<style>
/* ── Variables de turno ─────────────────────────── */
:root {
    --t-M:   #1565C0; --t-M-bg: #E3F2FD;
    --t-T:   #2E7D32; --t-T-bg: #E8F5E9;
    --t-MT:  #00838F; --t-MT-bg: #E0F7FA;
    --t-N:   #4A148C; --t-N-bg: #EDE7F6;
    --t-MTN: #880E4F; --t-MTN-bg: #FCE4EC;
    --t-MN:  #E65100; --t-MN-bg: #FFF3E0;
    --t-VAC: #5D4037; --t-VAC-bg: #EFEBE9;
    --t-PER: #37474F; --t-PER-bg: #ECEFF1;
    --t-INC: #BF360C; --t-INC-bg: #FBE9E7;
}
/* ── Grid general ───────────────────────────────── */
.cal-wrapper { overflow-x: auto; }
.cal-table {
    border-collapse: collapse;
    width: 100%;
    min-width: 900px;
    font-size: 11.5px;
}
.cal-table th, .cal-table td {
    border: 1px solid #e2e8f0;
    padding: 0;
    white-space: nowrap;
}
/* ── Columna nombre médico ──────────────────────── */
.col-nombre {
    position: sticky;
    left: 0; z-index: 2;
    background: #f8fafc;
    padding: 6px 10px;
    font-weight: 600;
    font-size: 12px;
    min-width: 160px;
    border-right: 2px solid #cbd5e1;
    color: #1e293b;
}
.col-nombre-header {
    background: #1e293b;
    color: #fff;
    position: sticky;
    left: 0; z-index: 3;
    padding: 8px 10px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-right: 2px solid #0f172a;
}
/* ── Headers de días ────────────────────────────── */
.th-dia {
    text-align: center;
    width: 34px;
    min-width: 34px;
    padding: 0;
    background: #1e293b;
    color: #94a3b8;
    font-size: 10px;
    text-transform: uppercase;
}
.th-dia .num { font-size: 14px; color: #f1f5f9; font-weight: 700; line-height: 1; display: block; }
.th-dia.finde     { background: #1a3a5c; }
.th-dia.dom       { background: #3b0764; }
.th-dia.hoy       { background: #065f46; }
/* ── Celdas turno ───────────────────────────────── */
.td-turno {
    text-align: center;
    vertical-align: middle;
    height: 38px;
    background: #fff;
}
.td-turno.finde   { background: #f0f9ff; }
.td-turno.dom     { background: #fdf4ff; }
.td-turno.hoy     { background: #f0fdf4; }
/* ── Badges turno ───────────────────────────────── */
.tbadge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 22px;
    border-radius: 5px;
    font-weight: 700; font-size: 9.5px;
    line-height: 1;
}
.tb-M   { color: var(--t-M);   background: var(--t-M-bg);   }
.tb-T   { color: var(--t-T);   background: var(--t-T-bg);   }
.tb-MT  { color: var(--t-MT);  background: var(--t-MT-bg);  }
.tb-N   { color: var(--t-N);   background: var(--t-N-bg);   }
.tb-MTN { color: var(--t-MTN); background: var(--t-MTN-bg); }
.tb-MN  { color: var(--t-MN);  background: var(--t-MN-bg);  }
.tb-VAC { color: var(--t-VAC); background: var(--t-VAC-bg); font-size: 8px; }
.tb-PER { color: var(--t-PER); background: var(--t-PER-bg); font-size: 8px; }
.tb-INC { color: var(--t-INC); background: var(--t-INC-bg); font-size: 8px; }
/* ── Fila alterna ───────────────────────────────── */
.cal-table tbody tr:nth-child(odd) .col-nombre  { background: #f1f5f9; }
.cal-table tbody tr:nth-child(even) .col-nombre { background: #fff; }
/* ── Leyenda ────────────────────────────────────── */
.leyenda { display: flex; gap: 8px; flex-wrap: wrap; }
.leg-item { display: flex; align-items: center; gap: 4px; font-size: 11px; }
/* ── Resumen médico ─────────────────────────────── */
.resumen-bar { height: 6px; border-radius: 4px; background: #e2e8f0; overflow: hidden; }
.resumen-bar-fill { height: 100%; background: #1565C0; border-radius: 4px; }
</style>
@endpush

@section('content')

{{-- ── SELECTOR ──────────────────────────────────────── --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="{{ route('calendario.index') }}" class="row g-2 align-items-end" id="form-calendario">
            <div class="col-sm-4">
                <label class="form-label small fw-semibold mb-1">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" {{ $u->id == $uciId ? 'selected':'' }}>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold mb-1">Mes</label>
                <select name="mes" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($nombresMeses as $n => $label)
                        @if($n > 0)
                        <option value="{{ $n }}" {{ $n == $mes ? 'selected':'' }}>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold mb-1">Año</label>
                <select name="anio" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($anios as $y)
                        <option value="{{ $y }}" {{ $y == $anio ? 'selected':'' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto ms-auto d-flex gap-2 align-items-end">
                <a href="{{ route('calendario.index', ['uci_id'=>$uciId,'mes'=>$prevMes,'anio'=>$prevAnio]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="{{ route('calendario.index', ['uci_id'=>$uciId,'mes'=>$nextMes,'anio'=>$nextAnio]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-chevron-right"></i>
                </a>
                @if(!$medicos->isEmpty())
                <button class="btn btn-success btn-sm" onclick="document.getElementById('form-descargar').submit()">
                    <i class="bi bi-file-excel me-1"></i>Descargar Excel
                </button>
                @endif
                <button class="btn btn-outline-secondary btn-sm d-print-none" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Formulario de descarga SEPARADO (no anidado) --}}
<form id="form-descargar" method="POST" action="{{ route('calendario.descargar') }}" style="display:none">
    @csrf
    <input type="hidden" name="uci_id" value="{{ $uciId }}">
    <input type="hidden" name="mes"    value="{{ $mes }}">
    <input type="hidden" name="anio"   value="{{ $anio }}">
</form>

@if($medicos->isEmpty())
    <div class="panel p-5 text-center">
        <i class="bi bi-calendar3 text-primary" style="font-size:3.5rem;opacity:.3"></i>
        <h5 class="mt-3 text-secondary">Sin datos</h5>
        <p class="text-muted small">Seleccione una UCI y un período que tenga datos cargados.</p>
    </div>
@else

{{-- ── CABECERA DEL MES ──────────────────────────────── --}}
@php
    $diasSemana   = ['L','M','M','J','V','S','D'];
    $hoy          = now()->day;
    $esEsteMes    = (now()->month == $mes && now()->year == $anio);

    // Para cada día: dow info
    $infosDias = [];
    for ($d = 1; $d <= $diasDelMes; $d++) {
        $f   = \Carbon\Carbon::create($anio, $mes, $d);
        $dow = ($f->dayOfWeek === 0) ? 6 : $f->dayOfWeek - 1;
        $infosDias[$d] = [
            'dow'    => $dow,
            'letra'  => $diasSemana[$dow],
            'finde'  => in_array($dow, [5,6]),
            'dom'    => ($dow === 6),
            'hoy'    => ($esEsteMes && $d === $hoy),
        ];
    }

    // Totales por médico
    $totalHoras = [];
    foreach ($medicos as $m) {
        $h = 0;
        $horasMap = ['M'=>6,'T'=>6,'MT'=>12,'N'=>12,'MTN'=>24,'MN'=>18,'VAC'=>0,'PER'=>0,'INC'=>0,''=>0,'LIBRE'=>0];
        for ($d = 1; $d <= $diasDelMes; $d++) {
            $c = $grilla[$m->id][$d] ?? '';
            $h += $horasMap[$c] ?? 0;
        }
        $totalHoras[$m->id] = $h;
    }
@endphp

<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <span class="panel-title">
            <i class="bi bi-calendar-month me-2 text-primary"></i>
            {{ $uci->nombre ?? '' }} &mdash; {{ $nombresMeses[$mes] }} {{ $anio }}
        </span>
        <span class="text-muted small">{{ $medicos->count() }} médicos · {{ $diasDelMes }} días</span>
    </div>

    {{-- LEYENDA --}}
    <div class="panel-body border-bottom py-2">
        <div class="leyenda">
            @foreach(['M'=>'Mañana','T'=>'Tarde','MT'=>'Mañ+Tarde','N'=>'Noche','MTN'=>'MTN (24h)','MN'=>'Mañ+Noche','VAC'=>'Vacaciones','PER'=>'Permiso','INC'=>'Incapacidad'] as $cod => $lab)
            <div class="leg-item">
                <span class="tbadge tb-{{ $cod }}">{{ $cod }}</span>
                <span class="text-muted">{{ $lab }}</span>
            </div>
            @endforeach
            <div class="leg-item ms-3">
                <span class="d-inline-block" style="width:14px;height:14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:3px"></span>
                <span class="text-muted">Sábado</span>
            </div>
            <div class="leg-item">
                <span class="d-inline-block" style="width:14px;height:14px;background:#fdf4ff;border:1px solid #e9d5ff;border-radius:3px"></span>
                <span class="text-muted">Domingo</span>
            </div>
        </div>
    </div>

    <div class="panel-body p-0">
        <div class="cal-wrapper">
            <table class="cal-table">
                <thead>
                    <tr>
                        <th class="col-nombre-header">Médico</th>
                        @for($d = 1; $d <= $diasDelMes; $d++)
                        @php $info = $infosDias[$d]; @endphp
                        <th class="th-dia {{ $info['dom'] ? 'dom' : ($info['finde'] ? 'finde' : '') }} {{ $info['hoy'] ? 'hoy' : '' }}">
                            <span style="font-size:9px;display:block;padding-top:4px;">{{ $info['letra'] }}</span>
                            <span class="num">{{ $d }}</span>
                            <span style="font-size:8px;display:block;padding-bottom:3px;opacity:.6">
                                {{ $info['dom'] ? 'Dom' : ($info['finde'] ? 'Sáb' : '') }}
                            </span>
                        </th>
                        @endfor
                        <th class="th-dia" style="min-width:50px;background:#0f172a;">
                            <span style="font-size:9px;display:block;padding-top:4px;color:#94a3b8">Total</span>
                            <span class="num" style="font-size:11px">Horas</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($medicos as $medico)
                    <tr>
                        <td class="col-nombre">
                            <div>{{ $medico->nombre }}</div>
                            @if($medico->uci?->codigo)
                            <div class="text-muted" style="font-size:10px;font-weight:400">{{ $medico->uci->codigo }}</div>
                            @endif
                        </td>
                        @for($d = 1; $d <= $diasDelMes; $d++)
                        @php
                            $info   = $infosDias[$d];
                            $codigo = $grilla[$medico->id][$d] ?? '';
                        @endphp
                        <td class="td-turno {{ $info['dom'] ? 'dom' : ($info['finde'] ? 'finde' : '') }} {{ $info['hoy'] ? 'hoy' : '' }}">
                            @if($codigo !== '')
                                <span class="tbadge tb-{{ $codigo }}">{{ $codigo }}</span>
                            @else
                                <span style="color:#cbd5e1;font-size:10px">·</span>
                            @endif
                        </td>
                        @endfor
                        <td class="td-turno" style="background:#f8fafc;font-weight:700;font-size:12px;color:#1565C0">
                            {{ $totalHoras[$medico->id] }}h
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── RESUMEN MÉDICOS ───────────────────────────────── --}}
<div class="row g-3 mt-3">
    @foreach($medicos as $medico)
    @php
        $h     = $totalHoras[$medico->id];
        $pct   = min(100, round($h / 192 * 100));
        $color = $h < 100 ? '#ef4444' : ($h > 192 ? '#f59e0b' : '#22c55e');
        // contar tipos de turno
        $conteo = array_count_values(array_filter(
            array_values($grilla[$medico->id] ?? []),
            fn($v) => $v !== ''
        ));
    @endphp
    <div class="col-sm-6 col-xl-4">
        <div class="panel py-3 px-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="fw-semibold small">{{ $medico->nombre }}</span>
                <span class="fw-bold small" style="color:{{ $color }}">{{ $h }}h</span>
            </div>
            <div class="resumen-bar mb-2"><div class="resumen-bar-fill" style="width:{{ $pct }}%;background:{{ $color }}"></div></div>
            <div class="d-flex gap-1 flex-wrap">
                @foreach(['M','T','MT','N','MTN','MN','VAC','PER','INC'] as $cod)
                    @if(isset($conteo[$cod]) && $conteo[$cod] > 0)
                        <span class="tbadge tb-{{ $cod }}" title="{{ $cod }}">{{ $conteo[$cod] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
