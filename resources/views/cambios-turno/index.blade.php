@extends('layouts.app')
@section('title', 'Cambios de Turno')
@section('page-title', 'Cambios de Turno')
@section('breadcrumb')
    <li class="breadcrumb-item active">Cambios de Turno</li>
@endsection

@push('styles')
<style>
/* ── Turno cards ─────────────────────────────────── */
.turno-cards { display:flex; flex-wrap:wrap; gap:8px; }
.tc {
    cursor:pointer; border:2px solid transparent; border-radius:10px;
    padding:8px 10px; text-align:center; min-width:72px; transition:all .18s;
    background:#f8fafc;
}
.tc:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.tc.selected { border-color:currentColor; box-shadow:0 0 0 3px rgba(0,0,0,.08); }
.tc-code {
    font-size:1.05rem; font-weight:800; padding:3px 6px;
    border-radius:6px; display:inline-block; margin-bottom:4px;
}
.tc-dia  { font-size:.7rem; color:#64748b; font-weight:600; }
.tc-h    { font-size:.65rem; color:#94a3b8; margin-top:1px; }

/* Colores por turno */
.tc-M  { color:#1565C0; } .tc-M  .tc-code { background:#e3f2fd; color:#1565C0; }
.tc-T  { color:#2E7D32; } .tc-T  .tc-code { background:#e8f5e9; color:#2E7D32; }
.tc-MT { color:#00838F; } .tc-MT .tc-code { background:#e0f7fa; color:#00838F; }
.tc-N  { color:#4A148C; } .tc-N  .tc-code { background:#ede7f6; color:#4A148C; }
.tc-MTN{ color:#880E4F; } .tc-MTN .tc-code{ background:#fce4ec; color:#880E4F; }
.tc-MN { color:#E65100; } .tc-MN .tc-code { background:#fff3e0; color:#E65100; }

/* ── Stepper ─────────────────────────────────────── */
.wiz-stepper {
    display:flex; align-items:center; justify-content:center;
    padding:16px 0 20px; gap:0;
}
.wiz-step-item { display:flex; flex-direction:column; align-items:center; min-width:72px; }
.wiz-circle {
    width:34px; height:34px; border-radius:50%; display:flex; align-items:center;
    justify-content:center; font-weight:700; font-size:.85rem;
    border:2px solid #cbd5e1; color:#94a3b8; background:#fff; transition:all .2s;
}
.wiz-circle.done  { background:#1565C0; border-color:#1565C0; color:#fff; }
.wiz-circle.active{ background:#1565C0; border-color:#1565C0; color:#fff; box-shadow:0 0 0 4px rgba(21,101,192,.18); }
.wiz-label { font-size:.68rem; color:#94a3b8; margin-top:4px; font-weight:600; }
.wiz-label.active { color:#1565C0; }
.wiz-line { flex:1; height:2px; background:#e2e8f0; margin-bottom:16px; min-width:20px; max-width:60px; }
.wiz-line.done { background:#1565C0; }

/* ── Tipo selector ───────────────────────────────── */
.tipo-btn {
    flex:1; border:2px solid #e2e8f0; border-radius:10px; padding:12px 8px;
    text-align:center; cursor:pointer; transition:all .18s; background:#fff;
    font-size:.85rem; line-height:1.4;
}
.tipo-btn:hover { border-color:#93c5fd; }
.tipo-btn.active.cambio  { border-color:#1565C0; background:#e3f2fd; color:#1565C0; }
.tipo-btn.active.cesion  { border-color:#2E7D32; background:#e8f5e9; color:#2E7D32; }

/* ── Preview box ─────────────────────────────────── */
.preview-box {
    background:linear-gradient(135deg,#f0f9ff 0%,#f0fdf4 100%);
    border:1.5px solid #bae6fd; border-radius:10px;
    padding:14px; text-align:center;
}
.preview-box .arrow { font-size:1.4rem; color:#64748b; margin:0 10px; }

/* ── Solicitudes list ────────────────────────────── */
.sol-card { border-bottom:1px solid #f1f5f9; padding:14px 16px; }
.sol-card:last-child { border-bottom:none; }
.sol-card.urgente { border-left:4px solid #f59e0b; }
.turno-pill {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:20px; font-size:.78rem; font-weight:700;
}
.tp-M   { background:#e3f2fd; color:#1565C0; }
.tp-T   { background:#e8f5e9; color:#2E7D32; }
.tp-MT  { background:#e0f7fa; color:#00838F; }
.tp-N   { background:#ede7f6; color:#4A148C; }
.tp-MTN { background:#fce4ec; color:#880E4F; }
.tp-MN  { background:#fff3e0; color:#E65100; }
.tp-def { background:#f1f5f9; color:#475569; }

/* ── Componente pills ────────────────────────────── */
.comp-pill {
    cursor:pointer; padding:5px 12px; border-radius:20px; font-size:.8rem;
    font-weight:600; border:2px solid transparent; transition:all .15s;
    background:#f1f5f9; color:#475569;
}
.comp-pill.selected { border-color:currentColor; }
</style>
@endpush

@section('content')
@php $user = auth()->user(); @endphp

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
</div>
@endif
@if(session('success'))
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
</div>
@endif

<div class="row g-4">

{{-- ══════════════════════════════════════════════════════════
     COLUMNA IZQUIERDA: WIZARD (solo médicos con medico_id)
══════════════════════════════════════════════════════════ --}}
@if($user->medico_id)
<div class="col-12 col-lg-5">
<div class="panel">

    {{-- Stepper --}}
    <div class="wiz-stepper" id="stepper">
        <div class="wiz-step-item">
            <div class="wiz-circle active" id="circ1">1</div>
            <div class="wiz-label active" id="lbl1">Tu turno</div>
        </div>
        <div class="wiz-line" id="line1"></div>
        <div class="wiz-step-item">
            <div class="wiz-circle" id="circ2">2</div>
            <div class="wiz-label" id="lbl2">El cambio</div>
        </div>
        <div class="wiz-line" id="line2"></div>
        <div class="wiz-step-item">
            <div class="wiz-circle" id="circ3">3</div>
            <div class="wiz-label" id="lbl3">Confirmar</div>
        </div>
    </div>

    {{-- ── PASO 1: Tu turno ─────────────────────────────── --}}
    <div id="paso1">
        <div class="px-3 pb-3">
            <h6 class="fw-bold mb-1" style="color:#1a2340">
                <i class="bi bi-calendar-check me-2 text-primary"></i>¿Qué turno quieres cambiar?
            </h6>
            <p class="text-muted small mb-3">Selecciona el mes y luego haz clic en el turno que deseas ofertar.</p>

            {{-- Período --}}
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted">Período</label>
                <select id="wiz_archivo" class="form-select form-select-sm">
                    @foreach($archivos as $a)
                        <option value="{{ $a->id }}" @selected($a->id == $archivoId)>
                            {{ $a->nombre_mes }} {{ $a->anio }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Mis turnos (cards) --}}
            <div class="mb-2">
                <label class="form-label small fw-semibold text-muted">Mis turnos este mes</label>
                <div id="misTurnosCards" class="turno-cards">
                    <span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</span>
                </div>
                <div id="sinTurnos" class="text-warning small mt-2" style="display:none">
                    <i class="bi bi-info-circle me-1"></i>No tienes turnos en este período.
                </div>
            </div>

            {{-- Componente (turno compuesto) --}}
            <div id="bloqueComp" class="mb-3 p-2 bg-light rounded" style="display:none">
                <label class="form-label small fw-semibold text-muted mb-2">
                    <i class="bi bi-puzzle me-1"></i>¿Qué parte ofertas?
                </label>
                <div id="compPills" class="d-flex flex-wrap gap-2"></div>
                <div id="compInfo" class="text-muted small mt-2"></div>
            </div>

            <button type="button" class="btn btn-primary w-100 fw-semibold mt-2" id="btnP1Next" disabled onclick="irPaso(2)">
                Siguiente <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
    </div>

    {{-- ── PASO 2: El cambio ────────────────────────────── --}}
    <div id="paso2" style="display:none">
        <div class="px-3 pb-3">
            <h6 class="fw-bold mb-1" style="color:#1a2340">
                <i class="bi bi-arrow-left-right me-2 text-primary"></i>¿Cómo quieres hacerlo?
            </h6>
            <p class="text-muted small mb-3">Elige el tipo y el médico con quien deseas el cambio.</p>

            {{-- Tipo --}}
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="tipo-btn active cambio" id="btnCambio" onclick="selTipo('cambio_directo')">
                    <i class="bi bi-arrow-left-right d-block fs-5 mb-1"></i>
                    <strong>Cambio</strong>
                    <div class="text-muted" style="font-size:.7rem;margin-top:2px">Intercambias<br>tu turno</div>
                </button>
                <button type="button" class="tipo-btn cesion" id="btnCesion" onclick="selTipo('donacion_directa')">
                    <i class="bi bi-gift d-block fs-5 mb-1"></i>
                    <strong>Ceder</strong>
                    <div class="text-muted" style="font-size:.7rem;margin-top:2px">Das tu turno<br>sin recibir</div>
                </button>
            </div>

            {{-- Médico --}}
            <div class="mb-3">
                <label class="form-label small fw-semibold text-muted" id="lblMedico">
                    Médico con quien cambias
                </label>
                <select id="wiz_medico" class="form-select form-select-sm" onchange="onMedicoChange()">
                    <option value="">— Seleccionar médico —</option>
                    @foreach($medicos as $m)
                        @if($m->id != $user->medico_id)
                        <option value="{{ $m->id }}" data-nombre="{{ $m->nombre_completo }}">
                            {{ $m->nombre_completo }}
                        </option>
                        @endif
                    @endforeach
                </select>
            </div>

            {{-- Turnos del receptor (solo para cambio) --}}
            <div id="secTurnosRec" style="display:none">
                <label class="form-label small fw-semibold text-muted">Sus turnos disponibles</label>
                <div id="turnosRecCards" class="turno-cards mb-2">
                    <span class="text-muted small">Selecciona un médico primero.</span>
                </div>
                <div id="sinTurnosRec" class="text-warning small" style="display:none">
                    <i class="bi bi-info-circle me-1"></i>No tiene turnos en este período.
                </div>
            </div>

            {{-- Preview del intercambio --}}
            <div id="previewBox" class="preview-box mb-3" style="display:none">
                <div class="small text-muted fw-semibold mb-2">Vista previa del cambio</div>
                <div class="d-flex align-items-center justify-content-center flex-wrap gap-2">
                    <div class="text-center">
                        <div id="prev_codigo_mio" class="turno-pill tp-def fw-bold fs-6 mb-1">—</div>
                        <div class="small text-muted" id="prev_dia_mio">—</div>
                        <div style="font-size:.65rem;color:#94a3b8">Tú</div>
                    </div>
                    <div class="arrow" id="prev_arrow">⇄</div>
                    <div class="text-center">
                        <div id="prev_codigo_rec" class="turno-pill tp-def fw-bold fs-6 mb-1">—</div>
                        <div class="small text-muted" id="prev_dia_rec">—</div>
                        <div style="font-size:.65rem;color:#94a3b8" id="prev_nombre_rec">—</div>
                    </div>
                </div>
                <div id="prev_horas" class="text-muted small mt-2" style="font-size:.7rem"></div>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-outline-secondary flex-fill" onclick="irPaso(1)">
                    <i class="bi bi-arrow-left me-1"></i> Anterior
                </button>
                <button type="button" class="btn btn-primary flex-fill fw-semibold" id="btnP2Next" disabled onclick="irPaso(3)">
                    Siguiente <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- ── PASO 3: Confirmar ────────────────────────────── --}}
    <div id="paso3" style="display:none">
        <div class="px-3 pb-3">
            <h6 class="fw-bold mb-1" style="color:#1a2340">
                <i class="bi bi-check-circle me-2 text-primary"></i>Confirmar y enviar
            </h6>
            <p class="text-muted small mb-3">Revisa el resumen y escribe el motivo antes de enviar.</p>

            {{-- Resumen visual --}}
            <div class="bg-light rounded p-3 mb-3" id="resumenWiz">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-person-circle text-primary fs-5"></i>
                    <div>
                        <div class="small text-muted">Médico receptor</div>
                        <div class="fw-semibold small" id="res_medico">—</div>
                    </div>
                </div>
                <hr class="my-2">
                <div class="d-flex align-items-center justify-content-around flex-wrap gap-2">
                    <div class="text-center">
                        <div class="small text-muted mb-1">Tu turno</div>
                        <div id="res_codigo_mio" class="turno-pill tp-def fw-bold mb-1">—</div>
                        <div class="small text-muted" id="res_dia_mio">—</div>
                    </div>
                    <div id="res_arrow" class="fs-4 text-muted">→</div>
                    <div class="text-center" id="res_col_rec">
                        <div class="small text-muted mb-1">Su turno</div>
                        <div id="res_codigo_rec" class="turno-pill tp-def fw-bold mb-1">—</div>
                        <div class="small text-muted" id="res_dia_rec">—</div>
                    </div>
                </div>
                <div class="mt-2 text-center" id="res_tipo_label">
                    <span class="badge bg-secondary-subtle text-secondary small">—</span>
                </div>
            </div>

            <form method="POST" action="{{ route('cambios-turno.store') }}" id="formWiz">
                @csrf
                <input type="hidden" name="turno_origen_id"    id="f_origen">
                <input type="hidden" name="turno_destino_id"   id="f_destino">
                <input type="hidden" name="componente_turno"   id="f_comp">
                <input type="hidden" name="tipo_movimiento"    id="f_tipo"   value="cambio_directo">
                <input type="hidden" name="medico_receptor_id" id="f_medico">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">
                        <i class="bi bi-chat-text me-1"></i>Motivo *
                    </label>
                    <textarea name="motivo" class="form-control form-control-sm" rows="3"
                              placeholder="Explica brevemente el motivo del cambio (mín. 5 caracteres)..."
                              required minlength="5" maxlength="500"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary flex-fill" onclick="irPaso(2)">
                        <i class="bi bi-arrow-left me-1"></i> Anterior
                    </button>
                    <button type="submit" class="btn btn-success flex-fill fw-semibold">
                        <i class="bi bi-send me-1"></i> Enviar solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>{{-- panel --}}

{{-- Leyenda de turnos --}}
<div class="panel mt-3 p-3">
    <div class="small fw-semibold text-muted mb-2"><i class="bi bi-info-circle me-1"></i>Guía de turnos</div>
    <div class="d-flex flex-wrap gap-2">
        @foreach(['M'=>'Mañana · 6h','T'=>'Tarde · 6h','MT'=>'Mañana+Tarde · 12h','N'=>'Noche · 12h','MTN'=>'Todo el día · 24h','MN'=>'Mañana+Noche · 18h'] as $c => $desc)
        <div class="d-flex align-items-center gap-1">
            <span class="turno-pill tp-{{ $c }}">{{ $c }}</span>
            <span class="text-muted" style="font-size:.7rem">{{ $desc }}</span>
        </div>
        @endforeach
    </div>
</div>
</div>{{-- col --}}
@endif

{{-- ══════════════════════════════════════════════════════════
     COLUMNA DERECHA: LISTADO DE SOLICITUDES
══════════════════════════════════════════════════════════ --}}
<div class="col-12 {{ $user->medico_id ? 'col-lg-7' : '' }}">

    {{-- Filtros --}}
    <div class="panel mb-3">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
            <i class="bi bi-funnel text-muted"></i>
            <select name="archivo_id" class="form-select form-select-sm" style="max-width:155px" onchange="this.form.submit()">
                @foreach($archivos as $a)
                    <option value="{{ $a->id }}" @selected($a->id == $archivoId)>
                        {{ $a->nombre_mes }} {{ $a->anio }}
                    </option>
                @endforeach
            </select>
            <select name="estado" class="form-select form-select-sm" style="max-width:165px" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="pendiente"             @selected($estado=='pendiente')>Pendiente respuesta</option>
                <option value="aceptado_colega"       @selected($estado=='aceptado_colega')>Aceptado, pend. coord.</option>
                <option value="aprobado_coordinador"  @selected($estado=='aprobado_coordinador')>Aprobados</option>
                <option value="rechazado_coordinador" @selected($estado=='rechazado_coordinador')>Rechazados</option>
                <option value="cancelado"             @selected($estado=='cancelado')>Cancelados</option>
            </select>
            <span class="ms-auto text-muted small">{{ $solicitudes->total() }} solicitud(es)</span>
        </form>
    </div>

    <div class="panel p-0">
    @forelse($solicitudes as $s)
    @php
        $esMiSolicitud = $user->medico_id && $s->medico_solicitante_id == $user->medico_id;
        $soyReceptor   = $user->medico_id && $s->medico_receptor_id   == $user->medico_id;
        $esCesion      = $s->tipo_movimiento === 'donacion_directa';
        $turnoYaAcordado = $s->turno_destino_id !== null;

        $colorEstado = match($s->estado) {
            'pendiente'             => ['bg'=>'warning', 'txt'=>'Esperando respuesta'],
            'aceptado_colega'       => ['bg'=>'info',    'txt'=>'Pendiente coordinador'],
            'aprobado_coordinador'  => ['bg'=>'success', 'txt'=>'Aprobado ✓'],
            'rechazado_colega',
            'rechazado_coordinador' => ['bg'=>'danger',  'txt'=>'Rechazado'],
            'cancelado'             => ['bg'=>'secondary','txt'=>'Cancelado'],
            default                 => ['bg'=>'secondary','txt'=>$s->estado],
        };

        $codigoOrigen   = $s->turnoOrigen?->codigo_turno  ?? '?';
        $codigoMostrado = $s->componente_turno ?? $codigoOrigen;
        $codigoDestino  = $s->turnoDestino?->codigo_turno ?? null;
    @endphp

    {{-- Destacar si requiere acción del usuario --}}
    <div class="sol-card {{ ($s->estado==='pendiente' && $soyReceptor && !$esMiSolicitud) ? 'urgente' : '' }}">

        {{-- Cabecera --}}
        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            <div class="d-flex flex-wrap gap-1 align-items-center">
                @if($soyReceptor && !$esMiSolicitud && $s->estado === 'pendiente')
                    <span class="badge bg-warning text-dark fw-semibold">
                        <i class="bi bi-bell-fill me-1"></i>Te solicitan
                    </span>
                @elseif($esMiSolicitud)
                    <span class="badge bg-primary-subtle text-primary">Yo solicité</span>
                @endif

                @if($esCesion)
                    <span class="badge bg-success-subtle text-success"><i class="bi bi-gift me-1"></i>Cedencia</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-arrow-left-right me-1"></i>Cambio</span>
                @endif

                <span class="badge bg-{{ $colorEstado['bg'] }}-subtle text-{{ $colorEstado['bg'] }}">
                    {{ $colorEstado['txt'] }}
                </span>
            </div>
            <span class="text-muted small text-nowrap">{{ $s->created_at->format('d/m/Y') }}</span>
        </div>

        {{-- Vista del intercambio --}}
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <div class="text-center" style="min-width:70px">
                <div class="small text-muted">{{ $esCesion ? 'Cede' : 'Ofrece' }}</div>
                <span class="turno-pill tp-{{ $codigoMostrado }}">{{ $codigoMostrado }}</span>
                <div class="small text-muted mt-1">{{ $s->turnoOrigen?->fecha?->format('d/m') ?? '—' }}</div>
                <div style="font-size:.65rem;color:#94a3b8">{{ $s->medicoSolicitante?->nombre ?? '—' }}</div>
            </div>

            <div class="fs-4 text-muted {{ $esCesion ? 'text-success' : '' }}">
                {{ $esCesion ? '→' : '⇄' }}
            </div>

            @if($esCesion)
            <div class="text-center">
                <div class="small text-muted">Recibe</div>
                <span class="turno-pill tp-def"><i class="bi bi-gift"></i> {{ $s->medicoReceptor?->nombre ?? '—' }}</span>
                <div style="font-size:.65rem;color:#94a3b8">sin contrapartida</div>
            </div>
            @elseif($codigoDestino)
            <div class="text-center" style="min-width:70px">
                <div class="small text-muted">A cambio</div>
                <span class="turno-pill tp-{{ $codigoDestino }}">{{ $codigoDestino }}</span>
                <div class="small text-muted mt-1">{{ $s->turnoDestino?->fecha?->format('d/m') ?? '—' }}</div>
                <div style="font-size:.65rem;color:#94a3b8">{{ $s->medicoReceptor?->nombre ?? '—' }}</div>
            </div>
            @else
            <div class="text-center text-muted small">
                <i class="bi bi-hourglass-split d-block mb-1"></i>
                Turno del colega<br>por acordar
            </div>
            @endif
        </div>

        {{-- Motivo --}}
        @if($s->motivo)
        <div class="small text-muted bg-light rounded px-2 py-1 mb-2">
            <i class="bi bi-chat-text me-1"></i>{{ Str::limit($s->motivo, 110) }}
        </div>
        @endif

        {{-- ── ACCIONES ─────────────────────────────── --}}

        {{-- Receptor: debe aceptar o rechazar --}}
        @if($s->estado === 'pendiente' && $soyReceptor && !$esMiSolicitud)
        <div class="rounded p-2 mb-1" style="background:#fffbeb;border:1px solid #fde68a">
            <div class="small fw-semibold mb-2">
                <i class="bi bi-hand-index me-1 text-warning"></i>Requiere tu respuesta:
            </div>

            @if(!$esCesion && !$turnoYaAcordado)
            {{-- Cambio sin turno pre-acordado: receptor elige su turno --}}
            <div class="mb-2">
                <label class="form-label small text-muted mb-1">¿Qué turno ofreces a cambio?</label>
                <select class="form-select form-select-sm dest-select" data-sol="{{ $s->id }}">
                    <option value="">— Sin especificar (acepto y acordamos después) —</option>
                    @foreach($misTurnosDisponibles as $td)
                    <option value="{{ $td->id }}">
                        {{ $td->fecha->format('d/m') }} · {{ $td->codigo_turno }}
                    </option>
                    @endforeach
                </select>
            </div>
            @elseif(!$esCesion && $turnoYaAcordado)
            {{-- El solicitante ya propuso el turno --}}
            <div class="small text-muted mb-2">
                <i class="bi bi-check-circle text-success me-1"></i>
                El colega propone cambiar por tu <strong>{{ $codigoDestino }}</strong> del {{ $s->turnoDestino?->fecha?->format('d/m/Y') }}.
            </div>
            @endif

            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('cambios-turno.aceptar', $s) }}" class="flex-fill aceptar-form" data-sol="{{ $s->id }}">
                    @csrf @method('PATCH')
                    @if(!$esCesion && !$turnoYaAcordado)
                    <input type="hidden" name="turno_destino_id" class="dest-hidden" value="">
                    @endif
                    <button class="btn btn-success btn-sm w-100 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i>{{ $esCesion ? 'Aceptar cedencia' : 'Aceptar cambio' }}
                    </button>
                </form>
                <form method="POST" action="{{ route('cambios-turno.rechazar-colega', $s) }}" class="flex-fill">
                    @csrf @method('PATCH')
                    <button class="btn btn-outline-danger btn-sm w-100 fw-semibold">
                        <i class="bi bi-x-circle me-1"></i>Rechazar
                    </button>
                </form>
            </div>
        </div>

        {{-- Solicitante esperando --}}
        @elseif($s->estado === 'pendiente' && $esMiSolicitud)
        <div class="d-flex gap-2 align-items-center">
            <span class="small text-muted flex-fill">
                <i class="bi bi-hourglass-split me-1"></i>Esperando respuesta de {{ $s->medicoReceptor?->nombre ?? 'colega' }}
            </span>
            <form method="POST" action="{{ route('cambios-turno.cancelar', $s) }}"
                  onsubmit="return confirm('¿Cancelar esta solicitud?')">
                @csrf @method('PATCH')
                <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x me-1"></i>Anular
                </button>
            </form>
        </div>

        {{-- Rechazado por colega --}}
        @elseif($s->estado === 'rechazado_colega' && $esMiSolicitud)
        <div class="d-flex gap-2 align-items-center">
            <span class="small text-danger flex-fill">
                <i class="bi bi-x-circle me-1"></i>Rechazado
                @if($s->respuesta_colega) — {{ $s->respuesta_colega }} @endif
            </span>
            <form method="POST" action="{{ route('cambios-turno.cancelar', $s) }}">
                @csrf @method('PATCH')
                <button class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-archive me-1"></i>Archivar
                </button>
            </form>
        </div>

        {{-- Aceptado, pend. coordinador --}}
        @elseif($s->estado === 'aceptado_colega' && $esMaestro)
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="{{ route('cambios-turno.aprobar', $s) }}" class="flex-fill"
                  onsubmit="return confirm('¿Confirmar y aplicar? Se modificarán los turnos en el cuadro.')">
                @csrf @method('PATCH')
                <button class="btn btn-primary btn-sm w-100 fw-semibold">
                    <i class="bi bi-check-all me-1"></i>Aprobar y aplicar
                </button>
            </form>
            <form method="POST" action="{{ route('cambios-turno.rechazar', $s) }}" class="flex-fill">
                @csrf @method('PATCH')
                <button class="btn btn-outline-danger btn-sm w-100">
                    <i class="bi bi-x me-1"></i>Rechazar
                </button>
            </form>
        </div>

        @elseif($s->estado === 'aceptado_colega' && !$esMaestro)
        <div class="small text-info">
            <i class="bi bi-clock-history me-1"></i>Aceptado — pendiente aprobación del coordinador
        </div>

        @elseif($s->estado === 'aprobado_coordinador')
        <div class="small text-success">
            <i class="bi bi-check-all me-1"></i>Aplicado al cuadro de turnos
            @if($s->resuelto_at) · {{ $s->resuelto_at->format('d/m/Y') }} @endif
        </div>

        @elseif(str_contains($s->estado, 'rechazado'))
        <div class="small text-danger">
            <i class="bi bi-x-circle me-1"></i>Rechazado
            @if($s->motivo_coordinador) — {{ $s->motivo_coordinador }}
            @elseif($s->respuesta_colega) — {{ $s->respuesta_colega }}
            @endif
        </div>

        @elseif($s->estado === 'cancelado')
        <div class="small text-muted"><i class="bi bi-archive me-1"></i>Cancelado</div>
        @endif

    </div>{{-- sol-card --}}
    @empty
    <div class="text-center text-muted py-5">
        <i class="bi bi-arrow-left-right fs-2 d-block mb-2 opacity-25"></i>
        No hay solicitudes en este período
    </div>
    @endforelse

    @if($solicitudes->hasPages())
    <div class="px-3 py-3">{{ $solicitudes->links() }}</div>
    @endif
    </div>{{-- panel --}}

</div>{{-- col --}}
</div>{{-- row --}}

@push('scripts')
<script>
const URL_MIS_TURNOS  = '{{ route("cambios-turno.mis-turnos") }}';
const URL_TUR_REC     = '{{ route("cambios-turno.turnos-receptor") }}';
const ARCHIVO_ACTUAL  = {{ $archivoId ?: 0 }};

const HORAS = { M:6, T:6, N:12, MT:12, MN:18, MTN:24 };
const COLORES = { M:'tp-M', T:'tp-T', MT:'tp-MT', N:'tp-N', MTN:'tp-MTN', MN:'tp-MN' };

/* ── Estado del wizard ──────────────────────────── */
const W = {
    archivoId: ARCHIVO_ACTUAL,
    turnoId:null, turnoCodigo:null, turnoDia:null, turnoHoras:null,
    componente:null,
    tipo:'cambio_directo',
    medicoId:null, medicoNombre:null,
    recTurnoId:null, recTurnoCodigo:null, recTurnoDia:null, recTurnoHoras:null,
};

/* ── Paso actual ────────────────────────────────── */
let pasoActual = 1;

function irPaso(n) {
    if (n === 2 && !validarPaso1()) return;
    if (n === 3 && !validarPaso2()) return;

    pasoActual = n;
    ['paso1','paso2','paso3'].forEach((id,i) => {
        document.getElementById(id).style.display = (i+1 === n) ? '' : 'none';
    });
    // Stepper
    [1,2,3].forEach(i => {
        const c = document.getElementById('circ'+i);
        const l = document.getElementById('lbl'+i);
        c.classList.remove('active','done');
        l.classList.remove('active');
        if (i < n)  { c.classList.add('done'); }
        if (i === n){ c.classList.add('active'); l.classList.add('active'); }
    });
    // Lines
    document.getElementById('line1').classList.toggle('done', n > 1);
    document.getElementById('line2').classList.toggle('done', n > 2);

    if (n === 3) rellenarResumen();
    if (n === 3) rellenarHiddens();
}

function validarPaso1() {
    if (!W.turnoId) {
        alert('Selecciona un turno primero.'); return false;
    }
    return true;
}

function validarPaso2() {
    if (!W.medicoId) {
        alert('Selecciona un médico.'); return false;
    }
    if (W.tipo === 'cambio_directo' && !W.recTurnoId) {
        alert('Selecciona el turno del colega que quieres recibir.'); return false;
    }
    return true;
}

/* ── Tarjetas de turno ──────────────────────────── */
function crearCard(t, tipo) {
    const div = document.createElement('div');
    div.className = `tc tc-${t.codigo}`;
    div.dataset.id     = t.id;
    div.dataset.codigo = t.codigo;
    div.dataset.dia    = t.dia ?? t.fecha;
    div.dataset.horas  = t.horas ?? (HORAS[t.codigo] || 0);
    if (t.componentes) div.dataset.componentes = JSON.stringify(t.componentes);

    div.innerHTML = `
        <div class="tc-code">${t.codigo}</div>
        <div class="tc-dia">${t.dia ?? t.fecha}</div>
        <div class="tc-h">${t.horas ?? (HORAS[t.codigo]||0)}h</div>
    `;

    div.addEventListener('click', () => {
        if (tipo === 'origen') selTurnoOrigen(div, t);
        else                   selTurnoDestino(div, t);
    });
    return div;
}

/* ── Paso 1: cargar mis turnos ──────────────────── */
function cargarMisTurnos(archivoId) {
    W.archivoId = archivoId;
    resetPaso1();
    const cont = document.getElementById('misTurnosCards');
    const sinT = document.getElementById('sinTurnos');
    cont.innerHTML = '<span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</span>';
    sinT.style.display = 'none';

    fetch(`${URL_MIS_TURNOS}?archivo_id=${archivoId}`)
        .then(r => r.json())
        .then(data => {
            cont.innerHTML = '';
            if (!data.length) { sinT.style.display = ''; return; }
            data.forEach(t => cont.appendChild(crearCard(t, 'origen')));
        })
        .catch(() => { cont.innerHTML = '<span class="text-danger small">Error al cargar turnos.</span>'; });
}

function resetPaso1() {
    W.turnoId = W.turnoCodigo = W.turnoDia = W.turnoHoras = W.componente = null;
    document.getElementById('bloqueComp').style.display = 'none';
    document.getElementById('btnP1Next').disabled = true;
}

function selTurnoOrigen(card, t) {
    document.querySelectorAll('#misTurnosCards .tc').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    W.turnoId     = t.id;
    W.turnoCodigo = t.codigo;
    W.turnoDia    = t.dia ?? t.fecha;
    W.turnoHoras  = t.horas ?? HORAS[t.codigo] ?? 0;
    W.componente  = null;

    // Mostrar selector de componente si es turno compuesto
    const comps = t.componentes || [];
    if (comps.length > 1) {
        mostrarComponentes(comps);
    } else {
        document.getElementById('bloqueComp').style.display = 'none';
        W.componente = (comps.length === 1) ? comps[0].valor : null;
        habilitarP1Next();
    }
}

function mostrarComponentes(comps) {
    const pills = document.getElementById('compPills');
    const info  = document.getElementById('compInfo');
    pills.innerHTML = '';
    comps.forEach((c,i) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `comp-pill tc-${c.valor}` + (i===0?' selected':'');
        btn.textContent = c.label;
        btn.dataset.valor = c.valor;
        btn.addEventListener('click', () => {
            pills.querySelectorAll('.comp-pill').forEach(p => p.classList.remove('selected'));
            btn.classList.add('selected');
            W.componente = c.valor;
            info.innerHTML = `<i class="bi bi-clock text-primary me-1"></i><strong>${HORAS[c.valor]||'?'}h</strong> se transferirán`;
            habilitarP1Next();
        });
        pills.appendChild(btn);
    });
    // seleccionar el primero
    W.componente = comps[0].valor;
    info.innerHTML = `<i class="bi bi-clock text-primary me-1"></i><strong>${HORAS[comps[0].valor]||'?'}h</strong> se transferirán`;
    document.getElementById('bloqueComp').style.display = '';
    habilitarP1Next();
}

function habilitarP1Next() {
    document.getElementById('btnP1Next').disabled = !W.turnoId;
}

/* ── Paso 2: tipo ───────────────────────────────── */
function selTipo(tipo) {
    W.tipo = tipo;
    const bC = document.getElementById('btnCambio');
    const bS = document.getElementById('btnCesion');
    const lM = document.getElementById('lblMedico');
    const sR = document.getElementById('secTurnosRec');
    bC.classList.toggle('active', tipo === 'cambio_directo');
    bC.classList.toggle('cambio', tipo === 'cambio_directo');
    bS.classList.toggle('active', tipo === 'donacion_directa');
    bS.classList.toggle('cesion', tipo === 'donacion_directa');

    lM.textContent = tipo === 'donacion_directa' ? 'Médico que recibirá tu turno' : 'Médico con quien cambias';

    if (tipo === 'donacion_directa') {
        sR.style.display = 'none';
        W.recTurnoId = W.recTurnoCodigo = W.recTurnoDia = W.recTurnoHoras = null;
        actualizarPreview();
        document.getElementById('btnP2Next').disabled = !W.medicoId;
    } else {
        if (W.medicoId) {
            sR.style.display = '';
            cargarTurnosReceptor();
        }
    }
}

/* ── Paso 2: médico ─────────────────────────────── */
function onMedicoChange() {
    const sel = document.getElementById('wiz_medico');
    const opt = sel.options[sel.selectedIndex];
    W.medicoId     = sel.value ? parseInt(sel.value) : null;
    W.medicoNombre = opt?.dataset.nombre ?? '';
    W.recTurnoId = W.recTurnoCodigo = W.recTurnoDia = W.recTurnoHoras = null;
    document.getElementById('previewBox').style.display = 'none';
    document.getElementById('btnP2Next').disabled = true;

    if (!W.medicoId) return;

    if (W.tipo === 'donacion_directa') {
        actualizarPreviewCesion();
        document.getElementById('btnP2Next').disabled = false;
        return;
    }

    document.getElementById('secTurnosRec').style.display = '';
    cargarTurnosReceptor();
}

function cargarTurnosReceptor() {
    const cont = document.getElementById('turnosRecCards');
    const sinT = document.getElementById('sinTurnosRec');
    cont.innerHTML = '<span class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Cargando...</span>';
    sinT.style.display = 'none';

    fetch(`${URL_TUR_REC}?medico_id=${W.medicoId}&archivo_id=${W.archivoId}`)
        .then(r => r.json())
        .then(data => {
            cont.innerHTML = '';
            if (!data.length) { sinT.style.display = ''; return; }
            data.forEach(t => cont.appendChild(crearCard(t, 'destino')));
        })
        .catch(() => { cont.innerHTML = '<span class="text-danger small">Error al cargar.</span>'; });
}

function selTurnoDestino(card, t) {
    document.querySelectorAll('#turnosRecCards .tc').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    W.recTurnoId    = t.id;
    W.recTurnoCodigo= t.codigo;
    W.recTurnoDia   = t.dia ?? t.fecha;
    W.recTurnoHoras = t.horas ?? HORAS[t.codigo] ?? 0;
    actualizarPreview();
    document.getElementById('btnP2Next').disabled = false;
}

/* ── Preview ────────────────────────────────────── */
function actualizarPreview() {
    if (!W.turnoId || !W.medicoId || !W.recTurnoId) return;
    const box    = document.getElementById('previewBox');
    const codMio = W.componente || W.turnoCodigo;

    document.getElementById('prev_codigo_mio').className = `turno-pill ${COLORES[codMio]||'tp-def'} fw-bold fs-6 mb-1`;
    document.getElementById('prev_codigo_mio').textContent = codMio;
    document.getElementById('prev_dia_mio').textContent    = W.turnoDia;

    document.getElementById('prev_codigo_rec').className = `turno-pill ${COLORES[W.recTurnoCodigo]||'tp-def'} fw-bold fs-6 mb-1`;
    document.getElementById('prev_codigo_rec').textContent = W.recTurnoCodigo;
    document.getElementById('prev_dia_rec').textContent    = W.recTurnoDia;
    document.getElementById('prev_nombre_rec').textContent = W.medicoNombre;

    const hMio = HORAS[codMio] || 0;
    const hRec = W.recTurnoHoras || 0;
    document.getElementById('prev_horas').innerHTML =
        `Tú das <strong>${hMio}h</strong> · recibes <strong>${hRec}h</strong>`;
    box.style.display = '';
}

function actualizarPreviewCesion() {
    if (!W.turnoId || !W.medicoId) return;
    const box    = document.getElementById('previewBox');
    const codMio = W.componente || W.turnoCodigo;

    document.getElementById('prev_codigo_mio').className = `turno-pill ${COLORES[codMio]||'tp-def'} fw-bold fs-6 mb-1`;
    document.getElementById('prev_codigo_mio').textContent = codMio;
    document.getElementById('prev_dia_mio').textContent    = W.turnoDia;
    document.getElementById('prev_arrow').textContent      = '→';
    document.getElementById('prev_codigo_rec').className   = 'turno-pill tp-def fw-bold fs-6 mb-1';
    document.getElementById('prev_codigo_rec').innerHTML   = '<i class="bi bi-gift"></i>';
    document.getElementById('prev_dia_rec').textContent    = '';
    document.getElementById('prev_nombre_rec').textContent = W.medicoNombre;
    document.getElementById('prev_horas').innerHTML        =
        `Cedes <strong>${HORAS[codMio]||0}h</strong> sin recibir nada a cambio`;
    box.style.display = '';
}

/* ── Paso 3: resumen y hiddens ──────────────────── */
function rellenarResumen() {
    const codMio = W.componente || W.turnoCodigo;
    document.getElementById('res_medico').textContent    = W.medicoNombre || '—';
    document.getElementById('res_codigo_mio').className  = `turno-pill ${COLORES[codMio]||'tp-def'} fw-bold mb-1`;
    document.getElementById('res_codigo_mio').textContent= codMio || '—';
    document.getElementById('res_dia_mio').textContent   = W.turnoDia || '—';

    if (W.tipo === 'cambio_directo') {
        document.getElementById('res_arrow').textContent     = '⇄';
        document.getElementById('res_col_rec').style.display = '';
        document.getElementById('res_codigo_rec').className  = `turno-pill ${COLORES[W.recTurnoCodigo]||'tp-def'} fw-bold mb-1`;
        document.getElementById('res_codigo_rec').textContent= W.recTurnoCodigo || '—';
        document.getElementById('res_dia_rec').textContent   = W.recTurnoDia || '—';
        document.getElementById('res_tipo_label').innerHTML  =
            '<span class="badge bg-primary-subtle text-primary"><i class="bi bi-arrow-left-right me-1"></i>Cambio directo</span>';
    } else {
        document.getElementById('res_arrow').textContent     = '→';
        document.getElementById('res_col_rec').style.display = 'none';
        document.getElementById('res_tipo_label').innerHTML  =
            '<span class="badge bg-success-subtle text-success"><i class="bi bi-gift me-1"></i>Cedencia (sin contrapartida)</span>';
    }
}

function rellenarHiddens() {
    document.getElementById('f_origen').value  = W.turnoId    || '';
    document.getElementById('f_destino').value = W.recTurnoId || '';
    document.getElementById('f_comp').value    = W.componente || '';
    document.getElementById('f_tipo').value    = W.tipo;
    document.getElementById('f_medico').value  = W.medicoId   || '';
}

/* ── Sync select turno_destino → hidden en aceptar forms ── */
document.querySelectorAll('.dest-select').forEach(sel => {
    const solId = sel.dataset.sol;
    const hidden = document.querySelector(`.aceptar-form[data-sol="${solId}"] .dest-hidden`);
    if (hidden) sel.addEventListener('change', () => { hidden.value = sel.value; });
});

/* ── Init ───────────────────────────────────────── */
document.getElementById('wiz_archivo')?.addEventListener('change', function() {
    cargarMisTurnos(this.value);
});

document.addEventListener('DOMContentLoaded', () => {
    if (ARCHIVO_ACTUAL) cargarMisTurnos(ARCHIVO_ACTUAL);
    // Estado inicial botón tipo
    document.getElementById('btnCambio')?.classList.add('active','cambio');
});
</script>
@endpush
@endsection