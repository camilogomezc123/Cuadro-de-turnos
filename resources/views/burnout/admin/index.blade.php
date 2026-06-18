@extends('layouts.app')

@section('title', 'Burnout – Panel Maestro')

@section('content')
<div class="container-fluid py-4">

    {{-- Encabezado --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-heart-pulse text-danger me-2"></i>Evaluación de Burnout Profesional</h4>
            <small class="text-muted">Resultados confidenciales — acceso exclusivo Maestro</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('burnout.preguntas') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-sliders me-1"></i>Gestionar preguntas
            </a>
            <a href="{{ route('burnout.exportar', ['periodo'=>$periodo]) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Estado encuesta --}}
    @if(!$encuesta)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>No hay encuesta activa. Configure una en la sección de preguntas.
        </div>
    @else
        <div class="alert alert-info d-flex align-items-center gap-3">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                Encuesta activa: <strong>{{ $encuesta->nombre }}</strong> —
                Periodicidad: <strong>{{ ucfirst($encuesta->periodo) }}</strong> —
                Período actual: <strong>{{ $periodo }}</strong>
            </div>
        </div>
    @endif

    {{-- Selector de período --}}
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-1">Período evaluado</label>
                    <select name="periodo" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($periodos as $p)
                            <option value="{{ $p }}" @selected($p==$periodo)>{{ $p }}</option>
                        @endforeach
                        @if($periodos->isEmpty())
                            <option value="{{ $periodo }}" selected>{{ $periodo }}</option>
                        @endif
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary">{{ $agregado['evaluados'] }}<small class="fs-6 text-muted">/{{ $totalMedicos }}</small></div>
                <div class="text-muted small">Respondieron</div>
                <div class="text-muted smaller">{{ $agregado['pct_respondio'] }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-warning">{{ $agregado['burnout_positivos'] }}</div>
                <div class="text-muted small">Burnout positivo</div>
                <div class="text-muted smaller">{{ $agregado['pct_positivo'] }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-danger">{{ $agregado['burnout_severos'] }}</div>
                <div class="text-muted small">Burnout severo</div>
                <div class="text-muted smaller">{{ $agregado['pct_severo'] }}%</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-secondary">{{ $alertas->count() }}</div>
                <div class="text-muted small">Alertas activas</div>
            </div>
        </div>
    </div>

    {{-- Promedios por dimensión --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1">Agotamiento Emocional (AE)</div>
                    <div class="fs-4 fw-bold {{ $agregado['prom_ae'] >= 27 ? 'text-danger' : ($agregado['prom_ae'] >= 19 ? 'text-warning' : 'text-success') }}">
                        {{ $agregado['prom_ae'] }}
                    </div>
                    <small class="text-muted">Umbral alto ≥ 27</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1">Despersonalización (DP)</div>
                    <div class="fs-4 fw-bold {{ $agregado['prom_dp'] >= 10 ? 'text-danger' : ($agregado['prom_dp'] >= 6 ? 'text-warning' : 'text-success') }}">
                        {{ $agregado['prom_dp'] }}
                    </div>
                    <small class="text-muted">Umbral alto ≥ 10</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small mb-1">Realización Personal (RP)</div>
                    <div class="fs-4 fw-bold {{ $agregado['prom_rp'] <= 33 ? 'text-danger' : ($agregado['prom_rp'] <= 39 ? 'text-warning' : 'text-success') }}">
                        {{ $agregado['prom_rp'] }}
                    </div>
                    <small class="text-muted">Umbral bajo ≤ 33</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Alertas activas --}}
    @if($alertas->count())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger bg-opacity-10 border-0">
            <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alertas activas</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr>
                        <th>Médico</th><th>Tipo</th><th>Descripción</th><th>Riesgo</th><th></th>
                    </tr></thead>
                    <tbody>
                        @foreach($alertas as $a)
                        <tr>
                            <td>{{ $a->medico?->nombre_completo ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ str_replace('_',' ', $a->tipo_alerta) }}</span></td>
                            <td class="text-muted small">{{ $a->descripcion }}</td>
                            <td>
                                @php $color = match($a->nivel_riesgo) { 'critico'=>'danger','alto'=>'warning','medio'=>'info', default=>'secondary' }; @endphp
                                <span class="badge bg-{{ $color }}">{{ ucfirst($a->nivel_riesgo) }}</span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('burnout.alertas.atender', $a) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-secondary btn-sm py-0 px-1">Atender</button>
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

    {{-- Tabla de resultados individuales --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 bg-white">
            <h6 class="mb-0 fw-bold">Resultados individuales — {{ $periodo }}</h6>
        </div>
        <div class="card-body p-0">
            @if($resultados->isEmpty())
                <div class="p-4 text-center text-muted">Sin resultados para este período.</div>
            @else
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Médico</th><th>UCI</th>
                            <th class="text-center">AE</th>
                            <th class="text-center">DP</th>
                            <th class="text-center">RP</th>
                            <th class="text-center">Horas</th>
                            <th class="text-center">Noct.</th>
                            <th class="text-center">Burnout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resultados as $r)
                        <tr class="{{ $r->burnout_severo ? 'table-danger' : ($r->burnout_positivo ? 'table-warning' : '') }}">
                            <td class="fw-semibold">{{ $r->medico?->nombre_completo ?? '—' }}</td>
                            <td class="text-muted small">{{ $r->medico?->uci?->nombre ?? '—' }}</td>
                            <td class="text-center">
                                <span class="badge bg-{{ $r->badge_ae }}">{{ $r->puntaje_agotamiento_emocional }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $r->badge_dp }}">{{ $r->puntaje_despersonalizacion }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $r->badge_rp }}">{{ $r->puntaje_realizacion_personal }}</span>
                            </td>
                            <td class="text-center">
                                <span class="{{ $r->supera_200h ? 'text-danger fw-bold' : '' }}">{{ $r->horas_programadas_mes }}h</span>
                            </td>
                            <td class="text-center">{{ $r->turnos_nocturnos }}</td>
                            <td class="text-center">
                                @if($r->burnout_severo)
                                    <span class="badge bg-danger">SEVERO</span>
                                @elseif($r->burnout_positivo)
                                    <span class="badge bg-warning text-dark">POSITIVO</span>
                                @else
                                    <span class="badge bg-success">Normal</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Por UCI --}}
    @if($porUci->isNotEmpty())
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header border-0 bg-white">
            <h6 class="mb-0 fw-bold">Resumen por UCI</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>UCI</th><th class="text-center">Evaluados</th><th class="text-center">B. Positivo</th><th class="text-center">B. Severo</th></tr></thead>
                    <tbody>
                        @foreach($porUci as $nombre => $datos)
                        <tr>
                            <td>{{ $nombre }}</td>
                            <td class="text-center">{{ $datos['total'] }}</td>
                            <td class="text-center">{{ $datos['burnout_positivo'] }}</td>
                            <td class="text-center">{{ $datos['burnout_severo'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
