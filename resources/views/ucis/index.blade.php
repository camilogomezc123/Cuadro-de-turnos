@extends('layouts.app')

@section('title', 'UCIs')
@section('page-title', 'Unidades de Cuidado Intensivo')
@section('breadcrumb')
    <li class="breadcrumb-item active">UCIs</li>
@endsection

@section('content')
{{-- SELECTOR PERÍODO --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <form method="GET" action="{{ route('ucis.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
        <label class="fw-semibold text-secondary small">Período:</label>
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
</div>

<div class="row g-3">
    @foreach($ucis as $uci)
    @php
        $ind   = $indicadores->get($uci->id);
        $cob   = $coberturaPorUci->get($uci->id);
        $diasCubiertos = $cob?->dias_cubiertos ?? 0;
        $pctCobertura  = $diasMes > 0 ? round($diasCubiertos / $diasMes * 100, 1) : 0;
        $totalHoras    = $ind?->total_horas ?? 0;
        $totalMedicos  = $ind?->total_medicos ?? 0;
        $horasNocturnas= $ind?->total_horas_nocturnas ?? 0;
        $promHoras     = $totalMedicos > 0 ? round($totalHoras / $totalMedicos, 1) : 0;
        $pctNocturna   = $totalHoras > 0 ? round($horasNocturnas / $totalHoras * 100, 1) : 0;
    @endphp
    <div class="col-md-6 col-lg-4">
        <a href="{{ route('ucis.show', ['uci' => $uci->id, 'mes' => $mes, 'anio' => $anio]) }}" class="text-decoration-none">
            <div class="panel h-100" style="transition:transform .2s,box-shadow .2s"
                 onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.12)'"
                 onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div class="panel-header" style="background:linear-gradient(135deg,#1565C0,#1E88E5);border-radius:13px 13px 0 0">
                    <span class="panel-title text-white">
                        <i class="bi bi-building-fill-cross me-2"></i>{{ $uci->nombre }}
                    </span>
                    @if($totalMedicos > 0)
                    <span class="badge bg-white text-primary fw-bold">{{ $totalMedicos }} méd.</span>
                    @endif
                </div>
                <div class="panel-body">
                    @if($ind && $totalHoras > 0)
                    <div class="row g-2 text-center mb-3">
                        <div class="col-4">
                            <div class="fw-bold text-primary">{{ number_format($totalHoras, 0) }}</div>
                            <div class="text-muted" style="font-size:.7rem">Horas Tot.</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-success">{{ $promHoras }}</div>
                            <div class="text-muted" style="font-size:.7rem">Prom/Médico</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold text-warning">{{ $pctCobertura }}%</div>
                            <div class="text-muted" style="font-size:.7rem">Cobertura</div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Cobertura días</span>
                            <span class="fw-semibold">{{ $diasCubiertos }}/{{ $diasMes }} días</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width:{{ $pctCobertura }}%"></div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Carga nocturna</span>
                            <span class="fw-semibold">{{ $pctNocturna }}%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width:{{ $pctNocturna }}%;background:#7C3AED"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <div class="flex-fill text-center p-2 rounded-2" style="background:#f0f8ff">
                            <div class="fw-bold text-primary small">{{ $ind->total_turnos }}</div>
                            <div class="text-muted" style="font-size:.65rem">Turnos</div>
                        </div>
                        <div class="flex-fill text-center p-2 rounded-2" style="background:#f3f0ff">
                            <div class="fw-bold small" style="color:#7C3AED">{{ number_format($horasNocturnas, 0) }}</div>
                            <div class="text-muted" style="font-size:.65rem">H. Noct.</div>
                        </div>
                    </div>
                    @else
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-inbox opacity-25 fs-2 d-block"></i>
                        Sin datos para {{ $nombresMeses[$mes-1] }} {{ $anio }}
                    </div>
                    @endif
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>
@endsection
