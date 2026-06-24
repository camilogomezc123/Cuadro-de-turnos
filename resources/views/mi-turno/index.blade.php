@extends('layouts.app')
@section('title', 'Mi Turno del Mes')

@push('styles')
<style>
/* ── KPIs ───────────────────────────── */
.kpi-card { text-align:center; padding:.9rem .5rem; }
.kpi-num  { font-size:1.6rem; font-weight:700; line-height:1.1; }

/* ── Cuadrícula calendario ──────────── */
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.cal-header-day {
    text-align: center;
    font-size: .68rem;
    font-weight: 700;
    color: #64748b;
    padding: 3px 0;
}
.cal-header-day.finde { color:#e11d48; }
.cal-cell {
    min-height: 48px;
    border-radius: 6px;
    padding: 3px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    cursor: default;
}
.cal-cell.finde    { background: #fff1f2; border-color:#fecdd3; }
.cal-cell.vacio    { background: transparent; border-color: transparent; }
.cal-cell.hoy      { border: 2px solid #6366f1 !important; }
.cal-num {
    font-size: .68rem;
    font-weight: 600;
    color: #475569;
    align-self: flex-end;
    margin-bottom: 1px;
    line-height: 1;
}
.cal-cell.finde .cal-num { color:#e11d48; }
.cal-badge {
    font-size: .62rem;
    font-weight: 700;
    border-radius: 4px;
    padding: 1px 4px;
    margin-top: auto;
    line-height: 1.4;
    white-space: nowrap;
}
/* Colores de turno para la cuadrícula */
.cb-M    { background:#d1fae5; color:#065f46; }
.cb-T    { background:#dbeafe; color:#1e40af; }
.cb-MT   { background:#e0e7ff; color:#3730a3; }
.cb-N    { background:#1e1b4b; color:#e0e7ff; }
.cb-MTN  { background:#7c3aed; color:#ede9fe; }
.cb-MN   { background:#4c1d95; color:#ede9fe; }
.cb-PER  { background:#fef9c3; color:#713f12; }
.cb-INC  { background:#fee2e2; color:#991b1b; }
.cb-LIBRE{ background:#f1f5f9; color:#94a3b8; }
.cb-vacio{ background:transparent; color:transparent; }

/* ── Tabla detalle ──────────────────── */
.tabla-detalle td, .tabla-detalle th {
    padding: .35rem .5rem;
    font-size: .82rem;
}
@media (max-width: 575px) {
    .tabla-detalle { font-size: .76rem; }
    .tabla-detalle td, .tabla-detalle th { padding: .28rem .35rem; }
    .kpi-num { font-size:1.3rem; }
}

/* ── Print ──────────────────────────── */
@media print {
    .sidebar, .topbar, .d-print-none { display:none !important; }
    .content-wrapper { margin-left:0 !important; }
    .panel { box-shadow:none !important; border:1px solid #ddd !important; }
    .cal-grid { break-inside: avoid; }
}
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0" style="color:#1a2340">
        <i class="bi bi-calendar-check me-2 text-primary"></i>Mi Turno del Mes
    </h4>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm d-print-none">
        <i class="bi bi-printer me-1"></i>Imprimir
    </button>
</div>

{{-- Selector de período --}}
<div class="panel mb-3 d-print-none">
    <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
        <label class="fw-semibold text-muted small mb-0">Período:</label>
        <select name="archivo_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @foreach($archivos as $a)
                <option value="{{ $a->id }}" @selected($a->id == $archivoId)>
                    {{ \Carbon\Carbon::create($a->anio, $a->mes)->translatedFormat('F Y') }}
                </option>
            @endforeach
        </select>
    </form>
</div>

@if(!$archivo)
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No hay períodos disponibles.</div>
@elseif($turnos->isEmpty())
    <div class="alert alert-warning"><i class="bi bi-exclamation-circle me-2"></i>No tienes turnos registrados para este período.</div>
@else

{{-- KPIs --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="panel kpi-card">
            <div class="kpi-num text-primary">{{ number_format($resumen['total_h'], 1) }}h</div>
            <div class="small text-muted">Horas totales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel kpi-card">
            <div class="kpi-num text-warning">{{ number_format($resumen['diurnas'], 1) }}h</div>
            <div class="small text-muted">Diurnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel kpi-card">
            <div class="kpi-num text-info">{{ number_format($resumen['nocturnas'], 1) }}h</div>
            <div class="small text-muted">Nocturnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel kpi-card">
            <div class="kpi-num text-success">{{ $turnos->whereNotIn('codigo_turno',['LIBRE','',' '])->count() }}</div>
            <div class="small text-muted">Días trabajados</div>
        </div>
    </div>
</div>

{{-- Distribución --}}
@if(!empty($resumen['por_codigo']))
<div class="panel mb-3">
    <h6 class="fw-semibold mb-2" style="color:#1a2340;font-size:.87rem">Distribución</h6>
    @php
        $cbCls = ['M'=>'cb-M','T'=>'cb-T','MT'=>'cb-MT','N'=>'cb-N','MTN'=>'cb-MTN','MN'=>'cb-MN','PER'=>'cb-PER','INC'=>'cb-INC','LIBRE'=>'cb-LIBRE'];
    @endphp
    <div class="d-flex flex-wrap gap-2">
        @foreach($resumen['por_codigo'] as $codigo => $cnt)
        <span class="cal-badge {{ $cbCls[$codigo] ?? '' }}" style="font-size:.78rem;padding:4px 10px;border-radius:6px">
            {{ $codigo }} — {{ $cnt }}d
        </span>
        @endforeach
    </div>
</div>
@endif

{{-- Toggle: Calendario / Lista --}}
<div class="panel mb-3">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h6 class="fw-semibold mb-0" style="color:#1a2340;font-size:.87rem">
            @if($archivo)
                {{ \Carbon\Carbon::create($archivo->anio, $archivo->mes)->translatedFormat('F Y') }}
            @endif
        </h6>
        <div class="btn-group btn-group-sm d-print-none" role="group">
            <input type="radio" class="btn-check" name="vistaToggle" id="vCalendario" checked>
            <label class="btn btn-outline-primary" for="vCalendario">
                <i class="bi bi-calendar3 me-1"></i><span class="d-none d-sm-inline">Calendario</span>
            </label>
            <input type="radio" class="btn-check" name="vistaToggle" id="vLista">
            <label class="btn btn-outline-primary" for="vLista">
                <i class="bi bi-list-ul me-1"></i><span class="d-none d-sm-inline">Lista</span>
            </label>
        </div>
    </div>

    {{-- Vista Calendario --}}
    <div id="vistaCalendario">
        @php
            $mesNum  = (int)$archivo->mes;
            $anioNum = (int)$archivo->anio;
            $firstDow = \Carbon\Carbon::create($anioNum, $mesNum, 1)->dayOfWeek; // 0=Dom
            // Convertir a Lun=0: si Dom(0)->6, else dow-1
            $primerDia = ($firstDow === 0) ? 6 : $firstDow - 1;
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mesNum, $anioNum);
            // Indexar turnos por dia_numero
            $turnosPorDia = $turnos->keyBy('dia_numero');
            $hoy = now()->day . '-' . now()->month . '-' . now()->year;
        @endphp

        <div class="cal-grid mb-1">
            @foreach(['L','M','X','J','V','S','D'] as $i => $dl)
            <div class="cal-header-day {{ $i >= 5 ? 'finde' : '' }}">{{ $dl }}</div>
            @endforeach
        </div>

        <div class="cal-grid">
            {{-- Celdas vacías antes del día 1 --}}
            @for($v = 0; $v < $primerDia; $v++)
            <div class="cal-cell vacio"></div>
            @endfor

            {{-- Días del mes --}}
            @for($d = 1; $d <= $diasEnMes; $d++)
            @php
                $fecha  = \Carbon\Carbon::create($anioNum, $mesNum, $d);
                $dow    = $fecha->dayOfWeek;
                $idx    = ($dow === 0) ? 6 : $dow - 1; // 0=Lun..6=Dom
                $finde  = $idx >= 5;
                $turno  = $turnosPorDia[$d] ?? null;
                $codigo = $turno?->codigo_turno ?? '';
                $esHoy  = ($d === now()->day && $mesNum === now()->month && $anioNum === now()->year);
                $cbClass= $cbCls[$codigo] ?? 'cb-vacio';
            @endphp
            <div class="cal-cell {{ $finde ? 'finde' : '' }} {{ $esHoy ? 'hoy' : '' }}">
                <span class="cal-num">{{ $d }}</span>
                @if($codigo)
                <span class="cal-badge {{ $cbClass }}">{{ $codigo }}</span>
                @endif
            </div>
            @endfor
        </div>

        {{-- Leyenda --}}
        <div class="d-flex flex-wrap gap-1 mt-3">
            @foreach(['M','T','MT','N','MTN','MN'] as $c)
            <span class="cal-badge {{ $cbCls[$c] }}">{{ $c }}</span>
            @endforeach
        </div>
    </div>

    {{-- Vista Lista --}}
    <div id="vistaLista" style="display:none">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 tabla-detalle">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Día</th>
                        <th>Turno</th>
                        <th class="d-none d-md-table-cell">UCI</th>
                        <th class="text-end">Horas</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $diasEs = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb','Sun'=>'Dom'];
                    @endphp
                    @foreach($turnos as $t)
                    @php
                        $fecha  = \Carbon\Carbon::parse($t->fecha);
                        $finde  = $fecha->isWeekend();
                        $diaEs  = $diasEs[$fecha->format('D')] ?? $fecha->format('D');
                        $codigo = $t->codigo_turno ?: '';
                        $cbC    = $cbCls[$codigo] ?? 'cb-LIBRE';
                    @endphp
                    <tr class="{{ $finde ? 'table-secondary' : '' }}">
                        <td class="fw-semibold">{{ $fecha->format('d/m') }}</td>
                        <td><span class="{{ $finde ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $diaEs }}</span></td>
                        <td>
                            @if($codigo)
                            <span class="cal-badge {{ $cbC }}" style="font-size:.76rem;padding:2px 7px">{{ $codigo }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted small d-none d-md-table-cell">{{ $t->uci?->nombre ?? '—' }}</td>
                        <td class="text-end">
                            @if($t->horas_total)
                                <strong>{{ number_format($t->horas_total, 0) }}h</strong>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3">Total</td>
                        <td class="d-none d-md-table-cell"></td>
                        <td class="text-end">{{ number_format($resumen['total_h'], 1) }}h</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@endif

@push('scripts')
<script>
// Toggle calendar/list
document.getElementById('vCalendario').addEventListener('change', function() {
    document.getElementById('vistaCalendario').style.display = '';
    document.getElementById('vistaLista').style.display = 'none';
});
document.getElementById('vLista').addEventListener('change', function() {
    document.getElementById('vistaCalendario').style.display = 'none';
    document.getElementById('vistaLista').style.display = '';
});
</script>
@endpush

@endsection
