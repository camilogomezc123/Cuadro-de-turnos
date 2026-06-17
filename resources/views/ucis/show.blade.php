@extends('layouts.app')

@section('title', $uci->nombre)
@section('page-title', $uci->nombre)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('ucis.index') }}">UCIs</a></li>
    <li class="breadcrumb-item active">{{ $uci->nombre }}</li>
@endsection

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <form method="GET" class="d-flex align-items-center gap-2">
        <select name="archivo_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            @foreach($archivos as $a)
                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>{{ $a->nombre_mes }} {{ $a->anio }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('ucis.index', ['archivo_id' => $archivoId]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

@if($indicador)
<div class="row g-3 mb-4">
    @php $kpis = [
        ['Especialistas', $indicador->num_especialistas, 'bi-people-fill', '#E3F2FD', '#1565C0'],
        ['Horas Totales', number_format($indicador->horas_totales, 1), 'bi-clock-fill', '#E8F5E9', '#2E7D32'],
        ['Prom/Médico', number_format($indicador->horas_promedio_medico, 1).'h', 'bi-graph-up', '#FFF8E1', '#E65100'],
        ['Cobertura Mensual', number_format($indicador->cobertura_mensual, 1).'%', 'bi-calendar-check-fill', '#E0F7FA', '#0277BD'],
        ['Cob. Fin Semana', number_format($indicador->cobertura_fin_semana, 1).'%', 'bi-calendar-heart-fill', '#FCE4EC', '#C62828'],
        ['Cob. Nocturna', number_format($indicador->cobertura_nocturna, 1).'%', 'bi-moon-stars-fill', '#EDE7F6', '#4527A0'],
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
                            <div class="fw-bold text-primary">{{ number_format($indicador->carga_diurna_pct, 1) }}%</div>
                            <div class="text-muted small">Diurna</div>
                        </div>
                        <div>
                            <div class="fw-bold" style="color:#7C3AED">{{ number_format($indicador->carga_nocturna_pct, 1) }}%</div>
                            <div class="text-muted small">Nocturna</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Médicos de la UCI --}}
    <div class="col-md-8">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-person-badge me-2 text-success"></i>Médicos de la UCI</span>
                <span class="badge bg-success-subtle text-success">{{ $medicos->count() }}</span>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr><th>Médico</th><th>Horas</th><th>H. Noct.</th><th>Turnos N</th><th>F/S</th><th>% Ocup.</th></tr>
                        </thead>
                        <tbody>
                            @foreach($medicos as $m)
                            <tr>
                                <td>
                                    <a href="{{ route('medicos.show', ['medico' => $m->medico_id, 'archivo_id' => $archivoId]) }}"
                                       class="fw-semibold text-decoration-none text-dark">
                                        {{ $m->medico->nombre }}
                                    </a>
                                </td>
                                <td><strong>{{ number_format($m->total_horas, 1) }}</strong></td>
                                <td style="color:#7C3AED">{{ number_format($m->horas_nocturnas, 1) }}</td>
                                <td><span class="badge-N turno-badge">{{ $m->turnos_n }}</span></td>
                                <td>{{ $m->turnos_fin_semana }}</td>
                                <td>
                                    <div class="progress" style="width:80px">
                                        <div class="progress-bar bg-primary" style="width:{{ min($m->porcentaje_ocupacion, 100) }}%"></div>
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
</div>
@else
<div class="panel p-5 text-center text-muted">
    <i class="bi bi-info-circle fs-1 opacity-25 d-block mb-2"></i>
    No hay indicadores para esta UCI en el período seleccionado.
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
                    <tr><th>Período</th><th>Especialistas</th><th>Horas Totales</th><th>Cob. Mensual</th><th>Cob. Nocturna</th></tr>
                </thead>
                <tbody>
                    @foreach($historial as $h)
                    <tr>
                        <td>{{ ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$h->mes] }} {{ $h->anio }}</td>
                        <td>{{ $h->num_especialistas }}</td>
                        <td>{{ number_format($h->horas_totales, 1) }}</td>
                        <td>{{ number_format($h->cobertura_mensual, 1) }}%</td>
                        <td>{{ number_format($h->cobertura_nocturna, 1) }}%</td>
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
@if($indicador)
new Chart(document.getElementById('chartCarga'), {
    type: 'doughnut',
    data: {
        labels: ['Diurna', 'Nocturna'],
        datasets: [{
            data: [{{ $indicador->carga_diurna_pct }}, {{ $indicador->carga_nocturna_pct }}],
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
