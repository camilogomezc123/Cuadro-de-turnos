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

    {{-- ── Formulario solicitud ──────────────────────────────────── --}}
    <div class="col-12 col-lg-4">
        <div class="panel h-100">
            <div class="panel-header d-none d-lg-flex">
                <span class="panel-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Nueva Solicitud</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('cambios-turno.store') }}" id="formSolicitud">
                    @csrf

                    {{-- Período --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-calendar3 me-1 text-muted"></i>Período
                        </label>
                        <select name="_archivo_id" class="form-select form-select-sm" id="selectArchivo">
                            @foreach($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mi turno --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-clock me-1 text-muted"></i>Mi turno
                        </label>
                        <select name="turno_origen_id" class="form-select form-select-sm" id="selectTurnoOrigen" required>
                            <option value="">— Cargando... —</option>
                        </select>
                        <div id="turnosSpinner" class="text-muted small mt-1" style="display:none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Cargando...
                        </div>
                        <div id="turnosSinResultados" class="text-warning small mt-1" style="display:none">
                            <i class="bi bi-info-circle me-1"></i>No tienes turnos en este período.
                        </div>
                    </div>

                    {{-- Componente del turno (solo si el turno es compuesto) --}}
                    <div class="mb-3" id="bloqueComponente" style="display:none">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-puzzle me-1 text-muted"></i>¿Qué parte ofreces?
                        </label>
                        <select name="componente_turno" id="selectComponente" class="form-select form-select-sm">
                        </select>
                        <div class="text-muted small mt-1" id="infoHorasComp"></div>
                    </div>

                    {{-- Tipo: Cambio o Ceder --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-arrow-left-right me-1 text-muted"></i>Tipo de solicitud
                        </label>
                        <div class="d-flex gap-2">
                            <div class="form-check flex-fill border rounded-2 p-2" id="optCambio"
                                 style="cursor:pointer;border-color:#dee2e6">
                                <input class="form-check-input" type="radio" name="tipo_movimiento"
                                       id="tipoCambio" value="cambio_directo" checked>
                                <label class="form-check-label small fw-semibold" for="tipoCambio" style="cursor:pointer">
                                    <i class="bi bi-arrow-left-right text-primary me-1"></i>Cambio
                                    <div class="text-muted fw-normal" style="font-size:11px">Intercambias tu turno por el del colega</div>
                                </label>
                            </div>
                            <div class="form-check flex-fill border rounded-2 p-2" id="optCesion"
                                 style="cursor:pointer;border-color:#dee2e6">
                                <input class="form-check-input" type="radio" name="tipo_movimiento"
                                       id="tipoCesion" value="donacion_directa">
                                <label class="form-check-label small fw-semibold" for="tipoCesion" style="cursor:pointer">
                                    <i class="bi bi-gift text-success me-1"></i>Ceder
                                    <div class="text-muted fw-normal" style="font-size:11px">Das tu turno sin recibir nada a cambio</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Médico receptor --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" id="lblReceptor">
                            <i class="bi bi-person me-1 text-muted"></i>Médico que recibe
                        </label>
                        <select name="medico_receptor_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar médico —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Motivo --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-chat-text me-1 text-muted"></i>Motivo
                        </label>
                        <textarea name="motivo" class="form-control form-control-sm" rows="3"
                                  placeholder="Explique el motivo (mín. 5 caracteres)..."
                                  required minlength="5"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-send me-2"></i>Enviar solicitud
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Listado de solicitudes ──────────────────────────────────── --}}
    <div class="col-12 col-lg-8" id="tab-listado">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title d-none d-lg-inline">
                    <i class="bi bi-arrow-left-right me-2"></i>Solicitudes
                </span>
                <form method="GET" class="d-flex gap-2 flex-wrap w-100 align-items-center">
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
                        <option value="cancelado"             {{ $estado=='cancelado'?'selected':'' }}>Cancelado</option>
                    </select>
                </form>
            </div>

            <div class="panel-body p-0">
                @forelse($solicitudes as $s)
                @php
                    $esMiSolicitud  = auth()->user()->medico_id && $s->medico_solicitante_id == auth()->user()->medico_id;
                    $soyReceptor    = auth()->user()->medico_id && $s->medico_receptor_id    == auth()->user()->medico_id;
                    $puedeResponder = $esMaestro || $soyReceptor;
                    $esCesion       = $s->tipo_movimiento === 'donacion_directa';

                    $badgeColor = match($s->estado) {
                        'pendiente'            => 'warning',
                        'aceptado_colega'      => 'info',
                        'aprobado_coordinador' => 'success',
                        'rechazado_colega',
                        'rechazado_coordinador'=> 'danger',
                        'cancelado'            => 'secondary',
                        default                => 'secondary',
                    };
                @endphp

                <div class="border-bottom px-3 py-3">
                    {{-- Cabecera --}}
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex flex-wrap gap-1">
                            @if($esMiSolicitud)
                                <span class="badge bg-primary-subtle text-primary">
                                    <i class="bi bi-person-fill me-1"></i>Yo solicité
                                </span>
                            @elseif($soyReceptor)
                                <span class="badge bg-warning-subtle text-warning">
                                    <i class="bi bi-person-check me-1"></i>Me solicitan
                                </span>
                            @endif
                            @if($esCesion)
                                <span class="badge bg-success-subtle text-success">
                                    <i class="bi bi-gift me-1"></i>Cedencia
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="bi bi-arrow-left-right me-1"></i>Cambio
                                </span>
                            @endif
                            <span class="badge bg-{{ $badgeColor }}-subtle text-{{ $badgeColor }}">
                                {{ $s->label_estado }}
                            </span>
                        </div>
                        <span class="text-muted small">{{ $s->created_at->format('d/m/Y') }}</span>
                    </div>

                    {{-- Médicos --}}
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="small text-muted">Solicitante</div>
                            <div class="fw-semibold small">{{ $s->medicoSolicitante?->nombre_completo ?? '—' }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">{{ $esCesion ? 'Receptor (cede a)' : 'Receptor' }}</div>
                            <div class="fw-semibold small">{{ $s->medicoReceptor?->nombre_completo ?? '—' }}</div>
                        </div>
                    </div>

                    {{-- Turnos --}}
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="small text-muted">
                                Turno {{ $esCesion ? 'cedido' : 'origen' }}
                                @if($s->componente_turno)
                                    <span class="badge bg-light text-dark border ms-1"
                                          title="Componente ofrecido">{{ $s->componente_turno }}</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <span class="turno-badge badge-{{ $s->turnoOrigen?->codigo_turno }}">
                                    {{ $s->turnoOrigen?->codigo_turno ?? '—' }}
                                </span>
                                <span class="small">{{ $s->turnoOrigen?->fecha?->format('d/m/Y') }}</span>
                                @if($s->turnoOrigen?->uci)
                                    <span class="text-muted" style="font-size:11px">{{ $s->turnoOrigen->uci->codigo }}</span>
                                @endif
                            </div>
                            @if($s->componente_turno && $s->componente_turno !== $s->turnoOrigen?->codigo_turno)
                                <div class="small text-primary mt-1">
                                    <i class="bi bi-puzzle me-1"></i>Ofrece solo:
                                    <strong>{{ $s->componente_turno }}</strong>
                                    ({{ ['M'=>'6h mañana','T'=>'6h tarde','N'=>'12h noche','MT'=>'12h mañana+tarde','MN'=>'18h mañana+noche','MTN'=>'24h completo'][$s->componente_turno] ?? '' }})
                                </div>
                            @endif
                        </div>
                        @if($s->turnoDestino && !$esCesion)
                        <div class="col-6">
                            <div class="small text-muted">Turno destino</div>
                            <div class="d-flex align-items-center gap-1">
                                <span class="turno-badge badge-{{ $s->turnoDestino->codigo_turno }}">
                                    {{ $s->turnoDestino->codigo_turno }}
                                </span>
                                <span class="small">{{ $s->turnoDestino->fecha?->format('d/m/Y') }}</span>
                            </div>
                        </div>
                        @elseif($esCesion)
                        <div class="col-6 d-flex align-items-center">
                            <span class="small text-success">
                                <i class="bi bi-arrow-right me-1"></i>
                                Sin contrapartida (cedencia)
                            </span>
                        </div>
                        @endif
                    </div>

                    {{-- Motivo --}}
                    @if($s->motivo)
                        <div class="small text-muted bg-light rounded px-2 py-1 mb-2">
                            <i class="bi bi-chat-text me-1"></i>{{ Str::limit($s->motivo, 100) }}
                        </div>
                    @endif

                    {{-- Acciones --}}
                    @if($s->estado === 'pendiente' && $puedeResponder && !$esMiSolicitud)
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            {{-- Aceptar --}}
                            <form method="POST" action="{{ route('cambios-turno.aceptar', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                @if(!$esCesion)
                                    {{-- Para cambio directo, el receptor puede indicar su turno --}}
                                    <select name="turno_destino_id" class="form-select form-select-sm mb-2"
                                            id="destSelect_{{ $s->id }}">
                                        <option value="">— Sin especificar mi turno (acepto primero) —</option>
                                        @php
                                            $misTurnosReceptor = auth()->user()->medico_id
                                                ? \App\Models\TurnoMedico::where('medico_id', auth()->user()->medico_id)
                                                    ->whereHas('archivo', fn($q)=>$q->where('id', $archivoId))
                                                    ->whereIn('codigo_turno',['M','T','MT','N','MTN','MN'])
                                                    ->orderBy('fecha')->get()
                                                : collect();
                                        @endphp
                                        @foreach($misTurnosReceptor as $tr)
                                            <option value="{{ $tr->id }}">
                                                {{ $tr->fecha->format('d/m') }} · {{ $tr->codigo_turno }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                <button class="btn btn-success btn-sm w-100 py-2">
                                    <i class="bi bi-check-circle me-1"></i>
                                    {{ $esCesion ? 'Aceptar cedencia' : 'Aceptar cambio' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('cambios-turno.rechazar-colega', $s) }}" class="flex-fill">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-danger btn-sm w-100 py-2">
                                    <i class="bi bi-x-circle me-1"></i>Rechazar
                                </button>
                            </form>
                        </div>

                    @elseif($s->estado === 'pendiente' && $esMiSolicitud)
                        {{-- Solicitante puede cancelar su propia solicitud pendiente --}}
                        <div class="d-flex gap-2 mt-2 align-items-center">
                            <span class="small text-muted flex-fill">
                                <i class="bi bi-hourglass-split me-1"></i>Esperando respuesta
                            </span>
                            <form method="POST" action="{{ route('cambios-turno.cancelar', $s) }}"
                                  onsubmit="return confirm('¿Cancelar esta solicitud?')">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-x me-1"></i>Anular solicitud
                                </button>
                            </form>
                        </div>

                    @elseif($s->estado === 'rechazado_colega' && $esMiSolicitud)
                        <div class="d-flex gap-2 mt-2 align-items-center">
                            <span class="small text-danger flex-fill">
                                <i class="bi bi-x-circle me-1"></i>
                                Rechazado por el colega
                                @if($s->respuesta_colega) — {{ $s->respuesta_colega }} @endif
                            </span>
                            <form method="POST" action="{{ route('cambios-turno.cancelar', $s) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-archive me-1"></i>Archivar
                                </button>
                            </form>
                        </div>

                    @elseif($s->estado === 'aceptado_colega' && $esMaestro)
                        <div class="d-flex gap-2 mt-2 flex-wrap">
                            <form method="POST" action="{{ route('cambios-turno.aprobar', $s) }}" class="flex-fill"
                                  onsubmit="return confirm('¿Confirmar y aplicar este cambio? Se modificarán los turnos en el cuadro.')">
                                @csrf @method('PATCH')
                                <button class="btn btn-primary btn-sm w-100 py-2">
                                    <i class="bi bi-check-all me-1"></i>Aprobar y aplicar
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
                            <i class="bi bi-clock-history me-1"></i>Aceptado — pendiente aprobación del coordinador
                        </div>

                    @elseif($s->estado === 'aprobado_coordinador')
                        <div class="small text-success mt-1">
                            <i class="bi bi-check-all me-1"></i>Aplicado al cuadro de turnos
                            @if($s->resuelto_at) · {{ $s->resuelto_at->format('d/m/Y') }} @endif
                        </div>

                    @elseif($s->estado === 'rechazado_coordinador')
                        <div class="small text-danger mt-1">
                            <i class="bi bi-x-circle me-1"></i>Rechazado por el coordinador
                            @if($s->motivo_coordinador) — {{ $s->motivo_coordinador }} @endif
                        </div>

                    @elseif($s->estado === 'cancelado')
                        <div class="small text-muted mt-1">
                            <i class="bi bi-archive me-1"></i>Solicitud cancelada
                        </div>
                    @endif
                </div>
                @empty
                <div class="text-center text-muted py-5">
                    <i class="bi bi-arrow-left-right fs-2 d-block mb-2 opacity-25"></i>
                    No hay solicitudes en este período
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
const misTurnosUrl  = '{{ route("cambios-turno.mis-turnos") }}';
const archivoActual = {{ $archivoId ?: 0 }};

// Mapa de horas por componente
const HORAS_COMP = {
    'M':6,'T':6,'N':12,'MT':12,'MN':18,'MTN':24
};

function cargarMisTurnos(archivoId) {
    const select   = document.getElementById('selectTurnoOrigen');
    const spinner  = document.getElementById('turnosSpinner');
    const sinItems = document.getElementById('turnosSinResultados');

    select.innerHTML = '<option value="">— Cargando... —</option>';
    select.disabled  = true;
    spinner.style.display  = '';
    sinItems.style.display = 'none';
    ocultarComponente();

    fetch(misTurnosUrl + '?archivo_id=' + archivoId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(turnos => {
        if (turnos.length === 0) {
            select.innerHTML = '<option value="">— Sin turnos en este período —</option>';
            sinItems.style.display = '';
        } else {
            select.innerHTML = '<option value="">— Selecciona tu turno —</option>';
            turnos.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.fecha + ' · ' + t.codigo;
                opt.dataset.componentes = JSON.stringify(t.componentes);
                opt.dataset.codigo = t.codigo;
                select.appendChild(opt);
            });
        }
    })
    .catch(() => {
        select.innerHTML = '<option value="">— Error al cargar —</option>';
    })
    .finally(() => {
        select.disabled       = false;
        spinner.style.display = 'none';
    });
}

function mostrarComponente(componentes, codigoTurno) {
    const bloque = document.getElementById('bloqueComponente');
    const sel    = document.getElementById('selectComponente');
    const info   = document.getElementById('infoHorasComp');

    if (!componentes || componentes.length <= 1) {
        // Turno simple (M, T, N) — no hay elección
        bloque.style.display = 'none';
        return;
    }

    sel.innerHTML = '';
    componentes.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.valor;
        opt.textContent = c.label;
        sel.appendChild(opt);
    });
    bloque.style.display = '';
    actualizarInfoHoras(sel.value);

    sel.onchange = () => actualizarInfoHoras(sel.value);
}

function actualizarInfoHoras(comp) {
    const info  = document.getElementById('infoHorasComp');
    const horas = HORAS_COMP[comp] ?? 0;
    info.innerHTML = horas
        ? `<i class="bi bi-clock text-primary me-1"></i><strong>${horas}h</strong> a transferir`
        : '';
}

function ocultarComponente() {
    document.getElementById('bloqueComponente').style.display = 'none';
    document.getElementById('selectComponente').innerHTML = '';
}

document.getElementById('selectTurnoOrigen').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) { ocultarComponente(); return; }
    try {
        const comps = JSON.parse(opt.dataset.componentes || '[]');
        mostrarComponente(comps, opt.dataset.codigo);
    } catch(e) { ocultarComponente(); }
});

