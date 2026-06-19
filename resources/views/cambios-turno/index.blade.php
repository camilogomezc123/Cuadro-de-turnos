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
                @if(session('error'))
                    <div class="alert alert-danger alert-sm py-2 small">{{ session('error') }}</div>
                @endif
                @if(session('success'))
                    <div class="alert alert-success alert-sm py-2 small">{{ session('success') }}</div>
                @endif
                <form method="POST" action="{{ route('cambios-turno.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Período</label>
                        <select name="_archivo_id" class="form-select form-select-sm" id="selectArchivo">
                            @foreach($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mi turno (origen)</label>
                        <select name="turno_origen_id" class="form-select form-select-sm" id="selectTurnoOrigen" required>
                            <option value="">— Cargando turnos... —</option>
                        </select>
                        <div id="turnosSpinner" class="text-muted small mt-1" style="display:none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Cargando...
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Médico receptor</label>
                        <select name="medico_receptor_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar médico —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
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
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="archivo_id" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                        @foreach($archivos as $a)
                            <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected':'' }}>
                                {{ $a->nombre_mes }} {{ $a->anio }}
                            </option>
                        @endforeach
                    </select>
                    <select name="estado" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                        <option value="">Todo estado</option>
                        <option value="pendiente"             {{ $estado=='pendiente'?'selected':'' }}>Pendiente</option>
                        <option value="aceptado_colega"       {{ $estado=='aceptado_colega'?'selected':'' }}>Aceptado colega</option>
                        <option value="aprobado_coordinador"  {{ $estado=='aprobado_coordinador'?'selected':'' }}>Aprobado</option>
                        <option value="rechazado_coordinador" {{ $estado=='rechazado_coordinador'?'selected':'' }}>Rechazado</option>
                        <option value="rechazado_colega"      {{ $estado=='rechazado_colega'?'selected':'' }}>Rechazado colega</option>
                    </select>
                </form>
            </div>
            <div class="panel-body p-0">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Solicitante</th>
                            <th>Turno origen</th>
                            <th>Receptor</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($solicitudes as $s)
                        <tr>
                            <td class="small fw-semibold">{{ $s->medicoSolicitante?->nombre_completo ?? '—' }}</td>
                            <td class="small">
                                {{ $s->turnoOrigen?->fecha?->format('d/m') }}
                                <span class="turno-badge badge-{{ $s->turnoOrigen?->codigo_turno }}">{{ $s->turnoOrigen?->codigo_turno }}</span>
                                <div class="text-muted" style="font-size:10px">{{ $s->turnoOrigen?->uci?->codigo }}</div>
                            </td>
                            <td class="small">{{ $s->medicoReceptor?->nombre_completo ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $s->badge_estado }}-subtle text-{{ $s->badge_estado }}">
                                    {{ $s->label_estado }}
                                </span>
                            </td>
                            <td class="small text-muted">{{ $s->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if($s->estado === 'pendiente')
                                        {{-- Solo el receptor (o maestro) ve los botones de aceptar/rechazar --}}
                                        @if($esMaestro || (auth()->user()->medico_id && $s->medico_receptor_id == auth()->user()->medico_id))
                                        <form method="POST" action="{{ route('cambios-turno.aceptar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-success btn-sm" title="Aceptar">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cambios-turno.rechazar-colega', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-danger btn-sm" title="Rechazar">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </form>
                                        @else
                                            <span class="small text-muted">Esperando respuesta</span>
                                        @endif
                                    @elseif($s->estado === 'aceptado_colega' && $esMaestro)
                                        <form method="POST" action="{{ route('cambios-turno.aprobar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-primary btn-sm">
                                                <i class="bi bi-check-all"></i> Aprobar
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('cambios-turno.rechazar', $s) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-outline-danger btn-sm">Rechazar</button>
                                        </form>
                                    @elseif($s->estado === 'aceptado_colega' && !$esMaestro)
                                        <span class="small text-muted">Pendiente coordinador</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay solicitudes de cambio</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-4 py-3">{{ $solicitudes->links() }}</div>
            </div>
        </div>
    </div>
</div>

<script>
const misTurnosUrl = '{{ route("cambios-turno.mis-turnos") }}';
const archivoActual = {{ $archivoId }};

function cargarMisTurnos(archivoId) {
    const select = document.getElementById('selectTurnoOrigen');
    const spinner = document.getElementById('turnosSpinner');

    select.innerHTML = '<option value="">— Cargando... —</option>';
    select.disabled = true;
    spinner.style.display = '';

    fetch(misTurnosUrl + '?archivo_id=' + archivoId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(turnos => {
        select.innerHTML = '';
        if (turnos.length === 0) {
            select.innerHTML = '<option value="">— Sin turnos en este período —</option>';
        } else {
            select.innerHTML = '<option value="">— Seleccionar turno —</option>';
            turnos.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.fecha + ' · ' + t.label;
                select.appendChild(opt);
            });
        }
    })
    .catch(() => {
        select.innerHTML = '<option value="">— Error al cargar turnos —</option>';
    })
    .finally(() => {
        select.disabled = false;
        spinner.style.display = 'none';
    });
}

// Al cambiar el período, recargar los turnos automáticamente
document.getElementById('selectArchivo').addEventListener('change', function () {
    cargarMisTurnos(this.value);
});

// Cargar turnos del período actual al entrar a la página
document.addEventListener('DOMContentLoaded', function () {
    cargarMisTurnos(archivoActual);
});
</script>
@endsection
