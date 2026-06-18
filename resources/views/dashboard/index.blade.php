@extends('layouts.app')

@section('title', 'Dashboard UCI')
@section('page-title', 'Dashboard Ejecutivo')
@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="fade-in">

{{-- Selector de período --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <form method="GET" action="{{ route('dashboard.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold text-secondary small mb-0"><i class="bi bi-calendar3 me-1"></i>Período:</label>
        <select name="mes" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
            @foreach($meses as $i => $m)
                <option value="{{ $i+1 }}" @selected($mes == $i+1)>{{ $m }}</option>
            @endforeach
        </select>
        <select name="anio" class="form-select form-select-sm" style="width:85px" onchange="this.form.submit()">
            @foreach($archivos->pluck('anio')->unique()->sortDesc() as $y)
                <option value="{{ $y }}" @selected($anio == $y)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
    @if($archivo)
        <span class="badge bg-success-subtle text-success rounded-pill">
            <i class="bi bi-check-circle me-1"></i>{{ $archivo->total_medicos ?? 0 }} médicos · {{ $archivo->total_turnos ?? 0 }} turnos cargados
        </span>
    @endif
    <a href="{{ route('consolidado.excel', ['mes'=>$mes,'anio'=>$anio]) }}" class="btn btn-sm btn-outline-success ms-auto">
        <i class="bi bi-file-earmark-excel me-1"></i>Descargar Excel
    </a>
</div>

{{-- KPI FILA 1: operacionales --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-1">
            <div class="kpi-icon mx-auto mb-2" style="background:#E3F2FD">
                <i class="bi bi-people-fill text-primary fs-4"></i>
            </div>
            <div class="kpi-value">{{ $totalMedicos }}</div>
            <div class="kpi-label">Médicos activos</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-1">
            <div class="kpi-icon mx-auto mb-2" style="background:#E8F5E9">
                <i class="bi bi-clock-fill text-success fs-4"></i>
            </div>
            <div class="kpi-value">{{ number_format($horasProgramadas, 0) }}</div>
            <div class="kpi-label">Horas programadas</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-2">
            <div class="kpi-icon mx-auto mb-2" style="background:#dcfce7">
                <i class="bi bi-check-circle-fill text-success fs-4"></i>
            </div>
            <div class="kpi-value">{{ number_format($horasReconocidas, 0) }}</div>
            <div class="kpi-label">Horas reconocidas</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-2">
            <div class="kpi-icon mx-auto mb-2" style="background:#fee2e2">
                <i class="bi bi-dash-circle-fill text-danger fs-4"></i>
            </div>
            <div class="kpi-value text-danger">{{ number_format($horasRestadas, 0) }}</div>
            <div class="kpi-label">Horas restadas</div>
            <div class="kpi-sub">Por no asistencia</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-3">
            <div class="kpi-icon mx-auto mb-2" style="background:#fef9c3">
                <i class="bi bi-calendar-x text-warning fs-4"></i>
            </div>
            <div class="kpi-value text-warning">{{ $turnosDescubiertos }}</div>
            <div class="kpi-label">Turnos descubiertos</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="kpi-card text-center fade-in fade-in-delay-3">
            <div class="kpi-icon mx-auto mb-2" style="background:#ede9fe">
                <i class="bi bi-clipboard2-pulse fs-4" style="color:#7c3aed"></i>
            </div>
            <div class="kpi-value" style="color:#7c3aed">{{ $novedadesMes }}</div>
            <div class="kpi-label">Novedades del mes</div>
        </div>
    </div>
</div>

{{-- KPI FILA 2: alertas y solicitudes --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center" style="border-left:4px solid #ef5350">
            <div class="kpi-icon mx-auto mb-2" style="background:#FFEBEE">
                <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
            </div>
            <div class="kpi-value text-danger">{{ $alertasAbiertas }}</div>
            <div class="kpi-label">Alertas abiertas</div>
            @if($alertasAbiertas > 0)
                <a href="{{ route('alertas.index') }}" class="btn btn-link btn-sm p-0 mt-1" style="font-size:.75rem">Ver →</a>
            @endif
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center" style="border-left:4px solid #f97316">
            <div class="kpi-icon mx-auto mb-2" style="background:#ffedd5">
                <i class="bi bi-clock-history fs-4" style="color:#ea580c"></i>
            </div>
            <div class="kpi-value" style="color:#ea580c">{{ $alertas12HHabil }}</div>
            <div class="kpi-label">Alertas +12h hábil</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center" style="border-left:4px solid #880E4F">
            <div class="kpi-icon mx-auto mb-2" style="background:#fce7f3">
                <i class="bi bi-person-x-fill fs-4" style="color:#be185d"></i>
            </div>
            <div class="kpi-value" style="color:#be185d">{{ $medicosExceso200 }}</div>
            <div class="kpi-label">Médicos > 200h</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center" style="border-left:4px solid #f59e0b">
            <div class="kpi-icon mx-auto mb-2" style="background:#FFF3E0">
                <i class="bi bi-arrow-left-right text-warning fs-4"></i>
            </div>
            <div class="kpi-value text-warning">{{ $cambiosPendientes }}</div>
            <div class="kpi-label">Cambios pendientes</div>
            @if($cambiosPendientes > 0)
                <a href="{{ route('cambios-turno.index') }}" class="btn btn-link btn-sm p-0 mt-1" style="font-size:.75rem">Revisar →</a>
            @endif
        </div>
    </div>
</div>

{{-- Gráficos --}}
<div class="row g-3 mb-4">
    {{-- Horas por UCI --}}
    <div class="col-lg-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Horas reconocidas por UCI</span>
            </div>
            <div class="panel-body">
                <canvas id="chartHorasUci" height="240"></canvas>
            </div>
        </div>
    </div>

    {{-- Distribución de turnos --}}
    <div class="col-lg-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Distribución de turnos</span>
            </div>
            <div class="panel-body d-flex flex-column align-items-center">
                <canvas id="chartTurnos" width="220" height="220"></canvas>
                <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                    @php $dist = $distribucionTurnos; $totalDist = $dist->sum(); @endphp
                    @foreach(['M'=>['#2196F3','Mañana'],'T'=>['#4CAF50','Tarde'],'MT'=>['#FF9800','M-Tarde'],'N'=>['#9C27B0','Noche'],'MTN'=>['#E91E63','MTN']] as $cod=>[$color,$lbl])
                    <div class="d-flex align-items-center gap-1 small">
                        <div style="width:11px;height:11px;border-radius:3px;background:{{ $color }}"></div>
                        <span class="text-muted">{{ $lbl }}</span>
                        <strong>{{ $dist[$cod] ?? 0 }}</strong>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Ranking de médicos --}}
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-trophy-fill me-2 text-warning"></i>Ranking de carga laboral — Top 10</span>
        <a href="{{ route('consolidado.index', ['mes'=>$mes,'anio'=>$anio]) }}" class="btn btn-sm btn-outline-secondary">
            Ver consolidado completo →
        </a>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr><th>#</th><th>Médico</th><th>Horas reconocidas</th><th>Progreso / 200h</th><th>Estado</th></tr>
                </thead>
                <tbody>
                    @forelse($rankingMedicos as $i => $m)
                    @php
                        $pct   = min(100, ($m['horas'] / 200) * 100);
                        $color = $m['horas'] > 200 ? 'danger' : ($m['horas'] > 150 ? 'warning' : 'success');
                    @endphp
                    <tr>
                        <td>
                            @if($i===0)<i class="bi bi-trophy-fill text-warning"></i>
                            @elseif($i===1)<i class="bi bi-trophy-fill text-secondary"></i>
                            @elseif($i===2)<i class="bi bi-trophy-fill" style="color:#cd7f32"></i>
                            @else<span class="text-muted">{{ $i+1 }}</span>@endif
                        </td>
                        <td class="fw-semibold">{{ $m['nombre'] }}</td>
                        <td><strong>{{ number_format($m['horas'], 1) }}h</strong></td>
                        <td style="min-width:140px">
                            <div class="progress">
                                <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                            </div>
                        </td>
                        <td>
                            @if($m['horas'] > 200)
                                <span class="badge bg-danger">Exceso</span>
                            @elseif($m['horas'] >= 100)
                                <span class="badge bg-success">Adecuado</span>
                            @else
                                <span class="badge bg-warning text-dark">Bajo</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">Sin datos para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Accesos rápidos --}}
<div class="row g-3">
    <div class="col-md-3">
        <a href="{{ route('novedades.index', ['mes'=>$mes,'anio'=>$anio]) }}" class="panel d-flex align-items-center gap-3 p-3 text-decoration-none">
            <div class="kpi-icon flex-shrink-0" style="background:#ede9fe"><i class="bi bi-clipboard2-pulse fs-4" style="color:#7c3aed"></i></div>
            <div><div class="fw-bold" style="color:#1a2340">Novedades</div><small class="text-muted">Gestionar novedades</small></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('secuencias.index') }}" class="panel d-flex align-items-center gap-3 p-3 text-decoration-none">
            <div class="kpi-icon flex-shrink-0" style="background:#dbeafe"><i class="bi bi-calendar-week text-primary fs-4"></i></div>
            <div><div class="fw-bold" style="color:#1a2340">Secuencias UCI</div><small class="text-muted">Plantillas de turnos</small></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('consolidado.index', ['mes'=>$mes,'anio'=>$anio]) }}" class="panel d-flex align-items-center gap-3 p-3 text-decoration-none">
            <div class="kpi-icon flex-shrink-0" style="background:#dcfce7"><i class="bi bi-bar-chart-line text-success fs-4"></i></div>
            <div><div class="fw-bold" style="color:#1a2340">Consolidado</div><small class="text-muted">Horas multi-UCI + Excel</small></div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="{{ route('alertas.index') }}" class="panel d-flex align-items-center gap-3 p-3 text-decoration-none">
            <div class="kpi-icon flex-shrink-0" style="background:#fee2e2"><i class="bi bi-exclamation-triangle text-danger fs-4"></i></div>
            <div><div class="fw-bold" style="color:#1a2340">Alertas</div>
            <small class="text-muted">{{ $alertasAbiertas }} abiertas</small></div>
        </a>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
