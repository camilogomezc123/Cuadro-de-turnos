@extends('layouts.app')

@section('title', 'UCIs')
@section('page-title', 'Unidades de Cuidado Intensivo')
@section('breadcrumb')
    <li class="breadcrumb-item active">UCIs</li>
@endsection

@section('content')
{{-- SELECTOR PERÍODO --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <form method="GET" action="{{ route('ucis.index') }}" class="d-flex align-items-center gap-2">
        <label class="fw-semibold text-secondary small">Período:</label>
        <select name="archivo_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            @foreach($archivos as $a)
                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                    {{ $a->nombre_mes }} {{ $a->anio }}
                </option>
            @endforeach
        </select>
    </form>
</div>

@php $indPorUci = $indicadores->keyBy('uci_id'); @endphp

<div class="row g-3">
    @foreach($ucis as $uci)
    @php $ind = $indPorUci->get($uci->id); @endphp
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('ucis.show', ['uci' => $uci->id, 'archivo_id' => $archivoId]) }}" class="text-decoration-none">
            <div class="panel h-100 hover-lift" style="transition:transform .2s,box-shadow .2s"
                 onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.12)'"
                 onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div class="panel-header" style="background:linear-gradient(135deg,#1565C0,#1E88E5);border-radius:13px 13px 0 0">
                    <span class="panel-title text-white">
                        <i class="bi bi-building-fill-cross me-2"></i>{{ $uci->nombre }}
                    </span>
                    @if($ind)
                    <span class="badge bg-white text-primary fw-bold">{{ $ind->num_especialistas }} esp.</span>
                    @endif
                </div>
                <div class="panel-body">
                    @if($ind)
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="fw-bold text-primary">{{ number_format($ind->horas_totales, 0) }}</div>
                            <div class="text-muted" style="font-size:.7rem">Horas Tot.</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success">{{ number_format($ind->horas_promedio_medico, 1) }}</div>
                            <div class="text-muted" style="font-size:.7rem">Prom/Médico</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-warning">{{ number_format($ind->cobertura_mensual, 1) }}%</div>
                            <div class="text-muted" style="font-size:.7rem">Cobertura</div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Cobertura mensual</span>
                            <span class="fw-semibold">{{ number_format($ind->cobertura_mensual, 1) }}%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width:{{ $ind->cobertura_mensual }}%"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Cobertura nocturna</span>
                            <span class="fw-semibold">{{ number_format($ind->cobertura_nocturna, 1) }}%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-purple" style="width:{{ $ind->cobertura_nocturna }}%;background:#7C3AED"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Cobertura fin de semana</span>
                            <span class="fw-semibold">{{ number_format($ind->cobertura_fin_semana, 1) }}%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" style="width:{{ $ind->cobertura_fin_semana }}%"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <div class="flex-fill text-center p-2 rounded-2" style="background:#f0f8ff">
                            <div class="fw-bold text-primary small">{{ number_format($ind->carga_diurna_pct, 0) }}%</div>
                            <div class="text-muted" style="font-size:.65rem">Diurna</div>
                        </div>
                        <div class="flex-fill text-center p-2 rounded-2" style="background:#f3f0ff">
                            <div class="fw-bold small" style="color:#7C3AED">{{ number_format($ind->carga_nocturna_pct, 0) }}%</div>
                            <div class="text-muted" style="font-size:.65rem">Nocturna</div>
                        </div>
                    </div>
                    @else
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-inbox opacity-25 fs-2 d-block"></i>
                        Sin datos para el período
                    </div>
                    @endif
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection
