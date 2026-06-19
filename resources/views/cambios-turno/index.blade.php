@extends('layouts.app')
@section('title', 'Cambios de Turno')
@section('page-title', 'Cambios de Turno')
@section('breadcrumb')
    <li class="breadcrumb-item active">Cambios de Turno</li>
@endsection
@section('content')

@if(session('error'))
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>{{ session('error') }}
    </div>
@endif
@if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-check-circle-fill"></i>{{ session('success') }}
    </div>
@endif

{{-- Tabs móvil --}}
<ul class="nav nav-pills nav-fill mb-3 d-lg-none" id="tabsCambio" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-semibold" data-bs-toggle="pill" data-bs-target="#tab-solicitar">
            <i class="bi bi-plus-circle me-1"></i>Solicitar
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-semibold" data-bs-toggle="pill" data-bs-target="#tab-listado">
            <i class="bi bi-list-ul me-1"></i>Mis solicitudes
            @if($solicitudes->total() > 0)
                <span class="badge bg-primary ms-1">{{ $solicitudes->total() }}</span>
            @endif
        </button>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-solicitar">
<div class="row g-4">

    {{-- ── Formulario solicitud ── --}}
    <div class="col-12 col-lg-4">
        <div class="panel">
            <div class="panel-header d-none d-lg-flex">
                <span class="panel-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Solicitar Cambio</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('cambios-turno.store') }}">
                    @csrf

                    {{-- Período --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-calendar3 me-1 text-muted"></i>Período
                        </label>
                        <select name="_archivo_id" class="form-select" id="selectArchivo">
                            @foreach($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mi turno --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-clock me-1 text-muted"></i>Mi turno a cambiar
                        </label>
                        <select name="turno_origen_id" class="form-select" id="selectTurnoOrigen" required>
                            <option value="">— Cargando turnos... —</option>
                        </select>
                        <div id="turnosSpinner" class="text-muted small mt-1" style="display:none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Cargando turnos...
                        </div>
                        <div id="turnosSinResultados" class="text-warning small mt-1" style="display:none">
                            <i class="bi bi-info-circle me-1"></i>No tienes turnos activos en este período.
                        </div>
                    </div>

                    {{-- Receptor --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-person me-1 text-muted"></i>Médico receptor
                        </label>
                        <select name="medico_receptor_id" class="form-select" required>
                            <option value="">— Seleccionar médico —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Motivo --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-chat-text me-1 text-muted"></i>Motivo
                        </label>
                        <textarea name="motivo" class="form-control" rows="3"
                                  placeholder="Explique el motivo del cambio (mín. 10 caracteres)..."
                                  required minlength="10"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-send me-2"></i>Enviar solicitud
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Listado de solicitudes ── --}}
    <div class="col-12 col-lg-8" id="tab-listado">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title d-none d-lg-inline">
                    <i class="bi bi-arrow-left-right me-2"></i>Solicitudes de Cambio
                </span>
                {{-- Filtros --}}
                <form method="GET" class="d-flex gap-2 flex-wrap w-100">
                    <select name="archivo_id" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
                        @foreach($archivos as $a)
                            <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected':'' }}>
                                {{ $a->nombre_mes }} {{ $a->anio }}
                            </option>
                        @endforeach
                    </select>
                    <select name="estado" class="form-select form-select-sm" style="max-width:170px" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="pendiente"             {{ $estado=='pendiente'?'selected':'' }}>Pendiente</option>
                        <option value="aceptado_colega"       {{ $estado=='aceptado_colega'?'selected':'' }}>Aceptado colega</option>
                        <option value="aprobado_coordinador"  {{ $estado=='aprobado_coordinador'?'selected':'' }}>Aprobado</option>
                        <option value="rechazado_coordinador" {{ $estado=='rechazado_coordinador'?'selected':'' }}>Rechazado</option>
                        <option value="rechazado_colega"      {{ $estado=='rechazado_colega'?'selected':'' }}>Rechazado colega</option>
                    </select>
                </form>
            </div>

            <div class="panel-body p-0">
                @forelse($solicitudes as $s)
                @php
                    $esMiSolicitud  = auth()->user()->medico_id && $s->medico_solicitante_id == auth()->user()->medico_id;
                    $soyReceptor    = auth()->user()->medico_id && $s->medico_receptor_id    == auth()->user()->medico_id;
                    $puedeResponder = $esMaestro || $soyReceptor;

                    $badgeColor = match($s->estado) {
                        'pendiente'            => 'warning',
                        'aceptado_colega'      => 'info',
                        'aprobado_coordinador' => 'success',
                        'rechazado_colega',
                        'rechazado_coordinador'=> 'danger',
                        default                => 'secondary',
                    };
                @endphp

                <div class="border-bottom px-3 py-3">
                    {{-- Cabecera de la tarjeta --}}
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            {{-- Rol en la solicitud --}}
                            @if($esMiSolicitud)
                                <span class="badge bg-primary-subtle text-primary me-1 mb-1">
                                    <i class="bi bi-person-fill me-1"></i>Yo solicité
                                </span>
                            @elseif($soyReceptor)
                                <span class="badge bg-warning-subtle text-warning me-1 mb-1">
                                    <i class="bi bi-person-check me-1"></i>Me solicitan
                                </span>
                            @endif
                            <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }}">
                                {{ $s->label_estado }}
                            </span>
                        </div>
                        <span class="text-muted small">{{ $s->created_at->format('d/m/Y') }}</span>
                    </div>

                    {{-- Médicos y turno --}}
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="small text-muted">Solicitante</div>
                            <div class="fw-semibold small">{{ $s->medicoSolicitante?->nombre_completo ?? '—' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Receptor</div>
                            <div class="fw-semibold small">{{ $s->medicoReceptor?->nombre_completo ?? '—' }}</div>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="small text-muted">Turno origen</div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="turno-badge badge-{{ $s->turnoOrigen?->codigo_turno }}">
                                    {{ $s->turnoOrigen?->codigo_turno ?? '—' }}
                                </span>
                                <span class="small">{{ $s->turnoOrigen?->fecha?->format('d/m/Y') }}</span>
                            </div>
                            @if($s->turnoOrigen?->uci)
                                <div class="text-muted" style="font-size:11px">{{ $s->turnoOrigen->uci->codigo }}</div>
                            @endif
                        </div>
                        @if($s->turnoDestino)
                        <div class="col-6">
                            <div class="small text-muted">Turno destino</div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="turno-badge badge-{{ $s->turnoDestino->codigo_turno }}">
                                    {{ $s->turnoDestino->codigo_turno }}
                                </span>
                                <span class="small">{{ $s->turnoDestino->fecha?->format('d/m/Y') }}</span>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Motivo --}}
                    @if($s->motivo)
                        <div class="small text-muted bg-light rounded px-2 py-1 mb-2">
                            <i class="bi bi-chat-text me-1"></i>{{ Str::limit($s->motivo, 80) }}
                        </div>
                    @endif

                    {{-- Acciones --}}
                    @if($s->estado === 'pendiente' && $puedeResponder)
                        <div class="d-flex gap-2 mt-2">
                            <form method="POST" action="{{ route('cambios-turno.aceptar', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                <button class="btn btn-success btn-sm w-100 py-2">
                                    <i class="bi bi-check-circle me-1"></i>Aceptar
                                </button>
                            </form>
                            <form method="POST" action="{{ route('cambios-turno.rechazar-colega', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-danger btn-sm w-100 py-2">
                                    <i class="bi bi-x-circle me-1"></i>Rechazar
                                </button>
                            </form>
                        </div>
                    @elseif($s->estado === 'pendiente' && !$puedeResponder)
                        <div class="small text-muted mt-1">
                            <i class="bi bi-hourglass-split me-1"></i>Esperando respuesta del receptor
                        </div>
                    @elseif($s->estado === 'aceptado_colega' && $esMaestro)
                        <div class="d-flex gap-2 mt-2">
                            <form method="POST" action="{{ route('cambios-turno.aprobar', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                <button class="btn btn-primary btn-sm w-100 py-2">
                                    <i class="bi bi-check-all me-1"></i>Aprobar
                                </button>
                            </form>
                            <form method="POST" action="{{ route('cambios-turno.rechazar', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-danger btn-sm w-100 py-2">
                                    <i class="bi bi-x me-1"></i>Rechazar
                                </button>
                            </form>
                        </div>
                    @elseif($s->estado === 'aceptado_colega' && !$esMaestro)
                        <div class="small text-info mt-1">
                            <i class="bi bi-clock-history me-1"></i>Aceptado — pendiente de aprobación del coordinador
                        </div>
                    @endif
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-arrow-left-right fs-2 d-block mb-2 opacity-25"></i>
                    No hay solicitudes de cambio en este período
                </div>
                @endforelse

                @if($solicitudes->hasPages())
                    <div class="px-3 py-3">{{ $solicitudes->links() }}</div>
                @endif
            </div>
        </div>
    </div>

</div>{{-- row --}}
</div>{{-- tab-pane --}}
</div>{{-- tab-content --}}

<script>
const misTurnosUrl = '{{ route("cambios-turno.mis-turnos") }}';
const archivoActual = {{ $archivoId ?: 0 }};

function cargarMisTurnos(archivoId) {
    const select   = document.getElementById('selectTurnoOrigen');
    const spinner  = document.getElementById('turnosSpinner');
    const sinItems = document.getElementById('turnosSinResultados');

    select.innerHTML = '';
    select.disabled  = true;
    spinner.style.display  = '';
    sinItems.style.display = 'none';

    fetch(misTurnosUrl + '?archivo_id=' + archivoId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(turnos => {
        if (turnos.length === 0) {
            select.innerHTML = '<option value="">— Sin turnos en este período —</option>';
            sinItems.style.display = '';
        } else {
            select.innerHTML = '<option value="">— Seleccionar turno —</option>';
            turnos.forEach(t => {
                const opt = document.createElement('option');
                opt.value       = t.id;
                opt.textContent = t.fecha + ' · ' + t.label;
                select.appendChild(opt);
            });
        }
    })
    .catch(() => {
        select.innerHTML = '<option value="">— Error al cargar turnos —</option>';
    })
    .finally(() => {
        select.disabled        = false;
        spinner.style.display  = 'none';
    });
}

document.getElementById('selectArchivo').addEventListener('change', function () {
    cargarMisTurnos(this.value);
});

document.addEventListener('DOMContentLoaded', function () {
    if (archivoActual) cargarMisTurnos(archivoActual);

    // En móvil, si hay solicitudes pendientes de mi acción, abrir el tab de listado automáticamente
    const hayAcciones = document.querySelectorAll('.btn-success, .btn-primary').length > 0;
    @if($solicitudes->total() > 0 && !$esMaestro)
    const tabListado = document.querySelector('[data-bs-target="#tab-listado"]');
    if (tabListado && hayAcciones) {
        tabListado?.click();
    }
    @endif
});
</script>
@endsection
