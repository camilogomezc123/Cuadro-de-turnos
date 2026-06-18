@extends('layouts.app')
@section('title', 'Burnout – Panel Maestro')
@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-heart-pulse text-danger me-2"></i>Tamizaje de Desgaste Profesional</h4>
            <small class="text-muted">Resultados confidenciales — acceso exclusivo Maestro</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('burnout.preguntas') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-sliders me-1"></i>Configurar encuesta
            </a>
            <a href="{{ route('burnout.exportar', ['periodo'=>$periodo]) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if(!$encuesta)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No hay encuesta activa. Configure una en la sección de preguntas.</div>
    @else
        <div class="alert alert-info py-2">
            <i class="bi bi-info-circle me-2"></i>
            Encuesta activa: <strong>{{ $encuesta->nombre }}</strong> — Período actual: <strong>{{ $periodo }}</strong>
        </div>
    @endif

    {{-- Selector período --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <label class="fw-semibold small mb-0">Período:</label>
                <select name="periodo" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                    @foreach($periodos as $p)
                        <option value="{{ $p }}" @selected($p==$periodo)>{{ $p }}</option>
                    @endforeach
                    @if($periodos->isEmpty())<option value="{{ $periodo }}" selected>{{ $periodo }}</option>@endif
                </select>
            </form>
        </div>
    </div>

    {{-- KPIs principales --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary">{{ $evaluados }}<small class="fs-6 text-muted">/{{ $totalMedicos }}</small></div>
                <div class="small text-muted">Evaluados</div>
                <div class="text-muted" style="font-size:.7rem">{{ $pctRespuesta }}% de respuesta</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success">{{ $bajos }}</div>
                <div class="small text-muted">Riesgo bajo</div>
                <div class="text-muted" style="font-size:.7rem">{{ $evaluados > 0 ? round($bajos/$evaluados*100,1) : 0 }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">{{ $moderados }}</div>
                <div class="small text-muted">Riesgo moderado</div>
                <div class="text-muted" style="font-size:.7rem">{{ $evaluados > 0 ? round($moderados/$evaluados*100,1) : 0 }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-danger">{{ $altos }}</div>
                <div class="small text-muted">Riesgo alto</div>
                <div class="text-muted" style="font-size:.7rem">{{ $evaluados > 0 ? round($altos/$evaluados*100,1) : 0 }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #f59e0b !important">
                <div class="fs-2 fw-bold text-warning">{{ $positivos }}</div>
                <div class="small text-muted">Tamizaje positivo</div>
                <div class="text-muted" style="font-size:.7rem">{{ $evaluados > 0 ? round($positivos/$evaluados*100,1) : 0 }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="card border-0 shadow-sm text-center py-3" style="border-top:3px solid #dc3545 !important">
                <div class="fs-2 fw-bold text-danger">{{ $criticos }}</div>
                <div class="small text-muted">Alerta crítica</div>
                <div class="text-muted" style="font-size:.7rem">{{ $evaluados > 0 ? round($criticos/$evaluados*100,1) : 0 }}%</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">

        {{-- Promedio por pregunta --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 fw-bold"><i class="bi bi-bar-chart me-2 text-primary"></i>Promedio por pregunta</div>
                <div class="card-body p-0">
                    @if(empty($promediosPregunta))
                        <div class="text-center text-muted py-4">Sin respuestas en este período.</div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>#</th><th>Pregunta</th><th class="text-center">Promedio</th></tr></thead>
                            <tbody>
                                @foreach($promediosPregunta as $i => $p)
                                <tr>
                                    <td class="text-muted small">{{ $i+1 }}</td>
                                    <td class="small">{{ Str::limit($p['texto'], 60) }}</td>
                                    <td class="text-center">
                                        @php $pc = $p['prom']; $c = $pc >= 5 ? 'danger' : ($pc >= 3 ? 'warning' : 'success'); @endphp
                                        <span class="badge bg-{{ $c }}">{{ $p['prom'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Comparaciones --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 fw-bold"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Comparaciones</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="fw-semibold small text-muted mb-2">Por horas programadas</div>
                        <div class="d-flex gap-3">
                            <div class="flex-fill text-center p-2 rounded" style="background:#fff3cd">
                                <div class="fw-bold text-warning fs-5">{{ $comparHoras['mas200']['cnt'] }}</div>
                                <div class="small text-muted">Médicos +200h</div>
                                <div class="small text-danger">{{ $comparHoras['mas200']['positivos'] }} positivos</div>
                            </div>
                            <div class="flex-fill text-center p-2 rounded" style="background:#d1fae5">
                                <div class="fw-bold text-success fs-5">{{ $comparHoras['menos200']['cnt'] }}</div>
                                <div class="small text-muted">Médicos ≤200h</div>
                                <div class="small text-warning">{{ $comparHoras['menos200']['positivos'] }} positivos</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold small text-muted mb-2">Por turnos nocturnos (≥4 vs &lt;4)</div>
                        <div class="d-flex gap-3">
                            <div class="flex-fill text-center p-2 rounded" style="background:#ede9fe">
                                <div class="fw-bold fs-5" style="color:#7c3aed">{{ $comparNocturno['con']['cnt'] }}</div>
                                <div class="small text-muted">Con ≥4 nocturnos</div>
                                <div class="small text-danger">{{ $comparNocturno['con']['positivos'] }} positivos</div>
                            </div>
                            <div class="flex-fill text-center p-2 rounded" style="background:#f0f9ff">
                                <div class="fw-bold text-primary fs-5">{{ $comparNocturno['sin']['cnt'] }}</div>
                                <div class="small text-muted">Con &lt;4 nocturnos</div>
                                <div class="small text-warning">{{ $comparNocturno['sin']['positivos'] }} positivos</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tendencia mensual --}}
    @if($tendencia->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 fw-bold"><i class="bi bi-graph-up me-2 text-primary"></i>Tendencia mensual</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Período</th><th class="text-center">Evaluados</th><th class="text-center">Positivos</th><th class="text-center">Alertas críticas</th><th class="text-center">% positivo</th></tr></thead>
                    <tbody>
                        @foreach($tendencia as $t)
                        <tr>
                            <td>{{ $t->periodo_evaluado }}</td>
                            <td class="text-center">{{ $t->evaluados }}</td>
                            <td class="text-center"><span class="badge bg-warning text-dark">{{ $t->positivos }}</span></td>
                            <td class="text-center"><span class="badge bg-danger">{{ $t->criticos }}</span></td>
                            <td class="text-center">{{ $t->evaluados > 0 ? round($t->positivos/$t->evaluados*100,1) : 0 }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Por UCI --}}
    @if($porUci->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>Comparación por UCI</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>UCI</th><th class="text-center">Evaluados</th><th class="text-center">Positivos</th><th class="text-center">Alertas críticas</th></tr></thead>
                    <tbody>
                        @foreach($porUci as $nombre => $d)
                        <tr>
                            <td>{{ $nombre }}</td>
                            <td class="text-center">{{ $d['evaluados'] }}</td>
                            <td class="text-center">{{ $d['positivos'] }}</td>
                            <td class="text-center">{{ $d['criticos'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Alertas activas --}}
    @if($alertas->count())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger bg-opacity-10 border-0"><h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alertas activas</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Médico</th><th>Tipo</th><th>Descripción</th><th>Riesgo</th><th></th></tr></thead>
                    <tbody>
                        @foreach($alertas as $a)
                        <tr>
                            <td class="fw-semibold">{{ $a->medico?->nombre_completo ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ str_replace('_',' ', $a->tipo_alerta) }}</span></td>
                            <td class="small text-muted">{{ $a->descripcion }}</td>
                            <td><span class="badge bg-{{ $a->nivel_riesgo==='critico'?'danger':($a->nivel_riesgo==='alto'?'warning':'info') }}">{{ ucfirst($a->nivel_riesgo) }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('burnout.alertas.atender', $a) }}">@csrf
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-1">Atender</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Resultados individuales --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 bg-white"><h6 class="mb-0 fw-bold">Resultados individuales — {{ $periodo }}</h6></div>
        <div class="card-body p-0">
            @if($resultados->isEmpty())
                <div class="p-4 text-center text-muted">Sin resultados para este período.</div>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Médico</th><th>UCI</th><th class="text-center">Nivel</th><th class="text-center">Tamizaje</th><th class="text-center">Alerta crítica</th><th class="text-center">Horas</th><th class="text-center">Noct.</th></tr>
                    </thead>
                    <tbody>
                        @foreach($resultados as $r)
                        @php
                            $nivel = $r->burnout_severo ? 'alto' : ($r->burnout_positivo ? 'moderado' : 'bajo');
                            $clNivel = ['alto'=>'danger','moderado'=>'warning','bajo'=>'success'][$nivel];
                        @endphp
                        <tr class="{{ $r->burnout_severo ? 'table-danger' : ($r->burnout_positivo ? 'table-warning' : '') }}">
                            <td class="fw-semibold">{{ $r->medico?->nombre_completo ?? '—' }}</td>
                            <td class="small text-muted">{{ $r->medico?->uci?->nombre ?? '—' }}</td>
                            <td class="text-center"><span class="badge bg-{{ $clNivel }}">{{ ucfirst($nivel) }}</span></td>
                            <td class="text-center">{{ $r->burnout_positivo ? '<span class="badge bg-warning text-dark">Positivo</span>' : '<span class="text-muted small">—</span>' }}</td>
                            <td class="text-center">{{ $r->burnout_severo ? '<span class="badge bg-danger">Crítica</span>' : '<span class="text-muted small">—</span>' }}</td>
                            <td class="text-center {{ $r->supera_200h ? 'text-danger fw-bold' : '' }}">{{ $r->horas_programadas_mes }}h</td>
                            <td class="text-center">{{ $r->turnos_nocturnos }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>
@endsection
