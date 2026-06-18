@extends('layouts.app')
@section('title','Cargar Secuencia / Calendario UCI')
@section('page-title','Importar Turnos desde Excel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('secuencias.index') }}">Secuencias</a></li>
    <li class="breadcrumb-item active">Cargar Excel</li>
@endsection

@section('content')
@php $tabActivo = session('tab_activo', 'calendario'); @endphp
<div class="fade-in">

{{-- Alertas --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('success_cal'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success_cal') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->has('excel_cal'))
<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('excel_cal') }}
</div>
@endif
@if($errors->has('excel'))
<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first('excel') }}
</div>
@endif

{{-- TABS --}}
<ul class="nav nav-tabs mb-4" id="tabsCarga">
    <li class="nav-item">
        <button class="nav-link {{ $tabActivo==='calendario' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#tabCalendario">
            <i class="bi bi-calendar-month me-1"></i>Importar cuadro mensual
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tabActivo==='secuencia' ? 'active' : '' }}"
                data-bs-toggle="tab" data-bs-target="#tabSecuencia">
            <i class="bi bi-arrow-repeat me-1"></i>Guardar secuencia base (patrón)
        </button>
    </li>
</ul>

<div class="tab-content">

{{-- ═══════════════════════════════════════════════════════
     TAB 1: IMPORTAR CUADRO MENSUAL DIRECTO
═══════════════════════════════════════════════════════ --}}
<div class="tab-pane fade {{ $tabActivo==='calendario' ? 'show active' : '' }}" id="tabCalendario">
<div class="row g-4">

