@extends('layouts.app')
@section('title', 'Formato Inicial')
@section('page-title', 'Formato Inicial de Turnos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Formato Inicial</li>
@endsection

@push('head-styles')
<style>
.turno-celda {
    width: 36px; height: 30px;
    text-align: center; font-size: 11px; font-weight: 700;
    border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer;
    padding: 2px; outline: none; background: #f8fafc;
    text-transform: uppercase;
}
.turno-celda:focus { border-color: #1565C0; background: #EBF5FF; }
.th-dia-mini {
    width: 36px; min-width: 36px; text-align: center;
    font-size: 10px; padding: 2px 0; color: #64748b;
}
.th-dia-mini.finde { background: #f0f9ff; }
.th-dia-mini.dom   { background: #fdf4ff; }
</style>
@endpush

@section('content')

{{-- TABS --}}
<ul class="nav nav-tabs mb-4" id="formatoTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-excel">
            <i class="bi bi-file-earmark-excel me-1 text-success"></i>Cargar desde Excel
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-manual">
            <i class="bi bi-pencil-square me-1 text-primary"></i>Crear manualmente
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-plantillas">
            <i class="bi bi-calendar-range me-1 text-warning"></i>Plantillas y Repetir año
        </button>
    </li>
</ul>

<div class="tab-content">

    {{-- TAB 1: Cargar Excel --}}
    <div class="tab-pane fade show active" id="tab-excel">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title"><i class="bi bi-cloud-upload me-2 text-success"></i>Cargar Excel como Plantilla Base</span>
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('formato-inicial.excel') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Nombre de la plantilla</label>
                                <input type="text" name="nombre" class="form-control form-control-sm"
                                       placeholder="Ej: Patrón Base UCI Torre C" required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Mes base</label>
                                    <select name="mes" class="form-select form-select-sm" required>
                                        @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $mn)
                                            <option value="{{ $i+1 }}" {{ $i+1 == date('n') ? 'selected':'' }}>{{ $mn }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Año</label>
                                    <select name="anio" class="form-select form-select-sm" required>
                                        @for($y = 2024; $y <= 2030; $y++)
                                            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected':'' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Archivo Excel (.xlsx)</label>
                                <input type="file" name="archivo" class="form-control form-control-sm"
                                       accept=".xlsx,.xls" required>
                                <div class="form-text">Mismo formato que importación: Fila 1 = UCI, F2 = letras día, F3 = números día.</div>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="bi bi-cloud-upload me-1"></i>Cargar y crear plantilla
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="panel h-100">
                    <div class="panel-header">
                        <span class="panel-title"><i class="bi bi-info-circle me-2"></i>¿Cómo funciona?</span>
                    </div>
                    <div class="panel-body">
                        <ol class="small text-secondary" style="line-height:2">
                            <li><strong>Carga un Excel</strong> con el cuadro base de un mes (el formato estándar).</li>
                            <li>El sistema extrae el <strong>patrón semanal</strong> de cada médico (qué turno tiene cada día de la semana).</li>
                            <li>En la pestaña <em>"Plantillas y Repetir año"</em>, selecciona la plantilla y elige el año.</li>
                            <li>El sistema genera <strong>todos los meses del año</strong> aplicando el mismo patrón semanal automáticamente.</li>
                        </ol>
                        <div class="alert alert-info py-2 small mt-3">
                            <i class="bi bi-lightbulb me-1"></i>
                            El patrón se extrae por día de semana: si un médico tiene <strong>M</strong> los lunes en el mes base,
                            todos los lunes del año quedarán como <strong>M</strong>.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TAB 2: Crear manualmente --}}
    <div class="tab-pane fade" id="tab-manual">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Crear Cuadro Manualmente</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('formato-inicial.manual') }}" id="form-manual">
                    @csrf
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <label class="form-label small fw-semibold">Nombre de la plantilla</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" required
                                   placeholder="Ej: Patrón Manual UCI Torre C">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label small fw-semibold">UCI</label>
                            <select name="uci_id" id="sel-uci" class="form-select form-select-sm" required>
                                @foreach($ucis as $u)
                                    <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold">Mes</label>
                            <select name="mes" id="sel-mes" class="form-select form-select-sm" required onchange="generarGrilla()">
                                @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $mn)
                                    <option value="{{ $i+1 }}" {{ $i+1 == date('n') ? 'selected':'' }}>{{ $mn }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-semibold">Año</label>
                            <select name="anio" id="sel-anio" class="form-select form-select-sm" required onchange="generarGrilla()">
                                @for($y = 2024; $y <= 2030; $y++)
                                    <option value="{{ $y }}" {{ $y == date('Y') ? 'selected':'' }}>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-1 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="generarGrilla()">
                                <i class="bi bi-table"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Selector de médicos --}}
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Agregar médicos al cuadro</label>
                        <div class="d-flex gap-2">
                            <select id="sel-medico" class="form-select form-select-sm" style="max-width:320px">
                                <option value="">— Seleccionar médico —</option>
                                @foreach($medicos as $m)
                                    <option value="{{ $m->id }}" data-nombre="{{ $m->nombre }}">{{ $m->nombre }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-primary btn-sm" onclick="agregarMedico()">
                                <i class="bi bi-plus"></i> Agregar
                            </button>
                        </div>
                    </div>

                    {{-- Leyenda de códigos --}}
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        @foreach(['M','T','MT','N','MTN','MN','VAC','PER','INC','LIBRE',''] as $cod)
                        <span class="turno-badge badge-{{ $cod ?: 'LIBRE' }}"
                              style="cursor:pointer;font-size:10px"
                              onclick="rellenarSeleccionado('{{ $cod }}')">
                            {{ $cod ?: '—' }}
                        </span>
                        @endforeach
                        <span class="text-muted small d-flex align-items-center">← click para aplicar a celda seleccionada</span>
                    </div>

                    <div id="grilla-manual" class="table-responsive">
                        <p class="text-muted small text-center py-3">
                            <i class="bi bi-arrow-up"></i> Seleccione mes, año y agregue médicos para generar la grilla.
                        </p>
                    </div>

                    <div class="mt-3 d-none" id="btn-guardar-manual">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i>Guardar cuadro como plantilla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- TAB 3: Plantillas + Repetir --}}
    <div class="tab-pane fade" id="tab-plantillas">
        @if($plantillas->isEmpty())
        <div class="panel p-5 text-center">
            <i class="bi bi-calendar-range text-warning" style="font-size:3rem;opacity:.3"></i>
            <h6 class="mt-3 text-muted">No hay plantillas creadas aún</h6>
            <p class="text-muted small">Cargue un Excel o cree un cuadro manual en las pestañas anteriores.</p>
        </div>
        @else
        <div class="row g-3">
            @foreach($plantillas as $plt)
            <div class="col-md-6 col-xl-4">
                <div class="panel">
                    <div class="panel-header d-flex justify-content-between">
                        <span class="panel-title fw-semibold">{{ $plt->nombre }}</span>
                        <form method="POST" action="{{ route('formato-inicial.destroy', $plt) }}"
                              onsubmit="return confirm('¿Eliminar plantilla?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm btn-xs">
                                <i class="bi bi-trash" style="font-size:10px"></i>
                            </button>
                        </form>
                    </div>
                    <div class="panel-body">
                        <div class="small text-muted mb-2">
                            {{ $plt->descripcion ?? '' }}<br>
                            <strong>Base:</strong> {{ $plt->nombre_mes_base }} {{ $plt->anio_base }}
                            @if($plt->uci)
                            · <strong>UCI:</strong> {{ $plt->uci->nombre }}
                            @endif
                        </div>
                        @if($plt->anios_generados)
                        <div class="mb-2">
                            <span class="text-muted small">Años generados:</span>
                            @foreach($plt->anios_generados as $ay)
                                <span class="badge bg-success-subtle text-success ms-1">{{ $ay }}</span>
                            @endforeach
                        </div>
                        @endif

                        <form method="POST" action="{{ route('formato-inicial.repetir') }}">
                            @csrf
                            <input type="hidden" name="plantilla_id" value="{{ $plt->id }}">
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-semibold mb-1">Repetir para el año:</label>
                                    <select name="anio_destino" class="form-select form-select-sm">
                                        @for($y = 2024; $y <= 2030; $y++)
                                            <option value="{{ $y }}" {{ $y == date('Y') ? 'selected':'' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning btn-sm"
                                        onclick="return confirm('¿Generar todos los meses del año seleccionado?')">
                                    <i class="bi bi-arrow-repeat me-1"></i>Repetir año
                                </button>
                            </div>
                        </form>

                        <div class="mt-2">
                            @if($plt->archivo_base_id)
                            <a href="{{ route('calendario.index', ['archivo_id'=>$plt->archivo_base_id]) }}"
                               class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-eye me-1"></i>Ver mes base en calendario
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>{{-- /tab-content --}}

@endsection

@push('scripts')
<script>
const DIAS_SEMANA = ['L','M','M','J','V','S','D'];
let medicosEnGrilla = {}; // { medicoId: nombre }
let celdaActiva = null;

function diasEnMes(mes, anio) {
    return new Date(anio, mes, 0).getDate();
}

function primerDow(mes, anio) {
    // 0=Dom ... 6=Sab → convertir a 0=Lun...6=Dom
    const d = new Date(anio, mes - 1, 1).getDay();
    return d === 0 ? 6 : d - 1;
}

function esFinde(dow) { return dow === 5 || dow === 6; }
function esDom(dow)   { return dow === 6; }

function generarGrilla() {
    const mes  = parseInt(document.getElementById('sel-mes').value);
    const anio = parseInt(document.getElementById('sel-anio').value);
    const dias = diasEnMes(mes, anio);
    renderGrilla(dias, mes, anio);
}

function agregarMedico() {
    const sel    = document.getElementById('sel-medico');
    const mId    = sel.value;
    const nombre = sel.options[sel.selectedIndex]?.dataset?.nombre;
    if (!mId || !nombre || medicosEnGrilla[mId]) return;
    medicosEnGrilla[mId] = nombre;
    const mes  = parseInt(document.getElementById('sel-mes').value);
    const anio = parseInt(document.getElementById('sel-anio').value);
    renderGrilla(diasEnMes(mes, anio), mes, anio);
}

function renderGrilla(dias, mes, anio) {
    if (!dias) return;
    const wrapper = document.getElementById('grilla-manual');
    if (Object.keys(medicosEnGrilla).length === 0) {
        wrapper.innerHTML = '<p class="text-muted small text-center py-3">Agregue médicos para ver la grilla.</p>';
        return;
    }

    let html = '<table class="table table-bordered" style="min-width:1200px;font-size:11px">';
    // Header letras días
    html += '<thead><tr><th style="min-width:160px;background:#1e293b;color:#fff">Médico</th>';
    for (let d = 1; d <= dias; d++) {
        const f   = new Date(anio, mes - 1, d);
        const dow = f.getDay() === 0 ? 6 : f.getDay() - 1;
        const cls = esDom(dow) ? 'style="background:#f3e8ff"' : (esFinde(dow) ? 'style="background:#e0f2fe"' : '');
        html += `<th class="th-dia-mini" ${cls}>${DIAS_SEMANA[dow]}<br><strong>${d}</strong></th>`;
    }
    html += '<th style="background:#1e293b;color:#fff;min-width:50px">Total</th>';
    html += '</tr></thead><tbody>';

    for (const [mId, nombre] of Object.entries(medicosEnGrilla)) {
        html += `<tr><td style="font-weight:600;padding:4px 8px;white-space:nowrap;background:#f8fafc;position:sticky;left:0">
            ${nombre}
            <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-danger" onclick="quitarMedico('${mId}')" style="font-size:10px">✕</button>
        </td>`;
        for (let d = 1; d <= dias; d++) {
            const f   = new Date(anio, mes - 1, d);
            const dow = f.getDay() === 0 ? 6 : f.getDay() - 1;
            const bg  = esDom(dow) ? '#fdf4ff' : (esFinde(dow) ? '#f0f9ff' : '#fff');
            html += `<td style="padding:2px;background:${bg}">
                <input type="text" name="turnos[${mId}][${d}]"
                       class="turno-celda" maxlength="5"
                       oninput="this.value=this.value.toUpperCase()"
                       onfocus="celdaActiva=this"
                       onblur="colorearCelda(this)"
                       style="background:${bg}">
            </td>`;
        }
        html += '<td id="total-' + mId + '" style="font-weight:700;text-align:center;background:#f8fafc">0h</td>';
        html += '</tr>';
    }
    html += '</tbody></table>';
    wrapper.innerHTML = html;
    document.getElementById('btn-guardar-manual').classList.remove('d-none');

    // Escuchar cambios para totalizar
    wrapper.querySelectorAll('.turno-celda').forEach(inp => {
        inp.addEventListener('input', () => {
            const name = inp.name; // turnos[mId][d]
            const match = name.match(/turnos\[(\d+)\]/);
            if (match) actualizarTotal(match[1]);
        });
    });
}

function quitarMedico(mId) {
    delete medicosEnGrilla[mId];
    const mes  = parseInt(document.getElementById('sel-mes').value);
    const anio = parseInt(document.getElementById('sel-anio').value);
    renderGrilla(diasEnMes(mes, anio), mes, anio);
}

function actualizarTotal(mId) {
    const HORAS = {M:6,T:6,MT:12,N:12,MTN:24,MN:18,VAC:0,PER:0,INC:0,'':0,LIBRE:0};
    let total = 0;
    document.querySelectorAll(`input[name^="turnos[${mId}]"]`).forEach(inp => {
        total += HORAS[inp.value.trim().toUpperCase()] || 0;
    });
    const td = document.getElementById('total-' + mId);
    if (td) td.textContent = total + 'h';
}

function colorearCelda(inp) {
    const COLORES = {
        M:'#E3F2FD',T:'#E8F5E9',MT:'#E0F7FA',N:'#EDE7F6',
        MTN:'#FCE4EC',MN:'#FFF3E0',VAC:'#EFEBE9',PER:'#ECEFF1',INC:'#FBE9E7'
    };
    const v = inp.value.trim().toUpperCase();
    inp.style.background = COLORES[v] || '#f8fafc';
}

function rellenarSeleccionado(codigo) {
    if (celdaActiva) {
        celdaActiva.value = codigo;
        colorearCelda(celdaActiva);
        const match = celdaActiva.name.match(/turnos\[(\d+)\]/);
        if (match) actualizarTotal(match[1]);
    }
}

// Generar grilla al cargar con mes/año actual
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#tab-manual select').forEach(s => {
        s.addEventListener('change', generarGrilla);
    });
});
</script>
@endpush
