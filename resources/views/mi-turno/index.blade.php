@extends('layouts.app')

@section('title', 'Mi Turno del Mes')

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

{{-- Resumen KPIs --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ number_format($resumen['total_h'], 1) }}h</div>
            <div class="small text-muted">Horas totales</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-3 fw-bold text-warning">{{ number_format($resumen['diurnas'], 1) }}h</div>
            <div class="small text-muted">Horas diurnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ number_format($resumen['nocturnas'], 1) }}h</div>
            <div class="small text-muted">Horas nocturnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $turnos->whereNotIn('codigo_turno',['LIBRE',''])->count() }}</div>
            <div class="small text-muted">Días trabajados</div>
        </div>
    </div>
</div>

{{-- Distribución de códigos --}}
@if(!empty($resumen['por_codigo']))
<div class="panel mb-3">
    <h6 class="fw-semibold mb-3" style="color:#1a2340">Distribución de turnos</h6>
    <div class="d-flex flex-wrap gap-2">
        @php
            $colores = ['M'=>'primary','T'=>'warning','MT'=>'success','N'=>'info','MTN'=>'danger','MN'=>'purple','LIBRE'=>'secondary'];
        @endphp
        @foreach($resumen['por_codigo'] as $codigo => $cnt)
            @php $color = $colores[$codigo] ?? 'secondary'; @endphp
            <span class="badge bg-{{ $color }} fs-6 px-3 py-2">
                {{ $codigo }}: {{ $cnt }} día{{ $cnt !== 1 ? 's' : '' }}
            </span>
        @endforeach
    </div>
</div>
@endif

{{-- Calendario de turnos --}}
<div class="panel">
    <h6 class="fw-semibold mb-3" style="color:#1a2340">Detalle del mes</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Turno</th>
                    <th>UCI</th>
                    <th class="text-end">Horas</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $diasEs = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb','Sun'=>'Dom'];
                    $bgTurno = ['M'=>'rgba(59,130,246,.1)','T'=>'rgba(234,179,8,.1)','MT'=>'rgba(16,185,129,.1)','N'=>'rgba(99,102,241,.1)','MTN'=>'rgba(239,68,68,.1)','MN'=>'rgba(139,92,246,.1)'];
                @endphp
                @foreach($turnos as $t)
                    @php
                        $fecha = \Carbon\Carbon::parse($t->fecha);
                        $esFinde = $fecha->isWeekend();
                        $diaEn   = $fecha->format('D');
                        $diaEs   = $diasEs[$diaEn] ?? $diaEn;
                        $bg      = $bgTurno[$t->codigo_turno] ?? '';
                    @endphp
                    <tr style="{{ $bg ? "background:{$bg}" : '' }}" class="{{ $esFinde ? 'table-secondary' : '' }}">
                        <td class="fw-semibold">{{ $fecha->format('d/m') }}</td>
                        <td>
                            <span class="{{ $esFinde ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $diaEs }}</span>
                        </td>
                        <td>
                            @php
                                $badgeColor = match($t->codigo_turno) {
                                    'M'   => 'primary',
                                    'T'   => 'warning',
                                    'MT'  => 'success',
                                    'N'   => 'info',
                                    'MTN' => 'danger',
                                    'MN'  => 'purple',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeColor }}">{{ $t->codigo_turno ?: '—' }}</span>
                        </td>
                        <td class="text-muted small">{{ $t->uci?->nombre ?? '—' }}</td>
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
                    <td colspan="4">Total</td>
                    <td class="text-end">{{ number_format($resumen['total_h'], 1) }}h</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

@endif

@push('styles')
<style>
@media print {
    .sidebar, .topbar, .d-print-none { display:none !important; }
    .content-wrapper { margin-left:0 !important; }
    .panel { box-shadow:none !important; border:1px solid #ddd !important; }
}
</style>
@endpush

@endsection