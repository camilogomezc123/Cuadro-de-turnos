@extends('layouts.app')
@section('title','Consolidado Mensual')
@section('page-title','Consolidado Mensual')
@section('breadcrumb')
    <li class="breadcrumb-item active">Consolidado</li>
@endsection

@section('content')
<div class="fade-in">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <h5 class="mb-0 fw-bold" style="color:#1a2340">
        <i class="bi bi-bar-chart-line me-2 text-primary"></i>
        Consolidado — {{ $meses[$mes-1] }} {{ $anio }}
    </h5>
    <div class="d-flex gap-2">
        <a href="{{ route('consolidado.excel', ['mes'=>$mes,'anio'=>$anio]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Consolidado Excel
        </a>
        <a href="{{ route('consolidado.cuadro-excel', ['mes'=>$mes,'anio'=>$anio]) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-table me-1"></i>Cuadro de Turnos Excel
        </a>
    </div>
</div>

{{-- Selector de mes --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Mes</label>
                <select name="mes" class="form-select form-select-sm">
                    @foreach($meses as $i=>$m)
                        <option value="{{ $i+1 }}" @selected($mes==$i+1)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Año</label>
                <input type="number" name="anio" value="{{ $anio }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100">Ver</button>
            </div>
        </form>
    </div>
</div>

{{-- KPIs resumen --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#dbeafe"><i class="bi bi-clock text-primary fs-4"></i></div>
            <div class="kpi-value">{{ number_format($totalProgramadas,0) }}</div>
            <div class="kpi-label">Horas programadas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#dcfce7"><i class="bi bi-check-circle text-success fs-4"></i></div>
            <div class="kpi-value">{{ number_format($totalReconocidas,0) }}</div>
            <div class="kpi-label">Horas reconocidas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#fee2e2"><i class="bi bi-exclamation-triangle text-danger fs-4"></i></div>
            <div class="kpi-value">{{ $totalConExceso }}</div>
            <div class="kpi-label">Médicos > 200h</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#fef9c3"><i class="bi bi-clipboard2 text-warning fs-4"></i></div>
            <div class="kpi-value">{{ $totalNovedades }}</div>
            <div class="kpi-label">Novedades</div>
        </div>
    </div>
</div>

{{-- Tabla consolidado --}}
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Detalle por médico (horas en todas las UCIs)</span>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0" style="font-size:.82rem">
                <thead>
                    <tr>
                        <th>Médico</th>
                        <th>H.Prog</th><th>H.Recon</th>
                        <th>M</th><th>T</th><th>MT</th><th>N</th><th>MTN</th>
                        <th>Diurnas</th><th>Nocturnas</th>
                        <th>Domingos</th><th>F.Sem</th>
                        <th>UCIs</th><th>Estado</th>
                        <th>Alertas</th><th>Novedades</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consolidado as $row)
                    @php
                        $estado  = $row['estado_carga'];
                        $clRow   = $estado==='exceso' ? 'table-danger' : ($estado==='bajo' ? 'table-warning' : '');
                    @endphp
                    <tr class="{{ $clRow }}">
                        <td class="fw-bold">{{ $row['medico']->nombre_completo }}</td>
                        <td>{{ number_format($row['horas_programadas'],1) }}</td>
                        <td class="fw-bold">{{ number_format($row['horas_reconocidas'],1) }}</td>
                        <td>{{ $row['turnos_M'] }}</td>
                        <td>{{ $row['turnos_T'] }}</td>
                        <td>{{ $row['turnos_MT'] }}</td>
                        <td>{{ $row['turnos_N'] }}</td>
                        <td>{{ $row['turnos_MTN'] }}</td>
                        <td>{{ number_format($row['horas_diurnas'],1) }}</td>
                        <td>{{ number_format($row['horas_nocturnas'],1) }}</td>
                        <td>{{ $row['total_domingos'] }}</td>
                        <td>{{ $row['total_fines_semana'] }}</td>
                        <td>{{ $row['ucis_trabajadas'] }}</td>
                        <td>
                            @if($estado==='exceso')
                                <span class="badge bg-danger">Exceso</span>
                            @elseif($estado==='bajo')
                                <span class="badge bg-warning text-dark">Bajo</span>
                            @else
                                <span class="badge bg-success">Adecuado</span>
                            @endif
                        </td>
                        <td>
                            @if($row['alertas']>0)
                                <span class="badge bg-danger">{{ $row['alertas'] }}</span>
                            @else — @endif
                        </td>
                        <td>
                            @if($row['novedades']>0)
                                <span class="badge bg-warning text-dark">{{ $row['novedades'] }}</span>
                            @else — @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="16" class="text-center text-muted py-4">Sin datos para este período.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="fw-bold" style="background:#f0f4f8">
                        <td>TOTALES</td>
                        <td>{{ number_format($totalProgramadas,1) }}</td>
                        <td>{{ number_format($totalReconocidas,1) }}</td>
                        <td colspan="14"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

</div>
@endsection