document.getElementById('selectArchivo').addEventListener('change', function() {
    cargarMisTurnos(this.value);
});

// Resaltar la opción seleccionada de tipo
document.querySelectorAll('input[name="tipo_movimiento"]').forEach(r => {
    r.addEventListener('change', function() {
        document.getElementById('optCambio').style.borderColor = '#dee2e6';
        document.getElementById('optCesion').style.borderColor = '#dee2e6';
        const lbl = document.getElementById('lblReceptor');
        if (this.value === 'donacion_directa') {
            document.getElementById('optCesion').style.borderColor = '#198754';
            lbl.innerHTML = '<i class="bi bi-person me-1 text-muted"></i>Médico que recibe (cedencia)';
        } else {
            document.getElementById('optCambio').style.borderColor = '#0d6efd';
            lbl.innerHTML = '<i class="bi bi-person me-1 text-muted"></i>Médico con quien cambias';
        }
    });
});
// Estado inicial del borde
document.getElementById('optCambio').style.borderColor = '#0d6efd';

document.addEventListener('DOMContentLoaded', function() {
    if (archivoActual) cargarMisTurnos(archivoActual);

    @if($solicitudes->total() > 0 && !$esMaestro)
    const hayAcciones = document.querySelectorAll('.btn-success').length > 0;
    const tabListado  = document.querySelector('[data-bs-target="#tab-listado"]');
    if (tabListado && hayAcciones) tabListado?.click();
    @endif
});
</script>
@endsection