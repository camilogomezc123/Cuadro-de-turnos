@extends('layouts.app')

@section('title', $uci->nombre)
@section('page-title', $uci->nombre)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ucis.index') }}">UCIs</a></li>
    <li class="breadcrumb-item active">{{ $uci->nombre }}</li>
@endsection

@section('content')
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
    <a href="{{ route('ucis.index', ['mes' => $mes, 'anio' => $anio]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

@php
    $totalHoras     = $indicador->total_horas ?? 0;
    $totalMedicos   = $indicador->total_medicos ?? 0;
    $totalTurnos    = $indicador->total_turnos ?? 0;
    $horasNocturnas = $indicador->total_horas_nocturnas ?? 0;
    $promHoras      = $totalMedicos > 0 ? round($totalHoras / $totalMedicos, 1) : 0;
    $pctNocturna    = $totalHoras > 0 ? round($horasNocturnas / $totalHoras * 100, 1) : 0;
    $pctDiurna      = 100 - $pctNocturna;
@endphp

@if($totalHoras > 0)
<div class="row g-3 mb-4">
    @php $kpis = [
        ['Médicos activos', $totalMedicos,              'bi-people-fill',        '#E3F2FD', '#1565C0'],
        ['Horas Totales',   number_format($totalHoras, 0), 'bi-clock-fill',      '#E8F5E9', '#2E7D32'],
        ['Prom/Médico',     $promHoras.'h',             'bi-graph-up',           '#FFF8E1', '#E65100'],
        ['Turnos programados', $totalTurnos,            'bi-calendar-check-fill','#E0F7FA', '#0277BD'],
        ['H. Nocturnas',    number_format($horasNocturnas, 0), 'bi-moon-stars-fill','#EDE7F6','#4527A0'],
        ['% Nocturno',      $pctNocturna.'%',           'bi-pie-chart-fill',    '#FCE4EC', '#C62828'],
    ]; @endphp
    @foreach($kpis as [$label, $val, $icon, $bg, $color])
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:{{ $bg }}">
                <i class="bi {{ $icon }} fs-5" style="color:{{ $color }}"></i>
            </div>
            <div class="kpi-value" style="font-size:1.4rem">{{ $val }}</div>
            <div class="kpi-label">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- Distribución carga --}}
    <div class="col-md-4">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pie-chart me-2 text-primary"></i>Distribución de Carga</span>
            </div>
            <div class="panel-body">
                <canvas id="chartCarga" height="200"></canvas>
                <div class="text-center mt-3">
                    <div class="d-flex justify-content-around">
                        <div>
                            <div class="fw-bold text-primary">{{ $pctDiurna }}%</div>
                            <div class="text-muted small">Diurna</div>
                        </div>
                        <div>
                            <div class="fw-bold" style="color:#7C3AED">{{ $pctNocturna }}%</div>
                            <div class="text-muted small">Nocturna</div>
                        </div>
                    </div>
                </div>
                @if($distribucion->isNotEmpty())
                <hr class="my-3">
                <div class="small text-muted mb-1 fw-semibold">Por tipo de turno</div>
                @foreach($distribucion as $cod => $dist)
                <div class="d-flex justify-content-between small mb-1">
                    <span class="badge" style="background:var(--bs-secondary-bg);color:inherit">{{ $cod }}</span>
                    <span class="fw-semibold">{{ $dist->cnt }}</span>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Médicos de la UCI --}}
    <div class="col-md-8">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-person-badge me-2 text-success"></i>Médicos — {{ $nombresMeses[$mes-1] }} {{ $anio }}</span>
                <span class="badge bg-success-subtle text-success">{{ $medicos->count() }}</span>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr><th>Médico</th><th class="text-end">Horas</th><th class="text-end">H. Noct.</th><th class="text-end">T. Noct.</th><th class="text-end">F/S</th></tr>
                        </thead>
                        <tbody>
                            @forelse($medicos as $m)
                            <tr>
                                <td>
                                    <a href="{{ route('medicos.show', ['medico' => $m->medico_id, 'mes' => $mes, 'anio' => $anio]) }}"
                                       class="fw-semibold text-decoration-none text-dark">
                                        {{ $m->medico?->nombre_completo ?? '—' }}
                                    </a>
                                </td>
                                <td class="text-end"><strong>{{ number_format($m->total_horas, 0) }}</strong></td>
                                <td class="text-end" style="color:#7C3AED">{{ number_format($m->horas_nocturnas, 0) }}</td>
                                <td class="text-end">{{ $m->turnos_nocturnos }}</td>
                                <td class="text-end">{{ $m->fines_semana }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">Sin médicos registrados</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@else
<div class="panel p-5 text-center text-muted">
    <i class="bi bi-info-circle fs-1 opacity-25 d-block mb-2"></i>
    No hay turnos registrados para <strong>{{ $uci->nombre }}</strong> en {{ $nombresMeses[$mes-1] }} {{ $anio }}.
</div>
@endif

{{-- HISTORIAL --}}
@if($historial->isNotEmpty())
<div class="panel mt-3">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-clock-history me-2 text-secondary"></i>Historial por Período</span>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr><th>Período</th><th class="text-end">Médicos</th><th class="text-end">Horas Totales</th></tr>
                </thead>
                <tbody>
                    @foreach($historial as $h)
                    <tr>
                        <td>
                            <a href="{{ route('ucis.show', ['uci' => $uci->id, 'mes' => $h->mes, 'anio' => $h->anio]) }}"
                               class="text-decoration-none">
                                {{ ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$h->mes] }} {{ $h->anio }}
                            </a>
                        </td>
                        <td class="text-end">{{ $h->medicos }}</td>
                        <td class="text-end">{{ number_format($h->horas, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
@if($totalHoras > 0)
new Chart(document.getElementById('chartCarga'), {
    type: 'doughnut',
    data: {
        labels: ['Diurna', 'Nocturna'],
        datasets: [{
            data: [{{ $pctDiurna }}, {{ $pctNocturna }}],
            backgroundColor: ['#2196F3','#7C3AED'],
            borderWidth: 0,
            hoverOffset: 6,
        }]
    },
    options: { responsive: true, cutout: '60%', plugins: { legend: { display: false } } }
});
@endif
</script>
@endpush
