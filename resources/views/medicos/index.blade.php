@extends('layouts.app')

@section('title', 'Médicos')
@section('page-title', 'Médicos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Médicos</li>
@endsection

@section('content')
{{-- FILTROS --}}
<div class="panel mb-3">
    <div class="panel-body py-3">
        <form method="GET" action="{{ route('medicos.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Mes</label>
                <select name="mes" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($nombresMeses as $i => $nombre)
                        <option value="{{ $i+1 }}" @selected($mes == $i+1)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Año</label>
                <select name="anio" class="form-select form-select-sm" onchange="this.form.submit()">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" @selected($anio == $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas las UCIs</option>
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" {{ $u->id == $uciId ? 'selected' : '' }}>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <a href="{{ route('medicos.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

{{-- TABLA DE MÉDICOS --}}
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-person-badge me-2 text-primary"></i>Listado de Médicos — {{ $nombresMeses[$mes-1] }} {{ $anio }}</span>
        <span class="badge bg-primary-subtle text-primary">{{ $medicos->count() }} médicos</span>
    </div>
    <div class="panel-body p-0">
        @if($medicos->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-person-x fs-1 d-block mb-2 opacity-25"></i>
                No hay médicos para los filtros seleccionados.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Médico</th>
                        <th>UCI principal</th>
                        <th class="text-end">Total Horas</th>
                        <th class="text-end">H. Noct.</th>
                        <th class="text-end">Turnos</th>
                        <th style="width:120px">Carga</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($medicos as $medico)
                    @php
                        $h   = $horasPorMedico->get($medico->id);
                        $tot = $h?->total_horas ?? 0;
                        $pct = min(100, round($tot / 200 * 100));
                        $color = $tot > 200 ? 'danger' : ($tot < 80 ? 'warning' : 'success');
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $medico->nombre_completo }}</td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary" style="font-size:.7rem">
                                {{ Str::limit($medico->uci?->nombre ?? '—', 22) }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if($tot > 0)
                                <span class="fw-bold text-{{ $color }}">{{ number_format($tot, 0) }}h</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end" style="color:#7C3AED">
                            {{ $h ? number_format($h->horas_nocturnas, 0).'h' : '—' }}
                        </td>
                        <td class="text-end">{{ $h?->total_turnos ?? '—' }}</td>
                        <td>
                            @if($tot > 0)
                            <div class="progress" style="height:6px">
                                <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                            </div>
                            <div class="text-muted" style="font-size:.65rem">{{ $pct }}% de 200h</div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('medicos.show', ['medico' => $medico->id, 'mes' => $mes, 'anio' => $anio]) }}"
                               class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
