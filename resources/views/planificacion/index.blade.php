@extends('layouts.app')

@section('title', 'Planificación Mensual')
@section('page-title', 'Planificación Mensual')
@section('breadcrumb')
    <li class="breadcrumb-item active">Planificación</li>
@endsection

@push('head-styles')
<style>
/* ── GRILLA DE PLANIFICACIÓN ── */
.grilla-wrap { overflow-x: auto; }
.grilla-turnos {
    border-collapse: collapse;
    font-size: 12px;
    white-space: nowrap;
}
.grilla-turnos th, .grilla-turnos td {
    border: 1px solid #dee2e6;
    padding: 3px 4px;
    text-align: center;
    vertical-align: middle;
}
.grilla-turnos th { background: #f1f5f9; font-weight: 600; position: sticky; top: 0; z-index: 2; }
.col-nombre { text-align: left !important; min-width: 160px; max-width: 180px; position: sticky; left: 0; background: #fff; z-index: 3; font-weight: 500; }
.col-dia    { min-width: 38px; }
.col-resumen{ min-width: 58px; background: #f8faff; font-weight: 600; }
.dia-finde  { background: #f0f4ff !important; }
.dia-domingo{ background: #ffe8f0 !important; }

/* Celda editable */
.celda-turno {
    cursor: pointer;
    border-radius: 4px;
    padding: 2px 4px;
    display: inline-block;
    min-width: 34px;
    font-weight: 700;
    letter-spacing: .5px;
    font-size: 11px;
    transition: transform .1s;
}
.celda-turno:hover { transform: scale(1.15); box-shadow: 0 2px 6px rgba(0,0,0,.18); }
.celda-turno.tiene-alerta { outline: 2px solid #ff5722; outline-offset: 1px; }

/* Badges de turno */
.badge-M    { background:#2196F3; color:#fff; }
.badge-T    { background:#4CAF50; color:#fff; }
.badge-MT   { background:#FF9800; color:#fff; }
.badge-N    { background:#673AB7; color:#fff; }
.badge-MTN  { background:#880E4F; color:#fff; }
.badge-MN   { background:#E91E63; color:#fff; }
.badge-VAC  { background:#00BCD4; color:#fff; }
.badge-PER  { background:#FFC107; color:#212529; }
.badge-INC  { background:#F44336; color:#fff; }
.badge-LIBRE{ background:#e9ecef; color:#6c757d; }
.badge-vacio{ background:#f8f9fa; color:#dee2e6; border: 1px dashed #dee2e6; }

/* Panel de filtros */
.filtros-panel { background:#f8faff; border:1px solid #e3edff; border-radius:10px; padding:16px 20px; }

/* Tooltip de edición */
#turno-modal .turno-opt {
    display: inline-block;
    padding: 6px 14px;
    margin: 4px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 700;
    font-size: 13px;
    border: 2px solid transparent;
    transition: all .15s;
}
#turno-modal .turno-opt:hover { transform: scale(1.1); border-color: rgba(0,0,0,.2); }
#turno-modal .turno-opt.selected { border-color: #000; box-shadow: 0 0 0 3px rgba(0,0,0,.15); }

/* Resumen cols */
.resumen-horas { color:#1565C0; }
.resumen-noches{ color:#673AB7; }
.resumen-finde { color:#E65100; }
</style>
@endpush

@section('content')

{{-- ── FILTROS ── --}}
<div class="filtros-panel mb-4">
    <form method="GET" action="{{ route('planificacion.index') }}" id="filtroForm" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label fw-semibold small">Período</label>
            <select name="archivo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">— Seleccionar período —</option>
                @foreach($archivos as $a)
                    <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                        {{ $a->nombre_mes }} {{ $a->anio }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small">UCI</label>
            <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">Todas las UCIs</option>
                @foreach($ucis as $u)
                    <option value="{{ $u->id }}" {{ $u->id == $uciId ? 'selected' : '' }}>{{ $u->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold small">Médico</label>
            <select name="medico_id" class="form-select form-select-sm">
                <option value="0">Todos los médicos</option>
                @foreach($medicos as $m)
                    <option value="{{ $m->id }}" {{ $m->id == $medicoId ? 'selected' : '' }}>{{ $m->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="solo_alertas" id="soloAlertas" value="1"
                    {{ $soloAlertas ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="form-check-label small" for="soloAlertas">Solo con alertas</label>
            </div>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-funnel"></i>
            </button>
        </div>
    </form>
</div>

@if(!$archivo)
    <div class="text-center py-5 text-muted">
        <i class="bi bi-calendar3 fs-1 d-block mb-3 opacity-25"></i>
        <p>Seleccione un período para ver la planificación mensual.</p>
        @if($archivos->isEmpty())
            <a href="{{ route('archivos.index') }}" class="btn btn-primary">
                <i class="bi bi-cloud-upload me-2"></i>Cargar primer Excel
            </a>
        @endif
    </div>
@else

{{-- ── LEYENDA ── --}}
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <span class="small text-muted me-2">Turnos:</span>
    @foreach($tiposTurno as $tt)
        <span class="celda-turno badge-{{ $tt->codigo }}">{{ $tt->codigo }}</span>
        <span class="small text-muted me-2">{{ $tt->nombre }} ({{ $tt->horas_total }}h)</span>
    @endforeach
    <span class="ms-3 small text-danger"><i class="bi bi-exclamation-diamond me-1"></i>Borde rojo = alerta</span>
</div>

{{-- ── GRILLA ── --}}
@if(empty($grilla))
    <div class="alert alert-info">No hay turnos registrados para este período con los filtros seleccionados.</div>
@else
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">
            <i class="bi bi-grid-3x3 me-2 text-primary"></i>
            {{ $archivo->nombre_mes }} {{ $archivo->anio }}
            @if($uciId) — {{ $ucis->firstWhere('id', $uciId)?->nombre }} @endif
        </span>
        <span class="badge bg-secondary-subtle text-secondary">{{ count($grilla) }} médicos</span>
    </div>
    <div class="panel-body p-0">
        <div class="grilla-wrap">
            <table class="grilla-turnos w-100" id="grillaTurnos">
                {{-- Encabezado días --}}
                <thead>
                    <tr>
                        <th class="col-nombre">Médico / UCI</th>
                        @for($d = 1; $d <= $diasEnMes; $d++)
                            @php $info = $diasInfo[$d]; @endphp
                            <th class="col-dia {{ $info['es_domingo'] ? 'dia-domingo' : ($info['es_finde'] ? 'dia-finde' : '') }}">
                                <div>{{ $info['letra'] }}</div>
                                <div>{{ $d }}</div>
                            </th>
                        @endfor
                        <th class="col-resumen resumen-horas">H. Tot</th>
                        <th class="col-resumen resumen-noches">Noches</th>
                        <th class="col-resumen resumen-finde">Finde</th>
                        <th class="col-resumen text-danger">⚠</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grilla as $medicoId => $diasMedico)
                        @php
                            $medico    = $medicos[$medicoId];
                            $totalH    = collect($diasMedico)->sum('horas_total');
                            $noches    = collect($diasMedico)->filter(fn($t) => in_array($t->codigo_turno, ['N','MTN','MN']))->count();
                            $findes    = collect($diasMedico)->filter(fn($t) => $t->es_fin_semana && $t->horas_total > 0)->count();
                            $alertas   = collect($diasMedico)->filter(fn($t) => $t->tiene_alerta)->count();
                        @endphp
                        <tr data-medico="{{ $medicoId }}">
                            <td class="col-nombre">
                                <div>{{ $medico->nombre }}</div>
                                <div class="text-muted" style="font-size:10px">{{ $medico->uci->codigo ?? '' }}</div>
                            </td>
                            @for($d = 1; $d <= $diasEnMes; $d++)
                                @php
                                    $turno  = $diasMedico[$d] ?? null;
                                    $codigo = $turno?->codigo_turno ?? '';
                                    $alerta = $turno?->tiene_alerta ?? false;
                                    $info   = $diasInfo[$d];
                                    $celdaClass = $info['es_domingo'] ? 'dia-domingo' : ($info['es_finde'] ? 'dia-finde' : '');
                                @endphp
                                <td class="col-dia {{ $celdaClass }}"
                                    data-turno-id="{{ $turno?->id ?? '' }}"
                                    data-fecha="{{ $info['fecha'] }}"
                                    data-medico-id="{{ $medicoId }}"
                                    data-archivo-id="{{ $archivoId }}">
                                    @if($turno && $turno->id)
                                        <span class="celda-turno badge-{{ $codigo ?: 'LIBRE' }} {{ $alerta ? 'tiene-alerta' : '' }}"
                                              data-bs-toggle="tooltip"
                                              title="{{ $turno->horas_total }}h | {{ $info['fecha'] }}"
                                              onclick="abrirModal({{ $turno->id }}, '{{ $codigo }}', '{{ $medico->nombre }}', '{{ $info['fecha'] }}')">
                                            {{ $codigo ?: '—' }}
                                        </span>
                                    @else
                                        <span class="celda-turno badge-vacio text-muted" style="font-size:10px">—</span>
                                    @endif
                                </td>
                            @endfor
                            <td class="col-resumen resumen-horas">{{ number_format($totalH, 0) }}h</td>
                            <td class="col-resumen resumen-noches">{{ $noches }}</td>
                            <td class="col-resumen resumen-finde">{{ $findes }}</td>
                            <td class="col-resumen text-danger">{{ $alertas > 0 ? $alertas : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endif

{{-- ── MODAL DE EDICIÓN ── --}}
<div class="modal fade" id="turno-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="modal-medico-nombre">Editar turno</h6>
                    <div class="text-muted small" id="modal-fecha-str"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Seleccione el turno:</label>
                    <div id="opciones-turno" class="text-center">
                        @foreach($tiposTurno as $tt)
                            <span class="turno-opt badge-{{ $tt->codigo }}"
                                  data-codigo="{{ $tt->codigo }}"
                                  title="{{ $tt->nombre }} — {{ $tt->horas_total }}h"
                                  onclick="seleccionarTurno('{{ $tt->codigo }}')">
                                {{ $tt->codigo }}
                            </span>
                        @endforeach
                        <span class="turno-opt badge-LIBRE" data-codigo="LIBRE"
                              title="Libre — 0h"
                              onclick="seleccionarTurno('LIBRE')">LIBRE</span>
                    </div>
                </div>
                <div class="alert alert-warning d-none" id="alerta-mtn-habil">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Atención:</strong> MTN solo está permitido sábados y domingos.
                </div>
                <div class="alert alert-info d-none" id="info-horas"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnGuardarTurno" disabled onclick="guardarTurno()">
                    <i class="bi bi-check2 me-1"></i>Guardar cambio
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Toast de confirmación --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastOk" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg">Turno actualizado</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const URL_EDITAR = '{{ route('planificacion.editar') }}';

const HORAS_TURNO = @json(
    collect($tiposTurno)->mapWithKeys(fn($t) => [$t->codigo => $t->horas_total])->toArray()
);

let turnoActivoId   = null;
let codigoActual    = '';
let codigoSeleccionado = '';
let modalBS         = null;

// Inicializar tooltips Bootstrap
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { trigger:'hover', placement:'top' });
    });
    modalBS = new bootstrap.Modal(document.getElementById('turno-modal'));
});

function abrirModal(turnoId, codigoActualVal, nombreMedico, fecha) {
    turnoActivoId       = turnoId;
    codigoActual        = codigoActualVal;
    codigoSeleccionado  = '';

    document.getElementById('modal-medico-nombre').textContent = nombreMedico;
    document.getElementById('modal-fecha-str').textContent     = fecha;
    document.getElementById('btnGuardarTurno').disabled = true;
    document.getElementById('alerta-mtn-habil').classList.add('d-none');
    document.getElementById('info-horas').classList.add('d-none');

    // Resaltar turno actual
    document.querySelectorAll('.turno-opt').forEach(el => {
        el.classList.toggle('selected', el.dataset.codigo === codigoActualVal);
    });

    modalBS.show();
}

function seleccionarTurno(codigo) {
    codigoSeleccionado = codigo;
    document.querySelectorAll('.turno-opt').forEach(el => {
        el.classList.toggle('selected', el.dataset.codigo === codigo);
    });

    // Advertencia MTN en día hábil
    const fechaStr = document.getElementById('modal-fecha-str').textContent;
    const dow      = new Date(fechaStr).getDay(); // 0=Dom, 6=Sab
    const esFinde  = dow === 0 || dow === 6;
    document.getElementById('alerta-mtn-habil').classList.toggle('d-none', !(codigo === 'MTN' && !esFinde));

    // Info de horas
    const horas = HORAS_TURNO[codigo] ?? 0;
    const infoDiv = document.getElementById('info-horas');
    if (codigo !== codigoActual) {
        infoDiv.textContent = `Cambio: ${codigoActual || '—'} → ${codigo} (${horas}h)`;
        infoDiv.classList.remove('d-none');
    } else {
        infoDiv.classList.add('d-none');
    }

    document.getElementById('btnGuardarTurno').disabled = (codigo === codigoActual);
}

async function guardarTurno() {
    if (!turnoActivoId || !codigoSeleccionado) return;

    const btn = document.getElementById('btnGuardarTurno');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';

    try {
        const resp = await fetch(URL_EDITAR, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ turno_id: turnoActivoId, codigo_turno: codigoSeleccionado }),
        });
        const data = await resp.json();

        if (data.ok) {
            // Actualizar celda en la grilla sin recargar
            const td = document.querySelector(`td[data-turno-id="${turnoActivoId}"]`);
            if (td) {
                const span = td.querySelector('.celda-turno');
                if (span) {
                    span.className = `celda-turno badge-${codigoSeleccionado} ${data.tiene_alerta ? 'tiene-alerta' : ''}`;
                    span.textContent = codigoSeleccionado;
                    span.title = `${data.horas_total}h`;
                    span.setAttribute('onclick', `abrirModal(${turnoActivoId}, '${codigoSeleccionado}', '${document.getElementById('modal-medico-nombre').textContent}', '${document.getElementById('modal-fecha-str').textContent}')`);
                }
            }
            mostrarToast(`Turno actualizado: ${codigoSeleccionado} (${data.horas_total}h)`, 'success');
            modalBS.hide();
        } else {
            mostrarToast(data.mensaje || 'Error al guardar', 'danger');
        }
    } catch(e) {
        mostrarToast('Error de conexión: ' + e.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Guardar cambio';
    }
}

function mostrarToast(msg, tipo = 'success') {
    const toast = document.getElementById('toastOk');
    toast.className = `toast align-items-center text-bg-${tipo} border-0`;
    document.getElementById('toastMsg').textContent = msg;
    new bootstrap.Toast(toast, { delay: 3500 }).show();
}
</script>
@endpush