{{-- Formulario de importación --}}
<div class="col-lg-5">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-upload me-2 text-primary"></i>Cargar cuadro mensual por UCI</span>
        </div>
        <div class="panel-body">

            <div class="alert alert-info small mb-3">
                <strong><i class="bi bi-info-circle me-1"></i>Formato aceptado:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li><strong>Columna A:</strong> Nombre del médico</li>
                    <li><strong>Columnas B en adelante:</strong> Un día del mes por columna
                        <br><span class="text-muted">— Con encabezado de números (1, 2, 3 … 31) en la primera fila, <strong>o</strong></span>
                        <br><span class="text-muted">— Sin encabezado: sistema asume que col B = día 1, col C = día 2, etc.</span>
                    </li>
                    <li>Cada celda contiene el código de turno: <code>M</code>, <code>T</code>, <code>MT</code>, <code>N</code>, <code>MTN</code>, <code>MN</code>, <code>PER</code>, <code>INC</code>, o vacía</li>
                    <li>Celdas vacías o con otro texto se ignoran (no generan turno)</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('secuencias.importar-calendario') }}"
                  enctype="multipart/form-data" id="formCalendario">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">UCI <span class="text-danger">*</span></label>
                    <select name="uci_id" class="form-select" required>
                        <option value="">Seleccione la UCI...</option>
                        @foreach($ucis as $u)
                            <option value="{{ $u->id }}" @selected(old('uci_id')==$u->id)>{{ $u->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label fw-bold">Mes <span class="text-danger">*</span></label>
                        <select name="mes" class="form-select" required>
                            @foreach($meses as $i=>$m)
                                <option value="{{ $i+1 }}" @selected(old('mes',now()->month)==$i+1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label fw-bold">Año <span class="text-danger">*</span></label>
                        <select name="anio" class="form-select" required>
                            @foreach($anios as $y)
                                <option value="{{ $y }}" @selected(old('anio',now()->year)==$y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Archivo Excel <span class="text-danger">*</span></label>
                    <input type="file" name="excel" class="form-control" accept=".xlsx,.xls"
                           id="inputCalExcel" required>
                </div>
                <div id="prevCalNombre" class="d-none mb-2">
                    <span class="badge bg-success-subtle text-success rounded-pill">
                        <i class="bi bi-file-earmark-excel me-1"></i>
                        <span id="calNombreArch"></span>
                    </span>
                </div>
                <button type="submit" class="btn btn-primary w-100" id="btnImportar">
                    <i class="bi bi-cloud-upload me-2"></i>Importar turnos
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Panel de ayuda visual --}}
<div class="col-lg-7">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-table me-2 text-success"></i>Ejemplo de formato</span>
        </div>
        <div class="panel-body">
            <p class="text-muted small mb-3">
                El sistema detecta automáticamente si el archivo tiene o no encabezado de números.
                Ambos formatos son válidos:
            </p>

            {{-- Formato CON encabezado --}}
            <p class="fw-bold small mb-1"><i class="bi bi-check-circle text-success me-1"></i>Con encabezado de días (recomendado):</p>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm" style="font-size:.78rem;max-width:500px">
                    <thead class="table-light">
                        <tr>
                            <th>Médico</th>
                            <th>1</th><th>2</th><th>3</th><th>4</th><th>5</th>
                            <th>6</th><th>7</th><th>…</th><th>31</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Diego Escobar</td><td>MT</td><td></td><td>M</td><td>M</td><td>MT</td><td>MTN</td><td></td><td class="text-muted">…</td><td>N</td></tr>
                        <tr><td>Julian Zabala</td><td>N</td><td>N</td><td>N</td><td>T</td><td></td><td></td><td></td><td class="text-muted">…</td><td></td></tr>
                        <tr><td>Jacobo Cardona</td><td></td><td></td><td>T</td><td></td><td></td><td></td><td></td><td class="text-muted">…</td><td></td></tr>
                    </tbody>
                </table>
            </div>

            {{-- Formato SIN encabezado --}}
            <p class="fw-bold small mb-1"><i class="bi bi-check-circle text-success me-1"></i>Sin encabezado (col B = día 1, col C = día 2…):</p>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm" style="font-size:.78rem;max-width:500px">
                    <thead class="table-light">
                        <tr>
                            <th>Médico</th>
                            <th>B</th><th>C</th><th>D</th><th>E</th><th>F</th>
                            <th>G</th><th>H</th><th>…</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Diego Escobar</td><td>MT</td><td></td><td>M</td><td>M</td><td>MT</td><td>MTN</td><td></td><td class="text-muted">…</td></tr>
                        <tr><td>Julian Zabala</td><td>N</td><td>N</td><td>N</td><td>T</td><td></td><td></td><td></td><td class="text-muted">…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <strong>Nota:</strong> Si ya hay turnos cargados para esa UCI y mes, serán <strong>reemplazados</strong> por los del nuevo archivo.
            </div>
        </div>
    </div>
</div>

</div>{{-- /row --}}
</div>{{-- /tab calendario --}}


{{-- ═══════════════════════════════════════════════════════
     TAB 2: GUARDAR SECUENCIA PATRÓN SEMANAL
═══════════════════════════════════════════════════════ --}}
<div class="tab-pane fade {{ $tabActivo==='secuencia' ? 'show active' : '' }}" id="tabSecuencia">
<div class="row g-4">

<div class="col-lg-5">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Guardar como secuencia base</span>
        </div>
        <div class="panel-body">

            <div class="alert alert-info small mb-3">
                <strong><i class="bi bi-info-circle me-1"></i>Para qué sirve:</strong>
                <p class="mb-1 mt-1">Guarda el patrón semanal (Lun–Vie fijo + fines de semana rotativos) y te permite <strong>aplicarlo a múltiples meses automáticamente</strong>.</p>
                <strong>Formato del Excel:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li>Columna A: nombre del médico</li>
                    <li>Fila 2: letras <code>L M M J V S D</code> repitiendo por semanas</li>
                    <li>Los turnos de Lun–Vie se toman como patrón fijo</li>
                    <li>Los fines de semana se detectan como rotación</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('secuencias.parsear-excel') }}"
                  enctype="multipart/form-data" id="formSecuencia">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">UCI <span class="text-danger">*</span></label>
                    <select name="uci_id" class="form-select" required>
                        <option value="">Seleccione la UCI...</option>
                        @foreach($ucis as $u)
                            <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre de la secuencia <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control"
                           placeholder="Ej: Secuencia Torre C 2026" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Año base</label>
                    <select name="anio" class="form-select" required>
                        @foreach($anios as $y)
                            <option value="{{ $y }}" @selected(now()->year==$y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Archivo Excel (con fila L/M/M/J/V/S/D) <span class="text-danger">*</span></label>
                    <input type="file" name="excel" class="form-control" accept=".xlsx,.xls" id="inputSeqExcel" required>
                </div>
                <div id="prevSeqNombre" class="d-none mb-2">
                    <span class="badge bg-primary-subtle text-primary rounded-pill">
                        <i class="bi bi-file-earmark-excel me-1"></i>
                        <span id="seqNombreArch"></span>
                    </span>
                </div>
                <button type="submit" class="btn btn-primary w-100" id="btnSecuencia">
                    <i class="bi bi-gear me-2"></i>Procesar y guardar secuencia
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Aplicar meses (si hay preview de secuencia) --}}
<div class="col-lg-7">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-calendar-range me-2 text-success"></i>Aplicar secuencia a meses</span>
        </div>
        <div class="panel-body">
            @if($preview)
            <div class="alert alert-success mb-3">
                <div class="fw-bold"><i class="bi bi-check-circle me-2"></i>Secuencia guardada</div>
                <div class="small mt-1">
                    <strong>{{ $preview['nombre'] }}</strong> · {{ $preview['uci'] }} · {{ $preview['anio'] }}<br>
                    Médicos: {{ implode(', ', $preview['doctores']) }}
                </div>
            </div>

            <form method="POST" action="{{ route('secuencias.aplicar-meses', $preview['id']) }}">
                @csrf
                <p class="fw-bold mb-2">Selecciona los meses a generar:</p>
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSeq(true)">
                        <i class="bi bi-check-all me-1"></i>Todo {{ $preview['anio'] }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleSeq(false)">
                        <i class="bi bi-x me-1"></i>Ninguno
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="seqRestantes()">
                        <i class="bi bi-calendar-check me-1"></i>Restantes del año
                    </button>
                </div>
                <div class="row g-2 mb-4">
                    @foreach($meses as $i => $m)
                    @php $mn = $i + 1; @endphp
                    <div class="col-6 col-md-4 col-xl-3">
                        <label class="mes-card {{ $mn >= now()->month ? '' : 'pasado' }}" for="seqm{{ $mn }}">
                            <input type="checkbox" name="meses_anio[]"
                                   value="{{ $mn }}-{{ $preview['anio'] }}"
                                   id="seqm{{ $mn }}" class="chk-seq"
                                   @checked($mn >= now()->month)>
                            <div class="mes-nombre">{{ $m }}</div>
                            <div class="mes-anio">{{ $preview['anio'] }}</div>
                        </label>
                    </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-calendar-plus me-2"></i>Generar turnos para los meses seleccionados
                </button>
            </form>
            @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-arrow-left-circle fs-1 d-block mb-3 text-primary"></i>
                <p>Sube el Excel con la secuencia (formulario de la izquierda).<br>
                Aquí aparecerán los meses disponibles para aplicar.</p>
                <hr class="my-4">
                <p class="small"><strong>¿Ya tienes secuencias guardadas?</strong></p>
                <a href="{{ route('secuencias.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list me-1"></i>Ver secuencias existentes
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

</div>{{-- /row --}}
</div>{{-- /tab secuencia --}}

</div>{{-- /tab-content --}}
</div>
@endsection

@push('styles')
<style>
.mes-card {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    border:2px solid #dee2e6; border-radius:10px; padding:10px 6px;
    cursor:pointer; transition:all .15s; user-select:none; background:#fff;
}
.mes-card input[type=checkbox] { display:none; }
.mes-card .mes-nombre { font-weight:700; font-size:.88rem; color:#1a2340; }
.mes-card .mes-anio   { font-size:.72rem; color:#6c757d; }
.mes-card:hover       { border-color:#2196F3; background:#e3f2fd; }
.mes-card.selected    { border-color:#1565C0; background:#1565C0; }
.mes-card.selected .mes-nombre { color:#fff; }
.mes-card.selected .mes-anio   { color:#bbdefb; }
.mes-card.pasado      { opacity:.6; }
</style>
@endpush

@push('scripts')
<script>
// File preview
document.getElementById('inputCalExcel')?.addEventListener('change', function() {
    const n = this.files[0]?.name ?? '';
    document.getElementById('calNombreArch').textContent = n;
    document.getElementById('prevCalNombre').classList.toggle('d-none', !n);
});
document.getElementById('inputSeqExcel')?.addEventListener('change', function() {
    const n = this.files[0]?.name ?? '';
    document.getElementById('seqNombreArch').textContent = n;
    document.getElementById('prevSeqNombre').classList.toggle('d-none', !n);
});

// Loading state
document.getElementById('formCalendario')?.addEventListener('submit', function() {
    const b = document.getElementById('btnImportar');
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importando...';
    b.disabled = true;
});
document.getElementById('formSecuencia')?.addEventListener('submit', function() {
    const b = document.getElementById('btnSecuencia');
    b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    b.disabled = true;
});

// Checkbox sync for mes cards
function syncCards(selector) {
    document.querySelectorAll(selector).forEach(chk => {
        const lbl = chk.closest('label');
        const update = () => lbl.classList.toggle('selected', chk.checked);
        chk.addEventListener('change', update);
        update();
    });
}
syncCards('.chk-seq');

function toggleSeq(v) {
    document.querySelectorAll('.chk-seq').forEach(c => {
        c.checked = v;
        c.closest('label').classList.toggle('selected', v);
    });
}
function seqRestantes() {
    const mes = {{ now()->month }};
    document.querySelectorAll('.chk-seq').forEach((c, i) => {
        c.checked = (i + 1) >= mes;
        c.closest('label').classList.toggle('selected', c.checked);
    });
}
</script>
@endpush
