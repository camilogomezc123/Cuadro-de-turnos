@extends('layouts.app')
@section('title','Cargar Secuencia desde Excel')
@section('page-title','Cargar Secuencia UCI desde Excel')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('secuencias.index') }}">Secuencias</a></li>
    <li class="breadcrumb-item active">Cargar Excel</li>
@endsection

@section('content')
<div class="fade-in">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
</div>
@endif

<div class="row g-4">

{{-- ── PASO 1: Cargar Excel ── --}}
<div class="col-lg-5">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-upload me-2 text-primary"></i>Paso 1 — Subir archivo Excel</span>
        </div>
        <div class="panel-body">

            {{-- Formato esperado --}}
            <div class="alert alert-info small mb-3">
                <strong><i class="bi bi-info-circle me-1"></i>Formato esperado del Excel:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li><strong>Columna A:</strong> Nombre completo del médico (ej: <em>Diego Escobar</em>)</li>
                    <li><strong>Fila 2:</strong> Letras de días <code>L M M J V S D</code> repitiendo por semana</li>
                    <li><strong>Resto:</strong> Códigos de turno: M, T, MT, N, MTN, MN, PER, INC</li>
                    <li>Fines de semana rotativos se detectan automáticamente</li>
                    <li>Los días hábiles (Lun–Vie) se toman como patrón fijo</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('secuencias.parsear-excel') }}" enctype="multipart/form-data" id="formCarga">
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
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre de la secuencia <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control"
                           placeholder="Ej: Secuencia Torre C 2026"
                           value="{{ old('nombre') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Año base <span class="text-danger">*</span></label>
                    <select name="anio" class="form-select" required>
                        @foreach($anios as $y)
                            <option value="{{ $y }}" @selected(old('anio', now()->year)==$y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Archivo Excel <span class="text-danger">*</span></label>
                    <input type="file" name="excel" class="form-control" accept=".xlsx,.xls,.csv" required id="inputExcel">
                    <div class="form-text">Formatos: .xlsx, .xls</div>
                </div>

                {{-- Preview del archivo seleccionado --}}
                <div id="previewNombre" class="d-none mb-3">
                    <span class="badge bg-primary-subtle text-primary rounded-pill">
                        <i class="bi bi-file-earmark-excel me-1"></i>
                        <span id="nombreArchivo"></span>
                    </span>
                </div>

                <button type="submit" class="btn btn-primary w-100" id="btnCargar">
                    <i class="bi bi-gear me-2"></i>Procesar y guardar secuencia
                </button>
            </form>
        </div>
    </div>
</div>

{{-- ── PASO 2: Aplicar a meses ── --}}
<div class="col-lg-7">
    <div class="panel h-100">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-calendar-range me-2 text-success"></i>Paso 2 — Programar meses</span>
        </div>
        <div class="panel-body">

            @if($preview)
            {{-- Secuencia recién cargada --}}
            <div class="alert alert-success mb-3">
                <div class="fw-bold"><i class="bi bi-check-circle me-2"></i>Secuencia guardada</div>
                <div class="small mt-1">
                    <strong>{{ $preview['nombre'] }}</strong> · {{ $preview['uci'] }} · {{ $preview['anio'] }}<br>
                    {{ count($preview['doctores']) }} médicos detectados:
                    <span class="text-muted">{{ implode(', ', $preview['doctores']) }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('secuencias.aplicar-meses', $preview['id']) }}">
                @csrf
                <p class="fw-bold mb-2">Selecciona los meses a generar:</p>

                {{-- Acciones rápidas --}}
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleTodos(true)">
                        <i class="bi bi-check-all me-1"></i>Todo {{ $preview['anio'] }}
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleTodos(false)">
                        <i class="bi bi-x me-1"></i>Ninguno
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="seleccionarRestantes()">
                        <i class="bi bi-calendar-check me-1"></i>Restantes del año
                    </button>
                </div>

                {{-- Grid de meses --}}
                <div class="row g-2 mb-4" id="gridMeses">
                    @php
                        $mesActual = now()->month;
                        $anioBase  = $preview['anio'];
                    @endphp
                    @foreach($meses as $i => $m)
                    @php $mesNum = $i + 1; @endphp
                    <div class="col-6 col-md-4 col-xl-3">
                        <label class="mes-card {{ $mesNum >= $mesActual ? 'activo' : '' }}"
                               for="mes{{ $mesNum }}">
                            <input type="checkbox" name="meses_anio[]"
                                   value="{{ $mesNum }}-{{ $anioBase }}"
                                   id="mes{{ $mesNum }}"
                                   class="chk-mes"
                                   @checked($mesNum >= $mesActual)>
                            <div class="mes-nombre">{{ $m }}</div>
                            <div class="mes-anio">{{ $anioBase }}</div>
                        </label>
                    </div>
                    @endforeach
                </div>

                {{-- También aplicar a otro año --}}
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="chkOtroAnio" onchange="toggleOtroAnio()">
                        <label class="form-check-label" for="chkOtroAnio">
                            También programar meses del año siguiente
                        </label>
                    </div>
                    <div id="secOtroAnio" class="d-none mt-2">
                        <div class="row g-2" id="gridMesesSig">
                            @foreach($meses as $i => $m)
                            @php $mesNum = $i + 1; @endphp
                            <div class="col-6 col-md-4 col-xl-3">
                                <label class="mes-card" for="mesS{{ $mesNum }}">
                                    <input type="checkbox" name="meses_anio[]"
                                           value="{{ $mesNum }}-{{ $anioBase + 1 }}"
                                           id="mesS{{ $mesNum }}"
                                           class="chk-mes-sig">
                                    <div class="mes-nombre">{{ $m }}</div>
                                    <div class="mes-anio">{{ $anioBase + 1 }}</div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-calendar-plus me-2"></i>Generar turnos para los meses seleccionados
                </button>
            </form>

            @else
            {{-- Estado inicial: sin secuencia cargada --}}
            <div class="text-center py-5 text-muted">
                <i class="bi bi-arrow-left-circle fs-1 d-block mb-3 text-primary"></i>
                <p>Sube el Excel con la secuencia de la UCI (Paso 1).<br>
                Una vez procesado, aquí podrás seleccionar en qué meses aplicarla.</p>
                <hr class="my-4">
                <p class="small"><strong>¿Ya tienes una secuencia guardada?</strong></p>
                <a href="{{ route('secuencias.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list me-1"></i>Ver secuencias existentes
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

