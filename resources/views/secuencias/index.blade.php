@extends('layouts.app')
@section('title','Secuencias UCI')
@section('page-title','Secuencias de Turno por UCI')
@section('breadcrumb')
    <li class="breadcrumb-item active">Secuencias UCI</li>
@endsection

@push('styles')
<style>
/* ── Tabla de secuencia ─────────────────────────────── */
.seq-tab-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
    border-radius: 6px;
}
.seq-tab-wrapper::-webkit-scrollbar { height: 5px; }
.seq-tab-wrapper::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

.table-seq {
    min-width: 520px;
    font-size: .82rem;
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}
.table-seq th,
.table-seq td {
    padding: .35rem .45rem;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    white-space: nowrap;
}
.table-seq thead th {
    background: #f0f4ff;
    font-weight: 600;
    font-size: .75rem;
    text-align: center;
    color: #3730a3;
}
/* Columna médico: sticky en scroll horizontal */
.col-medico {
    position: sticky;
    left: 0;
    z-index: 2;
    background: #ffffff;
    min-width: 130px;
    max-width: 180px;
    font-weight: 600;
    border-right: 2px solid #c7d2fe !important;
}
.table-seq thead .col-medico {
    background: #e8ecfd;
    z-index: 3;
}
/* Pestañas semana */
.nav-semanas .nav-link {
    font-size: .8rem;
    padding: .3rem .65rem;
    border-radius: 6px 6px 0 0;
    color: #64748b;
    border: 1px solid #dee2e6;
    border-bottom: none;
    background: #f8fafc;
}
.nav-semanas .nav-link.active {
    color: #3730a3;
    background: #fff;
    border-color: #c7d2fe;
    border-bottom-color: #fff;
    font-weight: 600;
}
.tab-content-semana {
    border: 1px solid #c7d2fe;
    border-radius: 0 6px 6px 6px;
    background: #fff;
    padding: .75rem;
}
/* Celda editable */
.celda-seq {
    cursor: pointer;
    text-align: center;
    min-width: 54px;
    transition: background .15s;
}
.celda-seq:hover { background: #eff6ff !important; }
/* Fin de semana */
.dia-finde { background: #f5f3ff; }
/* Indicador scroll móvil */
@media (max-width: 576px) {
    .table-seq { font-size: .76rem; }
    .table-seq th, .table-seq td { padding: .28rem .32rem; }
    .col-medico { min-width: 100px; font-size: .73rem; }
    .celda-seq { min-width: 42px; }
    .nav-semanas .nav-link { font-size: .72rem; padding: .25rem .5rem; }
    .seq-acciones { flex-wrap: wrap; }
}
/* Degradé scroll hint en móvil */
.scroll-hint-wrapper { position: relative; }
@media (max-width: 767px) {
    .scroll-hint-wrapper::after {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 24px; height: 100%;
        background: linear-gradient(to right, transparent, rgba(255,255,255,.85));
        pointer-events: none;
    }
}
/* Modal patrones — tabs semanas */
.nav-sem-modal .nav-link {
    font-size: .75rem;
    padding: .22rem .55rem;
}
/* Badge de turnos inline */
.badge-M   { background:#d1fae5; color:#065f46; }
.badge-T   { background:#dbeafe; color:#1e40af; }
.badge-MT  { background:#e0e7ff; color:#3730a3; }
.badge-N   { background:#1e1b4b; color:#e0e7ff; }
.badge-MTN { background:#7c3aed; color:#ede9fe; }
.badge-MN  { background:#4c1d95; color:#ede9fe; }
.badge-PER { background:#fef9c3; color:#713f12; }
.badge-INC { background:#fee2e2; color:#991b1b; }
.badge-LIBRE{ background:#f1f5f9; color:#475569; }
</style>
@endpush

@section('content')
<div class="fade-in">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold" style="color:#1a2340">
        <i class="bi bi-calendar-week me-2 text-primary"></i>Secuencias por UCI
    </h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaSecuencia">
        <i class="bi bi-plus-circle me-1"></i>Nueva secuencia
    </button>
</div>

{{-- Filtros --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end">
            <div class="col-md-4 col-6">
                <label class="form-label small fw-bold">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" @selected($uciId==$u->id)>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small fw-bold">Año</label>
                <select name="anio" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($anios as $y)
                        <option value="{{ $y }}" @selected($anio==$y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

{{-- Secuencias existentes --}}
@forelse($secuencias as $seq)
@php
    $porMedico = $seq->detalles->groupBy('medico_id');
    // Obtener lista de médicos únicos con su nombre
    $medicosList = $porMedico->map(fn($dets) => $dets->first()->medico);
    $seqId = $seq->id;
@endphp
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title">
            {{ $seq->nombre }}
            <span class="badge bg-{{ $seq->activa ? 'success' : 'secondary' }} ms-2">
                {{ $seq->activa ? 'Activa' : 'Inactiva' }}
            </span>
        </span>
        <div class="d-flex gap-2 seq-acciones">
            <button class="btn btn-sm btn-outline-primary"
                    onclick="abrirAplicarMes({{ $seq->id }},'{{ $seq->nombre }}')">
                <i class="bi bi-calendar-plus me-1"></i><span class="d-none d-sm-inline">Aplicar a </span>mes
            </button>
            <form method="POST" action="{{ route('secuencias.aplicar-anio', $seq) }}"
                  onsubmit="return confirm('¿Aplicar a todo {{ $seq->anio }}? Esto sobreescribirá los 12 meses.')">
                @csrf
                <button class="btn btn-sm btn-outline-success">
                    <i class="bi bi-calendar-range me-1"></i><span class="d-none d-md-inline">Año </span>{{ $seq->anio }}
                </button>
            </form>
            <button class="btn btn-sm btn-outline-info"
                    onclick="abrirAgregarMedico({{ $seq->id }})">
                <i class="bi bi-person-plus me-1"></i><span class="d-none d-sm-inline">Agregar médico</span>
            </button>
            <form method="POST" action="{{ route('secuencias.destroy', $seq) }}" onsubmit="return confirm('¿Desactivar secuencia?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="panel-body">
        <p class="small text-muted mb-3">
            <i class="bi bi-pencil-square me-1 text-primary"></i>
            Toca cualquier celda para editar el turno. Cada semana es <strong>independiente</strong> — los cambios no se propagan entre semanas.
        </p>

        {{-- Tabs: Sem 1, 2, 3, 4 --}}
        <ul class="nav nav-semanas mb-0 ps-1" id="tabsSem-{{ $seqId }}" role="tablist">
            @foreach([1,2,3,4] as $sem)
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $sem===1 ? 'active':'' }}"
                        id="tab-sem{{ $sem }}-{{ $seqId }}"
                        data-bs-toggle="tab"
                        data-bs-target="#pane-sem{{ $sem }}-{{ $seqId }}"
                        type="button" role="tab">
                    <i class="bi bi-calendar2-week me-1"></i>Sem {{ $sem }}
                </button>
            </li>
            @endforeach
        </ul>

        <div class="tab-content-semana">
            <div class="tab-content" id="tabContent-{{ $seqId }}">
                @foreach([1,2,3,4] as $sem)
                <div class="tab-pane fade {{ $sem===1 ? 'show active':'' }}"
                     id="pane-sem{{ $sem }}-{{ $seqId }}"
                     role="tabpanel">
                    <div class="scroll-hint-wrapper mt-1">
                        <div class="seq-tab-wrapper">
                            <table class="table-seq">
                                <thead>
                                    <tr>
                                        <th class="col-medico">Médico</th>
                                        @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $di => $dl)
                                        <th class="{{ $di >= 5 ? 'dia-finde' : '' }}">{{ $dl }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($medicosList as $midId => $medico)
                                    @php
                                        // Detalles de esta semana para este médico
                                        $detsSem = $porMedico[$midId]->where('numero_semana', $sem)->keyBy('dia_semana');
                                        // Vigencia: cualquier detalle de este médico
                                        $vigencia = $porMedico[$midId]->first()?->fecha_inicio_vigencia;
                                    @endphp
                                    <tr>
                                        <td class="col-medico">
                                            {{ $medico?->nombre_completo ?? '—' }}
                                            @if($vigencia)
                                                <br><span class="badge bg-light text-secondary" style="font-size:.65rem">desde {{ $vigencia->format('d/m/Y') }}</span>
                                            @endif
                                        </td>
                                        @for($d = 0; $d <= 6; $d++)
                                        @php $det = $detsSem[$d] ?? null; @endphp
                                        <td class="celda-seq {{ $d >= 5 ? 'dia-finde' : '' }}"
                                            data-det-id="{{ $det?->id ?? '' }}"
                                            data-seq-id="{{ $seqId }}"
                                            data-medico-id="{{ $midId }}"
                                            data-dia="{{ $d }}"
                                            data-semana="{{ $sem }}"
                                            data-codigo="{{ $det?->codigo_turno ?? '' }}"
                                            title="Semana {{ $sem }} — {{ ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][$d] }}">
                                            @if($det?->codigo_turno)
                                                <span class="badge badge-{{ $det->codigo_turno }}">{{ $det->codigo_turno }}</span>
                                            @else
                                                <span class="text-muted" style="opacity:.3;font-size:15px">+</span>
                                            @endif
                                        </td>
                                        @endfor
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">Sin médicos en esta secuencia.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@empty
<div class="panel">
    <div class="panel-body text-center py-4 text-muted">
        <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
        No hay secuencias para esta UCI/año. Cree una con el botón "Nueva secuencia".
    </div>
</div>
@endforelse

</div>{{-- fade-in --}}


{{-- ── Modal: Nueva secuencia ── --}}
<div class="modal fade" id="modalNuevaSecuencia" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Crear nueva secuencia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('secuencias.store') }}" id="formNuevaSeq">
            @csrf
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4 col-12">
                        <label class="form-label fw-bold">UCI <span class="text-danger">*</span></label>
                        <select name="uci_id" class="form-select" required>
                            @foreach($ucis as $u)
                                <option value="{{ $u->id }}" @selected($uciId==$u->id)>{{ $u->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-12">
                        <label class="form-label fw-bold">Nombre de la secuencia <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Secuencia A 2026" required>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">Año <span class="text-danger">*</span></label>
                        <select name="anio" class="form-select">
                            @foreach($anios as $y)<option value="{{ $y }}" @selected($anio==$y)>{{ $y }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="alert alert-info py-2 mb-3" style="font-size:.82rem">
                    <i class="bi bi-info-circle me-1"></i>
                    Defina el patrón de cada médico para las <strong>4 semanas independientes</strong>.
                    El sistema aplicará Sem 1 en la 1ª semana del mes, Sem 2 en la 2ª, etc. (ciclo de 4).
                </div>

                <div id="secuencias-medicos"></div>

                <button type="button" class="btn btn-sm btn-outline-secondary mt-2"
                        onclick="agregarFilaMedico()">
                    <i class="bi bi-plus me-1"></i>Agregar médico
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar secuencia</button>
            </div>
        </form>
    </div></div>
</div>

{{-- ── Modal: Aplicar a mes ── --}}
<div class="modal fade" id="modalAplicarMes" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="tituloAplicarMes">Aplicar secuencia a mes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" id="formAplicarMes">
            @csrf
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Mes</label>
                        <select name="mes" class="form-select">
                            @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i=>$m)
                                <option value="{{ $i+1 }}" @selected(now()->month==$i+1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Año</label>
                        <input type="number" name="anio" value="{{ $anio }}" class="form-control">
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0" style="font-size:.82rem">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Esto sobreescribirá los turnos existentes de esta UCI en el mes seleccionado.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div></div>
</div>

{{-- ── Modal: Agregar médico a secuencia ── --}}
<div class="modal fade" id="modalAgregarMedico" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Agregar médico a secuencia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" id="formAgregarMedico">
            @csrf
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Médico existente</label>
                        <select name="medico_id" class="form-select">
                            <option value="">— Nuevo médico —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Reemplaza a</label>
                        <select name="reemplaza_medico_id" class="form-select">
                            <option value="">— No reemplaza a nadie —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <input type="text" name="nombre_nuevo" class="form-control" placeholder="Nombre (si es nuevo)">
                    </div>
                    <div class="col-6">
                        <input type="text" name="apellido_nuevo" class="form-control" placeholder="Apellido">
                    </div>
                </div>

                <label class="form-label fw-bold">Patrón semanal — 4 semanas independientes</label>
                <ul class="nav nav-tabs nav-sem-modal mb-2" id="tabsAgregar" role="tablist">
                    @foreach([1,2,3,4] as $sem)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $sem===1?'active':'' }}"
                                data-bs-toggle="tab"
                                data-bs-target="#agr-sem{{ $sem }}"
                                type="button">Sem {{ $sem }}</button>
                    </li>
                    @endforeach
                </ul>
                <div class="tab-content border rounded p-2 mb-2">
                    @foreach([1,2,3,4] as $sem)
                    <div class="tab-pane fade {{ $sem===1?'show active':'' }}" id="agr-sem{{ $sem }}">
                        <div class="row g-1 mt-1">
                            @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $di => $dl)
                            <div class="col">
                                <label class="form-label small text-center d-block fw-bold {{ $di>=5 ? 'text-purple':'' }}">{{ $dl }}</label>
                                <select name="patron[{{ $sem }}][{{ $di }}]"
                                        class="form-select form-select-sm text-center">
                                    <option value="">—</option>
                                    @foreach(['M','T','MT','N','MTN','MN','PER','INC','LIBRE'] as $c)
                                        <option value="{{ $c }}">{{ $c }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="small text-muted mb-0">
                    <i class="bi bi-lightbulb me-1 text-warning"></i>
                    Si las semanas tienen el mismo patrón, configura solo Sem 1 — el sistema la usará como base.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Agregar</button>
            </div>
        </form>
    </div></div>
</div>

@endsection

@push('scripts')
<script>
const MEDICOS  = @json($medicos->map(fn($m)=>['id'=>$m->id,'nombre'=>$m->nombre_completo]));
const CODIGOS  = ['','M','T','MT','N','MTN','MN','PER','INC','LIBRE'];
const CSRF_SEQ = '{{ csrf_token() }}';
const DIAS_NOM = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

// ── Edición inline de celdas ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.celda-seq').forEach(td => {
        td.addEventListener('click', function() { iniciarEdicionSeq(this); });
    });
});

async function iniciarEdicionSeq(td) {
    if (td._editando) return;
    td._editando = true;

    const detId   = td.dataset.detId;
    const seqId   = td.dataset.seqId;
    const medicoId= td.dataset.medicoId;
    const dia     = td.dataset.dia;
    const semana  = td.dataset.semana;
    const codigo  = td.dataset.codigo || '';
    const original = td.innerHTML;

    const sel = document.createElement('select');
    sel.className = 'form-select form-select-sm p-0 text-center';
    sel.style.cssText = 'min-width:60px;font-size:11px;font-weight:bold;height:28px;border-radius:4px';

    CODIGOS.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c; opt.textContent = c || '—';
        if (c === codigo) opt.selected = true;
        sel.appendChild(opt);
    });

    td.innerHTML = '';
    td.appendChild(sel);
    sel.focus();

    let guardado = false;

    sel.addEventListener('change', async function() {
        guardado = true;
        const nuevo = this.value;

        // Actualizar SOLO la celda de esta semana específica
        actualizarCelda(td, nuevo, '');

        try {
            let url, method;
            if (detId) {
                url    = `/secuencias/detalle/${detId}`;
                method = 'PATCH';
            } else {
                url    = `/secuencias/${seqId}/celda/${medicoId}/${dia}/${semana}`;
                method = 'PUT';
            }

            const resp = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_SEQ,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ codigo_turno: nuevo }),
            });

            const data = await resp.json();
            if (data.ok) {
                actualizarCelda(td, data.codigo, data.det_id);
            } else {
                actualizarCelda(td, codigo, detId);
            }
        } catch(e) {
            console.error('Error guardando turno:', e);
            actualizarCelda(td, codigo, detId);
        }
    });

    sel.addEventListener('blur', function() {
        td._editando = false;
        if (!guardado) {
            setTimeout(() => {
                td.innerHTML = original;
                td._editando = false;
            }, 150);
        }
    });
}

function actualizarCelda(td, codigo, detId) {
    td._editando = false;
    td.dataset.codigo = codigo;
    if (detId) td.dataset.detId = detId;
    if (codigo) {
        td.innerHTML = `<span class="badge badge-${codigo}">${codigo}</span>`;
    } else {
        td.innerHTML = '<span class="text-muted" style="opacity:.3;font-size:15px">+</span>';
    }
}

// ── Modal: nueva secuencia ── filas de médicos con 4 semanas ──────
let filaIdx = 0;

function agregarFilaMedico() {
    const i    = filaIdx++;
    const opts = CODIGOS.map(c=>`<option value="${c}">${c||'—'}</option>`).join('');

    const tabsHtml = [1,2,3,4].map(sem => `
        <li class="nav-item" role="presentation">
            <button class="nav-link ${sem===1?'active':''} py-1 px-2"
                    style="font-size:.72rem"
                    data-bs-toggle="tab"
                    data-bs-target="#fila${i}-sem${sem}"
                    type="button">Sem ${sem}</button>
        </li>`).join('');

    const panesHtml = [1,2,3,4].map(sem => {
        const cols = DIAS_NOM.map((d,di) => `
            <div class="col">
                <label class="form-label small text-center d-block mb-1" style="font-size:.7rem">${d}</label>
                <select name="patrones[${i}][${sem}][${di}]"
                        class="form-select form-select-sm text-center p-0"
                        style="font-size:.72rem">${opts}</select>
            </div>`).join('');
        return `
        <div class="tab-pane fade ${sem===1?'show active':''}" id="fila${i}-sem${sem}">
            <div class="row g-1 pt-2">${cols}</div>
        </div>`;
    }).join('');

    const html = `
    <div class="card mb-2 border-primary border-opacity-25" id="fila-${i}">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2 mb-2">
                <select name="medicos[]" class="form-select form-select-sm" style="max-width:230px">
                    ${MEDICOS.map(m=>`<option value="${m.id}">${m.nombre}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                        onclick="document.getElementById('fila-${i}').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <ul class="nav nav-tabs mb-0" style="border-bottom:1px solid #dee2e6">${tabsHtml}</ul>
            <div class="tab-content border border-top-0 rounded-bottom p-2">${panesHtml}</div>
        </div>
    </div>`;

    document.getElementById('secuencias-medicos').insertAdjacentHTML('beforeend', html);
}

// Nota: la form de nueva secuencia usa patrones[filaIdx][semana][dia]
// pero el controller espera patrones[medico_id][semana][dia].
// Antes de submit, remapeamos:
document.getElementById('formNuevaSeq').addEventListener('submit', function(e) {
    // Reemplazar índice de fila por medico_id para cada fila
    const filas = document.querySelectorAll('#secuencias-medicos .card');
    filas.forEach(fila => {
        const medicoSel = fila.querySelector('select[name="medicos[]"]');
        const medicoId  = medicoSel?.value;
        if (!medicoId) return;

        // Renombrar todos los selects de patrones de esta fila
        const sels = fila.querySelectorAll('select[name^="patrones["]');
        sels.forEach(s => {
            // nombre actual: patrones[i][sem][dia]
            s.name = s.name.replace(/^patrones\[\d+\]/, `patrones[${medicoId}]`);
        });
    });
});

function abrirAplicarMes(seqId, nombre) {
    document.getElementById('tituloAplicarMes').textContent = 'Aplicar: ' + nombre;
    document.getElementById('formAplicarMes').action = `/secuencias/${seqId}/aplicar-mes`;
    new bootstrap.Modal(document.getElementById('modalAplicarMes')).show();
}

function abrirAgregarMedico(seqId) {
    document.getElementById('formAgregarMedico').action = `/secuencias/${seqId}/agregar-medico`;
    new bootstrap.Modal(document.getElementById('modalAgregarMedico')).show();
}

// Inicializar con una fila vacía
agregarFilaMedico();
</script>
@endpush
