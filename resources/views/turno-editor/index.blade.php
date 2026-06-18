@extends('layouts.app')
@section('title','Editor de Turnos')
@section('page-title','Editor de Turnos por UCI')
@section('breadcrumb')
    <li class="breadcrumb-item active">Editor de Turnos</li>
@endsection

@push('styles')
<style>
.grilla-table th, .grilla-table td { padding: 3px 4px; text-align:center; white-space:nowrap; }
.grilla-table .col-medico { text-align:left; min-width:130px; position:sticky; left:0; background:#fff; z-index:2; border-right:2px solid #dee2e6; }
.grilla-table .col-resumen { min-width:55px; background:#f8f9fa; }
.grilla-table th.dia-finde { background:#fff3cd; }
.grilla-table td.dia-finde { background:#fffbec; }
.grilla-table th.dia-hoy   { background:#d1fae5; }
.grilla-table td.dia-hoy   { background:#ecfdf5; }
.grilla-table th.sticky-head { position:sticky; top:0; z-index:3; background:#f0f4f8; }
.grilla-wrap  { overflow-x:auto; max-height:72vh; overflow-y:auto; }
.badge-M   { background:#2196F3; color:#fff; }
.badge-T   { background:#4CAF50; color:#fff; }
.badge-MT  { background:#FF9800; color:#fff; }
.badge-N   { background:#7B1FA2; color:#fff; }
.badge-MTN { background:#E91E63; color:#fff; }
.badge-MN  { background:#880E4F; color:#fff; }
.badge-PER { background:#78909C; color:#fff; }
.badge-INC { background:#F44336; color:#fff; }
.badge-VAC { background:#9E9E9E; color:#fff; }
.badge-cod { display:inline-block; padding:2px 5px; border-radius:4px; font-size:.72rem; font-weight:700; cursor:pointer; min-width:30px; }
.badge-cod.empty { background:#e9ecef; color:#adb5bd; }
.cel-edit { cursor:pointer; }
.cel-edit:hover { filter:brightness(.9); }
</style>
@endpush

@section('content')
<div class="fade-in">

{{-- ── FILTROS ── --}}
<div class="panel mb-3">
    <div class="panel-body">
        <form method="GET" action="{{ route('turno-editor.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" @selected($uciId==$u->id)>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Mes</label>
                <select name="mes" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($meses as $i=>$m)
                        <option value="{{ $i+1 }}" @selected($mes==$i+1)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Año</label>
                <input type="number" name="anio" value="{{ $anio }}" class="form-control form-control-sm" min="2020" max="2035" onchange="this.form.submit()">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-search me-1"></i>Ver
                </button>
                <span class="ms-auto">
                    @if($archivo)
                        <span class="badge bg-success-subtle text-success rounded-pill">
                            <i class="bi bi-check-circle me-1"></i>
                            {{ $archivo->total_medicos ?? 0 }} méd · {{ $archivo->total_turnos ?? 0 }} turnos
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning rounded-pill">
                            <i class="bi bi-exclamation-triangle me-1"></i>Sin datos
                        </span>
                    @endif
                </span>
            </div>
        </form>
    </div>
</div>

{{-- ── BOTONES DE ACCIÓN ── --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaSecuencia">
        <i class="bi bi-calendar-plus me-1"></i>Nueva secuencia
    </button>
    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRepetir" @if(!$archivo) disabled @endif>
        <i class="bi bi-arrow-repeat me-1"></i>Repetir a otros meses
    </button>
    <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarMedico" @if(!$archivo) disabled @endif>
        <i class="bi bi-person-plus me-1"></i>Agregar médico
    </button>
    @if($uci)
        <span class="ms-auto text-muted small align-self-center">
            <i class="bi bi-hospital me-1"></i>{{ $uci->nombre }}
            &nbsp;·&nbsp;<strong>{{ $meses[$mes-1] }} {{ $anio }}</strong>
        </span>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- ── GRILLA DE TURNOS ── --}}
<div class="panel">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-table me-2 text-primary"></i>Cuadro de Turnos</span>
        <small class="text-muted">Clic en una celda para cambiar el código</small>
    </div>
    <div class="panel-body p-0 grilla-wrap">
        @if($medicosExistentes->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                No hay turnos cargados para esta UCI y mes.<br>
                Use <strong>"Nueva secuencia"</strong> para ingresar los turnos.
            </div>
        @else
        <table class="table table-bordered grilla-table mb-0" style="font-size:.78rem">
            <thead>
                <tr>
                    <th class="sticky-head col-medico">Médico</th>
                    @foreach($diasInfo as $d => $info)
                    <th class="sticky-head {{ $info['es_finde'] ? 'dia-finde' : '' }} {{ $info['es_hoy'] ? 'dia-hoy' : '' }}"
                        title="{{ \Carbon\Carbon::parse($info['fecha'])->isoFormat('dddd D') }}">
                        <div>{{ $d }}</div>
                        <div style="font-size:.65rem;color:#666">{{ $info['letra'] }}</div>
                    </th>
                    @endforeach
                    <th class="sticky-head col-resumen">H.Total</th>
                    <th class="sticky-head col-resumen">M</th>
                    <th class="sticky-head col-resumen">T</th>
                    <th class="sticky-head col-resumen">MT</th>
                    <th class="sticky-head col-resumen">N</th>
                </tr>
            </thead>
            <tbody>
                @foreach($medicosExistentes as $m)
                @php
                    $turnosMed  = $grilla[$m->id] ?? [];
                    $horasTot   = 0;
                    $cntM = $cntT = $cntMT = $cntN = 0;
                    $horasMap   = ['M'=>6,'T'=>6,'MT'=>12,'N'=>12,'MTN'=>24,'MN'=>18];
                    foreach ($turnosMed as $cod) {
                        $horasTot += $horasMap[$cod] ?? 0;
                        if ($cod==='M')  $cntM++;
                        if ($cod==='T')  $cntT++;
                        if ($cod==='MT') $cntMT++;
                        if ($cod==='N')  $cntN++;
                    }
                @endphp
                <tr>
                    <td class="col-medico fw-semibold" id="nombre-med-{{ $m->id }}">
                        {{ $m->nombre_completo }}
                        <button type="button" class="btn btn-link p-0 ms-1 border-0 align-middle"
                                onclick="abrirEditMedico({{ $m->id }},'{{ addslashes($m->nombre) }}','{{ addslashes($m->apellido ?? '') }}')"
                                title="Editar nombre">
                            <i class="bi bi-pencil-fill text-muted" style="font-size:.65rem"></i>
                        </button>
                    </td>
                    @foreach($diasInfo as $d => $info)
                    @php $cod = $turnosMed[$d] ?? ''; @endphp
                    <td class="{{ $info['es_finde'] ? 'dia-finde' : '' }} {{ $info['es_hoy'] ? 'dia-hoy' : '' }} cel-edit"
                        onclick="abrirCeldaEdit({{ $m->id }},'{{ $m->nombre_completo }}','{{ $info['fecha'] }}','{{ $cod }}',{{ $d }})"
                        title="{{ $m->nombre_completo }} · {{ \Carbon\Carbon::parse($info['fecha'])->isoFormat('dddd D MMM') }}">
                        @if($cod)
                            <span class="badge-cod badge-{{ $cod }}">{{ $cod }}</span>
                        @else
                            <span class="badge-cod empty">—</span>
                        @endif
                    </td>
                    @endforeach
                    <td class="col-resumen fw-bold {{ $horasTot > 200 ? 'text-danger' : '' }}">{{ $horasTot }}</td>
                    <td class="col-resumen">{{ $cntM }}</td>
                    <td class="col-resumen">{{ $cntT }}</td>
                    <td class="col-resumen">{{ $cntMT }}</td>
                    <td class="col-resumen">{{ $cntN }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

</div>

{{-- ═══════════════════ MODALES ═══════════════════ --}}

{{-- Modal: Nueva secuencia --}}
<div class="modal fade" id="modalNuevaSecuencia" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Nueva secuencia — {{ $uci?->nombre }} · {{ $meses[$mes-1] }} {{ $anio }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('turno-editor.guardar-secuencia') }}" id="formNuevaSeq">
            @csrf
            <input type="hidden" name="uci_id" value="{{ $uciId }}">
            <input type="hidden" name="mes"    value="{{ $mes }}">
            <input type="hidden" name="anio"   value="{{ $anio }}">
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Defina el patrón semanal (Lun–Dom) por médico.
                    El sistema generará automáticamente los turnos de todo el mes respetando cada día de la semana.
                </p>
                <div id="filasSecuencia"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="agregarFilaSeq()">
                    <i class="bi bi-plus me-1"></i>Agregar médico
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Generar mes
                </button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal: Repetir secuencia --}}
<div class="modal fade" id="modalRepetir" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Repetir secuencia a otros meses</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('turno-editor.repetir') }}">
            @csrf
            <input type="hidden" name="uci_id"      value="{{ $uciId }}">
            <input type="hidden" name="mes_origen"   value="{{ $mes }}">
            <input type="hidden" name="anio_origen"  value="{{ $anio }}">
            <div class="modal-body">
                <p class="text-muted small">Toma el patrón semanal de <strong>{{ $meses[$mes-1] }} {{ $anio }}</strong> y lo aplica a los meses seleccionados.</p>
                <label class="form-label fw-bold">Meses destino <span class="text-danger">*</span></label>
                <div class="row g-2">
                    @php
                        $anioBase = $anio;
                        $mesBase  = $mes;
                    @endphp
                    @for($i = 1; $i <= 12; $i++)
                    @php
                        $mDest = (($mesBase - 1 + $i) % 12) + 1;
                        $aDest = $anioBase + intdiv($mesBase - 1 + $i, 12);
                        $key   = "{$mDest}-{$aDest}";
                    @endphp
                    <div class="col-6 col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="meses_destino[]"
                                   value="{{ $key }}" id="dest{{ $i }}">
                            <label class="form-check-label" for="dest{{ $i }}">
                                {{ $meses[$mDest-1] }} {{ $aDest }}
                            </label>
                        </div>
                    </div>
                    @endfor
                </div>
                <div class="alert alert-warning mt-3 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Sobreescribirá los turnos de esta UCI en los meses seleccionados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning">Repetir secuencia</button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal: Agregar médico al mes --}}
<div class="modal fade" id="modalAgregarMedico" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Agregar médico — {{ $meses[$mes-1] }} {{ $anio }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('turno-editor.agregar-medico') }}">
            @csrf
            <input type="hidden" name="uci_id" value="{{ $uciId }}">
            <input type="hidden" name="mes"    value="{{ $mes }}">
            <input type="hidden" name="anio"   value="{{ $anio }}">
            <div class="modal-body">

                {{-- Nuevo médico --}}
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary-subtle fw-bold small">
                        <i class="bi bi-person-plus me-1"></i>Médico nuevo (entrante)
                    </div>
                    <div class="card-body pb-2">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Médico existente en el sistema</label>
                            <select name="medico_id" class="form-select form-select-sm">
                                <option value="">— Crear médico nuevo —</option>
                                @foreach($todosMedicos as $tm)
                                    <option value="{{ $tm->id }}">{{ $tm->nombre_completo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2 mb-1" id="camposNuevoMedico">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold mb-1">Nombre <span class="text-muted">(si es nuevo)</span></label>
                                <input type="text" name="nombre_nuevo"   class="form-control form-control-sm" placeholder="Nombre">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold mb-1">Apellido</label>
                                <input type="text" name="apellido_nuevo" class="form-control form-control-sm" placeholder="Apellido">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reemplaza a --}}
                @if($medicosExistentes->isNotEmpty())
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning-subtle fw-bold small">
                        <i class="bi bi-arrow-left-right me-1"></i>Reemplaza a (médico saliente)
                    </div>
                    <div class="card-body pb-2">
                        <label class="form-label small fw-semibold mb-1">Seleccione el médico al que reemplaza</label>
                        <select name="reemplaza_medico_id" class="form-select form-select-sm">
                            <option value="">— Nadie (solo agregar) —</option>
                            @foreach($medicosExistentes as $me)
                                <option value="{{ $me->id }}">{{ $me->nombre_completo }}</option>
                            @endforeach
                        </select>
                        <div class="form-text text-warning-emphasis">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Si selecciona un médico, sus turnos de este mes serán eliminados y reemplazados por los del nuevo médico.
                        </div>
                    </div>
                </div>
                @endif

                {{-- Patrón semanal --}}
                <div class="card border-0 bg-light">
                    <div class="card-body pb-1">
                        <label class="form-label fw-bold small mb-2"><i class="bi bi-calendar-week me-1"></i>Patrón semanal del nuevo médico</label>
                        <div class="row g-1">
                            @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $di=>$dl)
                            <div class="col">
                                <label class="form-label small text-center d-block fw-bold {{ in_array($di,[5,6]) ? 'text-warning' : '' }}">{{ $dl }}</label>
                                <select name="patron[{{ $di }}]" class="form-select form-select-sm text-center">
                                    <option value="">—</option>
                                    @foreach(['M','T','MT','N','MTN','MN','PER','INC'] as $c)
                                        <option value="{{ $c }}">{{ $c }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-check me-1"></i>Agregar médico
                </button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal: Editar datos del médico --}}
<div class="modal fade" id="modalEditMedico" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header py-2">
            <h6 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar médico</h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editMedicoId">
            <div class="mb-2">
                <label class="form-label small fw-semibold mb-1">Nombre</label>
                <input type="text" id="editMedicoNombre" class="form-control form-control-sm" placeholder="Nombre">
            </div>
            <div class="mb-0">
                <label class="form-label small fw-semibold mb-1">Apellido</label>
                <input type="text" id="editMedicoApellido" class="form-control form-control-sm" placeholder="Apellido">
            </div>
        </div>
        <div class="modal-footer py-2">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" onclick="guardarEditMedico()">
                <i class="bi bi-check-lg me-1"></i>Guardar
            </button>
        </div>
    </div></div>
</div>

{{-- Modal: Editar celda individual --}}
<div class="modal fade" id="modalCeldaEdit" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header py-2">
            <h6 class="modal-title" id="titulocelda">Cambiar turno</h6>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="small text-muted mb-2" id="infoCelda"></p>
            <div class="d-flex flex-wrap gap-2 justify-content-center" id="botonesCode">
                @foreach(['M','T','MT','N','MTN','MN','PER','INC',''] as $c)
                <button type="button"
                        class="badge-cod badge-{{ $c ?: 'secondary' }} btn"
                        onclick="aplicarCodigo('{{ $c }}')"
                        style="font-size:.85rem;padding:6px 10px">
                    {{ $c ?: '—' }}
                </button>
                @endforeach
            </div>
        </div>
    </div></div>
</div>

@endsection

@push('scripts')
<script>
const TODOS_MEDICOS = @json($todosMedicos->map(fn($m)=>['id'=>$m->id,'nombre'=>$m->nombre_completo]));
const CODIGOS = ['','M','T','MT','N','MTN','MN','PER','INC'];
let seqIdx = 0;

// ── Agregar fila de médico en modal Nueva Secuencia ──
function agregarFilaSeq() {
    const i   = seqIdx++;
    const dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    const opts = CODIGOS.map(c => `<option value="${c}">${c||'—'}</option>`).join('');
    const medOpts = TODOS_MEDICOS.map(m => `<option value="${m.id}">${m.nombre}</option>`).join('');

    const html = `
    <div class="card mb-2" id="seqFila${i}">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <select name="medicos[]" class="form-select form-select-sm" style="max-width:230px" required>
                    <option value="">— Seleccione médico —</option>
                    ${medOpts}
                </select>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="document.getElementById('seqFila${i}').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row g-1">
                ${dias.map((d,di) => `
                <div class="col">
                    <label class="form-label small text-center d-block mb-1">${d}</label>
                    <select name="patrones[__I__][${di}]" class="form-select form-select-sm text-center">${opts}</select>
                </div>`).join('')}
            </div>
        </div>
    </div>`.replace(/__I__/g, i);

    document.getElementById('filasSecuencia').insertAdjacentHTML('beforeend', html);
}

// Agregar primera fila al abrir modal
document.getElementById('modalNuevaSecuencia').addEventListener('show.bs.modal', function() {
    if (document.getElementById('filasSecuencia').children.length === 0) {
        agregarFilaSeq();
    }
});

// ── Edición de celda individual ──
let celdaActual = { turnoId: null, medicoId: null, fecha: null, dia: null };

function abrirCeldaEdit(medicoId, nombre, fecha, codActual, dia) {
    celdaActual = { medicoId, fecha, dia };
    document.getElementById('titulocelda').textContent = nombre;
    document.getElementById('infoCelda').textContent   = `Fecha: ${fecha} — Actual: ${codActual || '—'}`;
    new bootstrap.Modal(document.getElementById('modalCeldaEdit')).show();
}

function aplicarCodigo(codigo) {
    if (!celdaActual.medicoId) return;

    fetch('{{ route("turno-editor.sustituir") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({
            medico_id: celdaActual.medicoId,
            fecha: celdaActual.fecha,
            uci_id: {{ $uciId }},
            codigo_nuevo: codigo,
            archivo_id: {{ $archivo?->id ?? 'null' }},
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalCeldaEdit')).hide();
            location.reload();
        } else {
            alert(data.mensaje || 'Error al guardar.');
        }
    })
    .catch(() => alert('Error de comunicación.'));
}

// ── Editar datos del médico ──
function abrirEditMedico(id, nombre, apellido) {
    document.getElementById('editMedicoId').value       = id;
    document.getElementById('editMedicoNombre').value   = nombre;
    document.getElementById('editMedicoApellido').value = apellido;
    new bootstrap.Modal(document.getElementById('modalEditMedico')).show();
}

function guardarEditMedico() {
    const id       = document.getElementById('editMedicoId').value;
    const nombre   = document.getElementById('editMedicoNombre').value.trim();
    const apellido = document.getElementById('editMedicoApellido').value.trim();

    if (!nombre) { alert('El nombre es obligatorio.'); return; }

    fetch(`/turnos/editor/medico/${id}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ nombre, apellido })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Actualizar el nombre en la celda sin recargar
            const cel = document.getElementById(`nombre-med-${id}`);
            if (cel) {
                // Preserve the button, only change text
                const btn = cel.querySelector('button');
                cel.textContent = data.nombre_completo + ' ';
                if (btn) cel.appendChild(btn);
            }
            bootstrap.Modal.getInstance(document.getElementById('modalEditMedico')).hide();
        } else {
            alert('Error al guardar.');
        }
    })
    .catch(() => alert('Error de comunicación.'));
}
</script>
@endpush
