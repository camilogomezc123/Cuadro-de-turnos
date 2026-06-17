@extends('layouts.app')
@section('title', 'Cambios de Turno')
@section('page-title', 'Cambios de Turno')
@section('breadcrumb')
    <li class="breadcrumb-item active">Cambios de Turno</li>
@endsection
@section('content')

<div class="row g-4">
    {{-- Solicitar cambio --}}
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Solicitar Cambio</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('cambios-turno.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Período</label>
                        <select name="_archivo_id" class="form-select form-select-sm" id="selectArchivo" onchange="filtrarTurnos()">
                            @foreach($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>{{ $a->nombre_mes }} {{ $a->anio }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mi turno (origen)</label>
                        <select name="turno_origen_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar turno —</option>
                            @foreach($turnos as $t)
                                <option value="{{ $t->id }}">{{ $t->medico->nombre }} · {{ $t->fecha->format('d/m') }} · {{ $t->codigo_turno }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Médico receptor</label>
                        <select name="medico_receptor_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar médico —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Motivo</label>
                        <textarea name="motivo" class="form-control form-control-sm" rows="3"
                                  placeholder="Explique el motivo del cambio..." required minlength="10"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-send me-1"></i>Enviar solicitud
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listado de solicitudes --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-arrow-left-right me-2"></i>Solicitudes de Cambio</span>
            </div>
            {{-- Filtros --}}
            <div class="panel-body border-bottom py-2">
                <form method="GET" class="d-flex gap-2">
                    <select name="archivo_id" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                        @foreach($archivos as $a)<option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected':'' }}>{{ $a->nombre_mes }} {{ $a->anio }}</option>@endforeach
                    </select>
                    <select name="estado" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                        <option value="">Todo estado</option>
                        <option value="pendiente" {{ $estado=='pendiente'?'selected':'' }}>Pendiente</option>
                        <option value="aceptado_colega" {{ $estado=='aceptado_colega'?'selected':'' }}>Aceptado colega</option>
                        <option value="aprobado_coordinador" {{ $estado=='aprobado_coordinador'?'selected':'' }}>Aprobado</option>
                        <option value="rechazado_coordinador" {{ $estado=='rechazado_coordinador'?'selected':'' }}>Rechazado</option>
                    </select>
                </form>
            </div>
            <div class="panel-body p-0">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr><th>Solicitante</th><th>Turno origen</th><th>Receptor</th><th>Estado</th><th>Fecha</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($solicitudes as $s)
                        <tr>
                            <td class="small fw-semibold">{{ $s->medicoSolicitante->nombre }}</td>
                            <td class="small">
                                {{ $s->turnoOrigen?->fecha?->format('d/m') }}
                                <span class="turno-badge badge-{{ $s->turnoOrigen?->codigo_turno }}">{{ $s->turnoOrigen?->codigo_turno }}</span>
                                <div class="text-muted" style="font-size:10px">{{ $s->turnoOrigen?->uci?->codigo }}</div>
                            </td>
                            <td class="small">{{ $s->medicoReceptor->nombre }}</td>
                            <td>
                                <span class="badge bg-{{ $s->badge_estado }}-subtle text-{{ $s->badge_estado }}">
                                    {{ $s->label_estado }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $s->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($s->estado === 'pendiente')
                                        <form method="POST" action="{{ route('cambios-turno.aceptar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-success btn-sm" title="Aceptar"><i class="bi bi-check2"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('cambios-turno.rechazar-colega', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-danger btn-sm" title="Rechazar"><i class="bi bi-x"></i></button>
                                        </form>
                                    @elseif($s->estado === 'aceptado_colega')
                                        <form method="POST" action="{{ route('cambios-turno.aprobar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-primary btn-sm" title="Aprobar (coordinador)">
                                                <i class="bi bi-check-all"></i> Aprobar
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cambios-turno.rechazar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-outline-danger btn-sm">Rechazar</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay solicitudes de cambio</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $solicitudes->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
