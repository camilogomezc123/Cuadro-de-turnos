@extends('layouts.app')

@section('title', 'Dashboard UCI')
@section('page-title', 'Dashboard Ejecutivo')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="fade-in">

{{-- SELECTOR DE PERÍODO --}}
@if($archivos->isNotEmpty())
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <form method="GET" action="{{ route('dashboard.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold text-secondary small mb-0"><i class="bi bi-calendar3 me-1"></i>Período:</label>
        <select name="archivo_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            @foreach($archivos as $a)
                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                    {{ $a->nombre_mes }} {{ $a->anio }}
                </option>
            @endforeach
        </select>
    </form>
    @if($archivo)
        <span class="badge bg-success-subtle text-success rounded-pill fs-7">
            <i class="bi bi-check-circle me-1"></i>{{ $archivo->total_medicos }} médicos · {{ $archivo->total_turnos }} turnos
        </span>
    @endif
</div>
@endif

{{-- SIN DATOS --}}
@if($archivos->isEmpty())
<div class="panel p-5 text-center">
    <i class="bi bi-file-earmark-excel text-primary" style="font-size:4rem;opacity:.3"></i>
    <h5 class="mt-3 text-secondary">Sin datos cargados</h5>
    <p class="text-muted small">Cargue un archivo Excel de turnos para ver el dashboard.</p>
    <a href="{{ route('archivos.index') }}" class="btn btn-primary mt-2">
        <i class="bi bi-cloud-upload me-2"></i>Cargar Excel
    </a>
</div>
@else