</div>{{-- /row --}}
</div>
@endsection

@push('styles')
<style>
.mes-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 10px 6px;
    cursor: pointer;
    transition: all .15s;
    user-select: none;
    background: #fff;
}
.mes-card input[type=checkbox] { display: none; }
.mes-card .mes-nombre { font-weight: 700; font-size: .88rem; color: #1a2340; }
.mes-card .mes-anio   { font-size: .72rem; color: #6c757d; }
.mes-card:hover       { border-color: #2196F3; background: #e3f2fd; }
.mes-card.selected    { border-color: #1565C0; background: #1565C0; }
.mes-card.selected .mes-nombre { color: #fff; }
.mes-card.selected .mes-anio   { color: #bbdefb; }
.mes-card.pasado      { opacity: .5; }
</style>
@endpush

@push('scripts')
<script>
// File name preview
document.getElementById('inputExcel')?.addEventListener('change', function() {
    const n = this.files[0]?.name ?? '';
    document.getElementById('nombreArchivo').textContent = n;
    document.getElementById('previewNombre').classList.toggle('d-none', !n);
});

// Sync checkbox ↔ card style
document.querySelectorAll('.chk-mes, .chk-mes-sig').forEach(chk => {
    const label = chk.closest('label');
    const update = () => label.classList.toggle('selected', chk.checked);
    chk.addEventListener('change', update);
    update();
});

function toggleTodos(val) {
    document.querySelectorAll('.chk-mes').forEach(c => {
        c.checked = val;
        c.closest('label').classList.toggle('selected', val);
    });
}

function seleccionarRestantes() {
    const mesActual = {{ now()->month }};
    document.querySelectorAll('.chk-mes').forEach((c, i) => {
        const mes = i + 1;
        c.checked = mes >= mesActual;
        c.closest('label').classList.toggle('selected', c.checked);
    });
}

function toggleOtroAnio() {
    const show = document.getElementById('chkOtroAnio').checked;
    document.getElementById('secOtroAnio').classList.toggle('d-none', !show);
}

// Loading state on submit
document.getElementById('formCarga')?.addEventListener('submit', function() {
    const btn = document.getElementById('btnCargar');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    btn.disabled = true;
});
</script>
@endpush