// Chart: Horas por UCI
const uciData = @json($horasPorUci);
if (uciData.length && document.getElementById('chartHorasUci')) {
    new Chart(document.getElementById('chartHorasUci'), {
        type: 'bar',
        data: {
            labels: uciData.map(d => d.nombre.replace('UCI ','').replace('CARDIOVASCULAR','CARDIO')),
            datasets: [{
                label: 'Horas reconocidas',
                data: uciData.map(d => d.horas),
                backgroundColor: ['#1565C0','#1976D2','#1E88E5','#2196F3','#42A5F5','#64B5F6','#90CAF9','#1B5E20','#388E3C'],
                borderRadius: 8, borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y.toFixed(1)}h` } } },
            scales: {
                y: { grid: { color: '#f0f4f8' }, ticks: { font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
            }
        }
    });
}

// Chart: Distribución de turnos
const dist = @json($distribucionTurnos);
if (document.getElementById('chartTurnos')) {
    new Chart(document.getElementById('chartTurnos'), {
        type: 'doughnut',
        data: {
            labels: ['M','T','MT','N','MTN'],
            datasets: [{
                data: [dist.M??0, dist.T??0, dist.MT??0, dist.N??0, dist.MTN??0],
                backgroundColor: ['#2196F3','#4CAF50','#FF9800','#9C27B0','#E91E63'],
                borderWidth: 0, hoverOffset: 8,
            }]
        },
        options: { responsive: true, cutout: '60%', plugins: { legend: { display: false } } }
    });
}
</script>
@endpush