{{-- KPI CARDS --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-1">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#E3F2FD">
                    <i class="bi bi-people-fill text-primary fs-4"></i>
                </div>
            </div>
            <div class="kpi-value">{{ number_format($resumen['totalMedicos']) }}</div>
            <div class="kpi-label">Médicos Activos</div>
            <div class="kpi-sub">Total en el período</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-1">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#E8F5E9">
                    <i class="bi bi-clock-fill text-success fs-4"></i>
                </div>
            </div>
            <div class="kpi-value">{{ number_format($resumen['totalHoras'], 0) }}</div>
            <div class="kpi-label">Horas Totales</div>
            <div class="kpi-sub">Todas las UCIs</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-2">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#EDE7F6">
                    <i class="bi bi-moon-stars-fill text-purple fs-4" style="color:#7C3AED"></i>
                </div>
            </div>
            <div class="kpi-value">{{ number_format($resumen['horasNocturnas'], 0) }}</div>
            <div class="kpi-label">Horas Nocturnas</div>
            <div class="kpi-sub">Turno N (7pm–7am)</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-2">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#FFF8E1">
                    <i class="bi bi-graph-up text-warning fs-4"></i>
                </div>
            </div>
            <div class="kpi-value">{{ number_format($resumen['promedioHoras'], 1) }}</div>
            <div class="kpi-label">Promedio/Médico</div>
            <div class="kpi-sub">Horas en el mes</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#E0F7FA">
                    <i class="bi bi-calendar-check-fill text-info fs-4"></i>
                </div>
            </div>
            <div class="kpi-value">{{ $resumen['coberturaMensualProm'] }}%</div>
            <div class="kpi-label">Cobertura Mensual</div>
            <div class="kpi-sub">Promedio por UCI</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2 fade-in fade-in-delay-3">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="kpi-icon" style="background:#FCE4EC">
                    <i class="bi bi-shield-check-fill fs-4" style="color:#C62828"></i>
                </div>
            </div>
            <div class="kpi-value">{{ $resumen['coberturaFindeProm'] }}%</div>
            <div class="kpi-label">Cobertura F/S</div>
            <div class="kpi-sub">Fines de semana</div>
        </div>
    </div>
</div>

{{-- KPIs SEGUNDA FILA: Alertas + MTN --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3 fade-in fade-in-delay-1">
        <div class="kpi-card" style="border-left: 4px solid #ef5350">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon" style="background:#FFEBEE">
                    <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                </div>
                @if($alertasAbiertas > 0)
                    <a href="{{ route('alertas.index', ['archivo_id' => $archivoId]) }}" class="badge bg-danger text-decoration-none">Ver</a>
                @endif
            </div>
            <div class="kpi-value text-danger">{{ $alertasAbiertas }}</div>
            <div class="kpi-label">Alertas Abiertas</div>
            <div class="kpi-sub">{{ $alertasAltas }} de alta prioridad</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 fade-in fade-in-delay-1">
        <div class="kpi-card" style="border-left: 4px solid #880E4F">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon" style="background:#FCE4EC">
                    <i class="bi bi-stars fs-4" style="color:#880E4F"></i>
                </div>
            </div>
            <div class="kpi-value" style="color:#880E4F">{{ $turnosMtn }}</div>
            <div class="kpi-label">Turnos MTN</div>
            <div class="kpi-sub">Mañana-Tarde-Noche (24h)</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 fade-in fade-in-delay-2">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon" style="background:#E8F5E9">
                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                </div>
            </div>
            @php
                $pctCumplenMin = $resumen['totalMedicos'] > 0
                    ? round((\App\Models\IndicadorMedico::where('archivo_id', $archivoId)->where('total_horas', '>=', 100)->count() / max(1,$resumen['totalMedicos'])) * 100)
                    : 0;
            @endphp
            <div class="kpi-value text-success">{{ $pctCumplenMin }}%</div>
            <div class="kpi-label">Cumplen ≥100h/mes</div>
            <div class="kpi-sub">Médicos sobre el mínimo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3 fade-in fade-in-delay-2">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="kpi-icon" style="background:#FFF3E0">
                    <i class="bi bi-arrow-left-right text-warning fs-4"></i>
                </div>
            </div>
            @php
                $cambiosPendientes = \App\Models\SolicitudCambioTurno::whereIn('estado',['pendiente','aceptado_colega'])->count();
            @endphp
            <div class="kpi-value text-warning">{{ $cambiosPendientes }}</div>
            <div class="kpi-label">Cambios Pendientes</div>
            <div class="kpi-sub">Solicitudes en curso</div>
        </div>
    </div>
</div>

{{-- GRÁFICOS FILA 1 --}}
<div class="row g-3 mb-3">
    {{-- Horas por UCI --}}
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Horas por UCI</span>
            </div>
            <div class="panel-body">
                <canvas id="chartHorasUci" height="260"></canvas>
            </div>
        </div>
    </div>

    {{-- Distribución de turnos --}}
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Distribución de Turnos</span>
            </div>
            <div class="panel-body d-flex flex-column align-items-center">
                <canvas id="chartTurnos" width="240" height="240"></canvas>
                <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                    @php $dist = $distribucionTurnos; $total = array_sum($dist); @endphp
                    @foreach(['M' => ['#2196F3','Mañana'], 'T' => ['#4CAF50','Tarde'], 'MT' => ['#FF9800','M-Tarde'], 'N' => ['#9C27B0','Noche']] as $cod => [$color, $label])
                    <div class="d-flex align-items-center gap-1 small">
                        <div style="width:12px;height:12px;border-radius:3px;background:{{ $color }}"></div>
                        <span class="text-muted">{{ $label }}</span>
                        <strong>{{ $dist[$cod] ?? 0 }}</strong>
                        @if($total > 0)<span class="text-muted">({{ round(($dist[$cod]/$total)*100,1) }}%)</span>@endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- GRÁFICOS FILA 2 --}}
<div class="row g-3 mb-3">
    {{-- Ranking de médicos --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-trophy-fill me-2 text-warning"></i>Ranking de Carga Laboral</span>
                <span class="badge bg-secondary-subtle text-secondary">Top {{ count($rankingMedicos) }}</span>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Médico</th>
                                <th>UCI</th>
                                <th>Horas</th>
                                <th>% Ocup.</th>
                                <th>Carga</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rankingMedicos as $i => $m)
                            <tr>
                                <td>
                                    @if($i === 0)<i class="bi bi-trophy-fill text-warning"></i>
                                    @elseif($i === 1)<i class="bi bi-trophy-fill text-secondary"></i>
                                    @elseif($i === 2)<i class="bi bi-trophy-fill" style="color:#cd7f32"></i>
                                    @else<span class="text-muted">{{ $i+1 }}</span>@endif
                                </td>
                                <td class="fw-semibold">{{ $m['nombre'] }}</td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ Str::limit($m['uci'], 18) }}</span></td>
                                <td><strong>{{ number_format($m['horas'], 1) }}</strong></td>
                                <td>{{ number_format($m['pct'], 1) }}%</td>
                                <td style="width:120px">
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" style="width:{{ min($m['pct'], 100) }}%"></div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Cobertura semanal --}}
    <div class="col-lg-4">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-calendar-week me-2 text-info"></i>Cobertura Semanal</span>
            </div>
            <div class="panel-body">
                <canvas id="chartSemanal" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

@endif
</div>
@endsection

@push('scripts')
<script>
@if(!$archivos->isEmpty())
// Chart: Horas por UCI
const horasUciData = @json($horasPorUci);
new Chart(document.getElementById('chartHorasUci'), {
    type: 'bar',
    data: {
        labels: horasUciData.map(d => d.uci.replace('UCI ', '').replace('CARDIOVASCULAR','CARDIO')),
        datasets: [{
            label: 'Horas Totales',
            data: horasUciData.map(d => d.horas),
            backgroundColor: [
                '#1565C0','#1976D2','#1E88E5','#2196F3','#42A5F5',
                '#64B5F6','#90CAF9','#1B5E20','#388E3C'
            ],
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y.toFixed(1)} horas` } }
        },
        scales: {
            y: { grid: { color: '#f0f4f8' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Chart: Distribución de Turnos
const dist = @json($distribucionTurnos);
new Chart(document.getElementById('chartTurnos'), {
    type: 'doughnut',
    data: {
        labels: ['Mañana (M)', 'Tarde (T)', 'M-Tarde (MT)', 'Noche (N)'],
        datasets: [{
            data: [dist.M, dist.T, dist.MT, dist.N],
            backgroundColor: ['#2196F3','#4CAF50','#FF9800','#9C27B0'],
            borderWidth: 0,
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});

// Chart: Cobertura Semanal
const semData = @json($coberturaSemanal);
new Chart(document.getElementById('chartSemanal'), {
    type: 'radar',
    data: {
        labels: semData.map(d => d.dia),
        datasets: [{
            label: 'Horas',
            data: semData.map(d => d.horas),
            backgroundColor: 'rgba(33,150,243,.15)',
            borderColor: '#2196F3',
            borderWidth: 2,
            pointBackgroundColor: '#2196F3',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { r: { grid: { color: '#f0f4f8' }, ticks: { font: { size: 10 } } } }
    }
});
@endif
</script>
@endpush
