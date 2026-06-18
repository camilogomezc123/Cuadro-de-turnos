@extends('layouts.app')
@section('title','Consolidado Anual')
@section('page-title','Consolidado Anual de Horas')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('consolidado.index') }}">Consolidado</a></li>
    <li class="breadcrumb-item active">Anual</li>
@endsection

@push('styles')
<style>
.tabla-anual th, .tabla-anual td { padding:5px 8px; text-align:center; white-space:nowrap; }
.tabla-anual .col-medico { text-align:left; min-width:150px; position:sticky; left:0; background:#fff; z-index:2; border-right:2px solid #dee2e6; }
.tabla-anual thead th { position:sticky; top:0; background:#f0f4f8; z-index:3; }
.tabla-anual thead th.col-medico { z-index:4; }
.tabla-anual .col-total { background:#f0f4f8; font-weight:700; position:sticky; right:0; border-left:2px solid #dee2e6; }
.tabla-anual .mes-pasado { color:#adb5bd; }
.tabla-anual .mes-actual { background:#fff9e6; font-weight:700; }
.h-exceso  { color:#dc3545; font-weight:700; }
.h-adecuado{ color:#198754; }
.h-bajo    { color:#fd7e14; }
.h-vacio   { color:#dee2e6; }
.wrap-tabla { overflow-x:auto; max-height:75vh; overflow-y:auto; }
.pill-mes { display:inline-block; padding:2px 7px; border-radius:12px; font-size:.78rem; font-weight:600; }
</style>
@endpush

@section('content')
<div class="fade-in">

{{-- Selector de año --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form method="GET" action="{{ route('consolidado.anual') }}" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-semibold text-secondary small mb-0"><i class="bi bi-calendar me-1"></i>Año:</label>
            <div class="d-flex gap-1">
                @foreach($anios as $y)
                <a href="{{ route('consolidado.anual', ['anio'=>$y]) }}"
                   class="btn btn-sm {{ $y==$anio ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $y }}
                </a>
                @endforeach
            </div>
            <span class="ms-auto text-muted small">
                <i class="bi bi-people me-1"></i>{{ count($matriz) }} médicos
            </span>
        </form>
    </div>
</div>

{{-- Leyenda --}}
<div class="d-flex gap-3 mb-3 flex-wrap small">
    <span><span class="pill-mes bg-danger-subtle text-danger">215h</span> &gt; 200h (exceso)</span>
    <span><span class="pill-mes bg-success-subtle text-success">165h</span> 100–200h (adecuado)</span>
    <span><span class="pill-mes bg-warning-subtle text-warning">80h</span> &lt; 100h (bajo)</span>
    <span><span class="pill-mes bg-light text-muted">—</span> Sin turnos</span>
</div>

{{-- Tabla matriz --}}
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-table me-2 text-primary"></i>
            Horas reconocidas — {{ $anio }}
        </span>
        <a href="{{ route('consolidado.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-calendar-month me-1"></i>Vista mensual
        </a>
    </div>
    <div class="panel-body p-0 wrap-tabla">
        <table class="table table-bordered tabla-anual mb-0">
            <thead>
                <tr>
                    <th class="col-medico">Médico</th>
                    @foreach($meses as $i => $m)
                    @php
                        $mn = $i + 1;
                        $esActual = ($mn === now()->month && $anio === now()->year);
                        $esPasado = ($anio < now()->year) || ($anio === now()->year && $mn < now()->month);
                    @endphp
                    <th class="{{ $esActual ? 'mes-actual' : ($esPasado ? 'mes-pasado' : '') }}">
                        {{ substr($m, 0, 3) }}
                        @if($esActual)<div style="font-size:.6rem;color:#f59e0b">▲ actual</div>@endif
                    </th>
                    @endforeach
                    <th class="col-total">Total</th>
                </tr>
                {{-- Fila de totales por columna --}}
                <tr class="table-light fw-bold" style="font-size:.78rem">
                    <td class="col-medico text-muted">SUMA UCI</td>
                    @foreach($meses as $i => $m)
                    @php $mn = $i + 1; $t = $totales[$mn] ?? 0; @endphp
                    <td class="{{ $t > 0 ? '' : 'text-muted' }}">
                        {{ $t > 0 ? number_format($t, 0) : '—' }}
                    </td>
                    @endforeach
                    <td class="col-total">{{ number_format(array_sum($totales), 0) }}</td>
                </tr>
            </thead>
            <tbody>
                @forelse($matriz as $medicoId => $row)
                @php
                    $totalAnual = $row['total'];
                    $pctColor   = $totalAnual > 200*12*0.8 ? 'danger' : ($totalAnual > 100*12*0.5 ? 'success' : 'warning');
                @endphp
                <tr>
                    <td class="col-medico fw-semibold" style="font-size:.83rem">{{ $row['nombre'] }}</td>
                    @foreach($meses as $i => $m)
                    @php
                        $mn  = $i + 1;
                        $h   = $row['meses'][$mn] ?? 0;
                        $cls = $h > 200 ? 'pill-mes bg-danger-subtle text-danger'
                             : ($h >= 100 ? 'pill-mes bg-success-subtle text-success'
                             : ($h > 0    ? 'pill-mes bg-warning-subtle text-warning'
                             :              ''));
                        $esActual = ($mn === now()->month && $anio === now()->year);
                    @endphp
                    <td class="{{ $esActual ? 'mes-actual' : '' }}">
                        @if($h > 0)
                            <span class="{{ $cls }}">{{ number_format($h, 0) }}</span>
                        @else
                            <span class="text-muted" style="font-size:.7rem">—</span>
                        @endif
                    </td>
                    @endforeach
                    <td class="col-total">
                        @if($totalAnual > 0)
                            <span class="{{ $totalAnual > 2400 ? 'text-danger' : ($totalAnual >= 1200 ? 'text-success' : 'text-warning') }}">
                                {{ number_format($totalAnual, 0) }}h
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($meses) + 2 }}" class="text-center text-muted py-4">
                        No hay datos de turnos para el año {{ $anio }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
@endsection
