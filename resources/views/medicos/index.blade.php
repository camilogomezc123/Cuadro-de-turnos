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
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Período</label>
                <select name="archivo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($archivos as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                            {{ $a->nombre_mes }} {{ $a->anio }}
                        </option>
                    @endforeach
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
            <div class="col-md-4">
                <input type="hidden" name="archivo_id" value="{{ $archivoId }}">
                <a href="{{ route('medicos.index', ['archivo_id' => $archivoId]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpiar filtros
                </a>
            </div>
        </form>
    </div>
</div>

{{-- TABLA DE MÉDICOS --}}
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-person-badge me-2 text-primary"></i>Listado de Médicos</span>
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
            <table class="table table-custom mb-0" id="tablaMedicos">
                <thead>
                    <tr>
                        <th>Médico</th>
                        <th>UCI</th>
                        <th>Total Horas</th>
                        <th>H. Diurnas</th>
                        <th>H. Nocturnas</th>
                        <th>Turnos M</th>
                        <th>Turnos T</th>
                        <th>Turnos MT</th>
                        <th>Turnos N</th>
                        <th>% Ocup.</th>
                        <th>Carga</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($medicos as $medico)
                    @php $ind = $medico->indicadores->first(); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $medico->nombre }}</td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary" style="font-size:.7rem">
                                {{ Str::limit($medico->uci->nombre ?? '-', 20) }}
                            </span>
                        </td>
                        <td>{{ $ind ? number_format($ind->total_horas, 1) : '-' }}</td>
                        <td class="text-info">{{ $ind ? number_format($ind->horas_diurnas, 1) : '-' }}</td>
                        <td class="text-purple" style="color:#7C3AED">{{ $ind ? number_format($ind->horas_nocturnas, 1) : '-' }}</td>
                        <td><span class="badge-M turno-badge">{{ $ind->turnos_m ?? 0 }}</span></td>
                        <td><span class="badge-T turno-badge">{{ $ind->turnos_t ?? 0 }}</span></td>
                        <td><span class="badge-MT turno-badge">{{ $ind->turnos_mt ?? 0 }}</span></td>
                        <td><span class="badge-N turno-badge">{{ $ind->turnos_n ?? 0 }}</span></td>
                        <td>
                            @if($ind)
                                <span class="{{ $ind->porcentaje_ocupacion > 50 ? 'text-danger fw-bold' : 'text-success' }}">
                                    {{ number_format($ind->porcentaje_ocupacion, 1) }}%
                                </span>
                            @else -
                            @endif
                        </td>
                        <td style="width:100px">
                            @if($ind)
                            <div class="progress">
                                <div class="progress-bar {{ $ind->porcentaje_ocupacion > 50 ? 'bg-danger' : 'bg-success' }}"
                                     style="width:{{ min($ind->porcentaje_ocupacion, 100) }}%"></div>
                            </div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('medicos.show', ['medico' => $medico->id, 'archivo_id' => $archivoId]) }}"
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
