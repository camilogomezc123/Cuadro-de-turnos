@extends('layouts.app')
@section('title','Secuencias UCI')
@section('page-title','Secuencias de Turno por UCI')
@section('breadcrumb')
    <li class="breadcrumb-item active">Secuencias UCI</li>
@endsection

@section('content')
<div class="fade-in">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
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
            <div class="col-md-4">
                <label class="form-label small fw-bold">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" @selected($uciId==$u->id)>{{ $u->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
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
<div class="panel mb-4">
    <div class="panel-header">
        <span class="panel-title">
            {{ $seq->nombre }}
            <span class="badge bg-{{ $seq->activa ? 'success' : 'secondary' }} ms-2">
                {{ $seq->activa ? 'Activa' : 'Inactiva' }}
            </span>
        </span>
        <div class="d-flex gap-2">
            {{-- Aplicar a mes --}}
            <button class="btn btn-sm btn-outline-primary"
                    onclick="abrirAplicarMes({{ $seq->id }},'{{ $seq->nombre }}')">
                <i class="bi bi-calendar-plus me-1"></i>Aplicar a mes
            </button>
            {{-- Aplicar año completo --}}
            <form method="POST" action="{{ route('secuencias.aplicar-anio', $seq) }}"
                  onsubmit="return confirm('¿Aplicar esta secuencia a todo el año {{ $seq->anio }}? Esto sobreescribirá los 12 meses.')">
                @csrf
                <button class="btn btn-sm btn-outline-success">
                    <i class="bi bi-calendar-range me-1"></i>Año {{ $seq->anio }} completo
                </button>
            </form>
            {{-- Agregar médico --}}
            <button class="btn btn-sm btn-outline-info"
                    onclick="abrirAgregarMedico({{ $seq->id }})">
                <i class="bi bi-person-plus me-1"></i>Agregar médico
            </button>
            {{-- Desactivar --}}
            <form method="POST" action="{{ route('secuencias.destroy', $seq) }}" onsubmit="return confirm('¿Desactivar secuencia?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-custom" style="font-size:.82rem">
                <thead>
                    <tr>
                        <th>Médico</th>
                        <th>Lun</th><th>Mar</th><th>Mié</th><th>Jue</th><th>Vie</th><th>Sáb</th><th>Dom</th>
                        <th>Vigencia</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $porMedico = $seq->detalles->groupBy('medico_id');
                    @endphp
                    @forelse($porMedico as $midId => $dets)
                    @php
                        $medico = $dets->first()->medico;
                        $diasMap = $dets->keyBy('dia_semana');
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $medico?->nombre_completo ?? '—' }}</td>
                        @for($d = 0; $d <= 6; $d++)
                            @php $det = $diasMap[$d] ?? null; @endphp
                            <td>
                                @if($det?->codigo_turno)
                                    <span class="badge badge-{{ $det->codigo_turno }}">{{ $det->codigo_turno }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endfor
                        <td>
                            <small>
                                {{ $dets->first()?->fecha_inicio_vigencia?->format('d/m/Y') ?? '—' }}
                                @if($dets->first()?->fecha_fin_vigencia)
                                    → {{ $dets->first()->fecha_fin_vigencia->format('d/m/Y') }}
                                @endif
                            </small>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-muted text-center">Sin médicos en esta secuencia.</td></tr>
                    @endforelse
                </tbody>
            </table>
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

</div>

{{-- Modal: Nueva secuencia --}}
<div class="modal fade" id="modalNuevaSecuencia" tabindex="-1">
    <div class="modal-dialog modal-xl"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Crear nueva secuencia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('secuencias.store') }}" id="formNuevaSeq">
            @csrf
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">UCI <span class="text-danger">*</span></label>
                        <select name="uci_id" id="seqUciId" class="form-select" required>
                            @foreach($ucis as $u)
                                <option value="{{ $u->id }}" @selected($uciId==$u->id)>{{ $u->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Nombre de la secuencia <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej: Secuencia A 2026" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Año <span class="text-danger">*</span></label>
                        <select name="anio" class="form-select">
                            @foreach($anios as $y)<option value="{{ $y }}" @selected($anio==$y)>{{ $y }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <p class="text-muted small mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Ingrese los códigos de turno para cada día de la semana por médico.
                    <strong>Lunes–Viernes:</strong> secuencia fija.
                    <strong>Sábado–Domingo:</strong> se aplican también pero puede dejarlos en blanco para fines de semana rotativos.
                </p>

                <div id="secuencias-medicos">
                    {{-- Se agrega dinámicamente --}}
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="agregarFilaMedico()">
                    <i class="bi bi-plus me-1"></i>Agregar médico
                </button>
                <div id="medicosSelIds"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar secuencia</button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal: Aplicar a mes --}}
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
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mes</label>
                        <select name="mes" class="form-select">
                            @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i=>$m)
                                <option value="{{ $i+1 }}" @selected(now()->month==$i+1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Año</label>
                        <input type="number" name="anio" value="{{ $anio }}" class="form-control">
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
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

{{-- Modal: Agregar médico a secuencia --}}
<div class="modal fade" id="modalAgregarMedico" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Agregar médico a secuencia</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="" id="formAgregarMedico">
            @csrf
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Médico existente</label>
                    <select name="medico_id" class="form-select">
                        <option value="">— Nuevo médico —</option>
                        @foreach($medicos as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col"><input type="text" name="nombre_nuevo" class="form-control" placeholder="Nombre (si es nuevo)"></div>
                    <div class="col"><input type="text" name="apellido_nuevo" class="form-control" placeholder="Apellido"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reemplaza a</label>
                    <select name="reemplaza_medico_id" class="form-select">
                        <option value="">— No reemplaza a nadie —</option>
                        @foreach($medicos as $m)
                            <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="form-label fw-bold">Patrón semanal</label>
                <div class="row g-1">
                    @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $di => $dl)
                    <div class="col">
                        <label class="form-label small text-center d-block">{{ $dl }}</label>
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
const MEDICOS = @json($medicos->map(fn($m)=>['id'=>$m->id,'nombre'=>$m->nombre_completo]));
const CODIGOS  = ['','M','T','MT','N','MTN','MN','PER','INC','LIBRE'];
let filaIdx = 0;

function agregarFilaMedico() {
    const i = filaIdx++;
    const dias = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
    const opts  = CODIGOS.map(c=>`<option value="${c}">${c||'—'}</option>`).join('');

    const html = `
    <div class="card mb-2" id="fila-${i}">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2 mb-2">
                <select name="medicos[]" class="form-select form-select-sm" style="max-width:220px">
                    ${MEDICOS.map(m=>`<option value="${m.id}">${m.nombre}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="document.getElementById('fila-${i}').remove()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row g-1">
                ${dias.map((d,di)=>`
                <div class="col">
                    <label class="form-label small text-center d-block fw-bold">${d}</label>
                    <select name="patrones[__IDX__][${di}]" class="form-select form-select-sm text-center">${opts}</select>
                </div>`).join('')}
            </div>
        </div>
    </div>`.replace(/__IDX__/g, i);

    document.getElementById('secuencias-medicos').insertAdjacentHTML('beforeend', html);
}

// Necesitamos que los names de patrones lleven medico_id real al submit
// Hack: al submit reescribimos los nombres según el select del médico
document.getElementById('formNuevaSeq')?.addEventListener('submit', function(e) {
    // noop: usamos filaIdx como key temporal, el server agrupa por medico
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

// Agregar una fila inicial al cargar
agregarFilaMedico();
</script>
@endpush
