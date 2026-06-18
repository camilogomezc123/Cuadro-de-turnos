@extends('layouts.app')

@section('title', $medico->nombre_completo)
@section('page-title', $medico->nombre_completo)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('medicos.index') }}">Médicos</a></li>
    <li class="breadcrumb-item active">{{ Str::limit($medico->nombre_completo, 30) }}</li>
@endsection

@section('content')
{{-- SELECTOR PERÍODO --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="mes" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
            @foreach($nombresMeses as $i => $nombre)
                <option value="{{ $i+1 }}" @selected($mes == $i+1)>{{ $nombre }}</option>
            @endforeach
        </select>
        <select name="anio" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
            @for($y = now()->year; $y >= now()->year - 3; $y--)
                <option value="{{ $y }}" @selected($anio == $y)>{{ $y }}</option>
            @endfor
        </select>
    </form>
    <a href="{{ route('medicos.index', ['mes' => $mes, 'anio' => $anio]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

{{-- KPIs --}}
@php
    $tot  = $indicador['total_horas'];
    $color= $tot > 200 ? 'danger' : ($tot < 80 ? 'warning' : 'success');
@endphp
<div class="row g-3 mb-4">
    @php $kpis = [
        ['Total Horas',    number_format($tot, 0).'h', 'bi-clock-fill',       '#E3F2FD', 'text-primary'],
        ['H. Diurnas',     number_format($indicador['horas_diurnas'],0).'h', 'bi-sun-fill','#FFF8E1','text-warning'],
        ['H. Nocturnas',   number_format($indicador['horas_nocturnas'],0).'h','bi-moon-stars-fill','#EDE7F6','text-purple'],
        ['Turnos nocturnos',$indicador['turnos_nocturnos'],'bi-moon-fill','#EDE7F6','text-purple'],
        ['Fines de semana',$indicador['fines_semana'],'bi-calendar-heart-fill','#FCE4EC','text-danger'],
        ['Supera 200h',    $indicador['supera_200h']?'Sí':'No','bi-exclamation-triangle-fill','#FFEBEE',$indicador['supera_200h']?'text-danger':'text-success'],
    ]; @endphp
    @foreach($kpis as [$label, $value, $icon, $bg, $textClass])
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:{{ $bg }}">
                <i class="bi {{ $icon }} {{ $textClass }} fs-5"></i>
            </div>
            <div class="kpi-value" style="font-size:1.4rem">{{ $value }}</div>
            <div class="kpi-label">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- UCIs del médico este mes --}}
@if($ucisDelMedico->isNotEmpty())
<div class="panel mb-3">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-building-fill-cross me-2 text-primary"></i>UCIs — {{ $nombresMeses[$mes-1] }} {{ $anio }}</span>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>UCI</th><th class="text-end">Horas</th></tr></thead>
                <tbody>
                    @foreach($ucisDelMedico as $u)
                    <tr>
                        <td>{{ $u->uci?->nombre ?? '—' }}</td>
                        <td class="text-end fw-bold">{{ number_format($u->horas, 0) }}h</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Turnos del mes --}}
<div class="panel mb-3">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-calendar3 me-2 text-success"></i>Turnos — {{ $nombresMeses[$mes-1] }} {{ $anio }}</span>
        <span class="badge bg-success-subtle text-success">{{ $turnos->count() }} registros</span>
    </div>
    <div class="panel-body p-0">
        @if($turnos->isEmpty())
            <div class="text-center text-muted py-4">Sin turnos registrados para este período.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Fecha</th><th>UCI</th><th class="text-center">Turno</th><th class="text-end">Horas</th><th class="text-end">H.Noct</th></tr>
                </thead>
                <tbody>
                    @foreach($turnos->whereIn('codigo_turno',['M','T','MT','N','MTN','MN']) as $t)
                    <tr>
                        <td>{{ $t->fecha->format('d/m/Y') }} <small class="text-muted">{{ $t->dia_semana }}</small></td>
                        <td><span class="badge bg-primary-subtle text-primary" style="font-size:.7rem">{{ $t->uci?->codigo }}</span></td>
                        <td class="text-center"><span class="badge-{{ $t->codigo_turno }} turno-badge">{{ $t->codigo_turno }}</span></td>
                        <td class="text-end">{{ $t->horas_total }}h</td>
                        <td class="text-end" style="color:#7C3AED">{{ $t->horas_nocturnas > 0 ? $t->horas_nocturnas.'h' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="fw-bold text-end">Total:</td>
                        <td class="text-end fw-bold text-primary">{{ number_format($tot, 0) }}h</td>
                        <td class="text-end fw-bold" style="color:#7C3AED">{{ number_format($indicador['horas_nocturnas'], 0) }}h</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- HISTORIAL --}}
@if($historial->isNotEmpty())
<div class="panel mt-3">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-clock-history me-2 text-secondary"></i>Historial por mes</span>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>Período</th><th>UCI</th><th class="text-end">Horas</th><th class="text-end">Turnos</th></tr>
                </thead>
                <tbody>
                    @foreach($historial as $h)
                    <tr>
                        <td>
                            <a href="{{ route('medicos.show', ['medico' => $medico->id, 'mes' => $h->mes, 'anio' => $h->anio]) }}"
                               class="text-decoration-none">
                                {{ ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$h->mes] }} {{ $h->anio }}
                            </a>
                        </td>
                        <td><span class="badge bg-primary-subtle text-primary" style="font-size:.7rem">{{ $h->uci?->codigo }}</span></td>
                        <td class="text-end">{{ number_format($h->total_horas, 0) }}h</td>
                        <td class="text-end">{{ $h->total_turnos }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
