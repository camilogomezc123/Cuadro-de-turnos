@extends('layouts.app')

@section('title', 'Cargar Excel')
@section('page-title', 'Cargar Archivo Excel')
@section('breadcrumb')
    <li class="breadcrumb-item active">Cargar Excel</li>
@endsection

@section('content')
<div class="row g-4">

    {{-- FORMULARIO DE CARGA --}}
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-cloud-upload me-2 text-primary"></i>Nuevo Archivo de Turnos</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('archivos.upload') }}" enctype="multipart/form-data" id="uploadForm">
                    @csrf

                    {{-- Zona de arrastre --}}
                    <div id="dropZone" class="border-2 border-dashed rounded-3 p-4 text-center mb-4"
                         style="border: 2px dashed #CBD5E0; cursor:pointer; transition: all .2s"
                         onclick="document.getElementById('archivoInput').click()">
                        <i class="bi bi-file-earmark-excel text-success" style="font-size:3rem"></i>
                        <div class="mt-2 fw-semibold text-secondary">Haga clic o arrastre el archivo aquí</div>
                        <div class="text-muted small mt-1">Formatos: .xlsx, .xls · Máximo: 20 MB</div>
                        <div id="fileName" class="mt-2 text-primary fw-semibold d-none"></div>
                    </div>
                    <input type="file" id="archivoInput" name="archivo" accept=".xlsx,.xls" class="d-none" required>

                    {{-- Formato nuevo: mes/año opcionales (se extraen del nombre de hoja) --}}
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Formato automático:</strong> Si el archivo tiene hojas como <em>"Mayo 2026"</em>,
                        el mes y año se detectan automáticamente. Deje los selectores en blanco para importar todos los meses.
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Mes (opcional)</label>
                            <select name="mes" class="form-select">
                                <option value="">— Todos los meses —</option>
                                @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $mn)
                                    <option value="{{ $i+1 }}">{{ $mn }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Año (opcional)</label>
                            <select name="anio" class="form-select">
                                <option value="">— Auto —</option>
                                @foreach(range(now()->year - 1, now()->year + 2) as $a)
                                    <option value="{{ $a }}" {{ $a == now()->year ? 'selected':'' }}>{{ $a }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="sobreescribir" id="sobreescribir">
                        <label class="form-check-label small text-muted" for="sobreescribir">
                            Sobreescribir si ya existe el período seleccionado
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="btnUpload">
                        <i class="bi bi-cloud-upload me-2"></i>Procesar Archivo
                    </button>
                </form>

                {{-- Loading --}}
                <div id="loadingMsg" class="text-center d-none mt-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-secondary small">Procesando archivo Excel, por favor espere...</div>
                </div>

                {{-- Guía de estructura --}}
                <div class="mt-4 p-3 rounded-3" style="background:#f8faff;border:1px solid #e3edff">
                    <div class="fw-semibold small text-primary mb-2">
                        <i class="bi bi-info-circle me-1"></i>Formato soportado — Clínica de Occidente
                    </div>
                    <div class="small text-muted">
                        <strong>Hojas:</strong> Una por mes (ej: <em>Mayo 2026</em>). Dentro de cada hoja, bloques verticales por UCI.<br>
                        <strong>Bloque UCI:</strong> Fila con nombre UCI → letras día → números día → médicos con turnos.<br>
                        <strong>UCIs detectadas:</strong> UCI TORRE C, UCI CARDIOVASCULAR, UCI GENERAL, UCI NEUROVASCULAR, UCI QUIRÚRGICA, UCI TORRE B1, UCI TORRE B2, UCIN - RESPIRATORIA.<br><br>
                        <table class="table table-sm table-bordered small mb-0">
                            <thead class="table-light">
                                <tr><th>Código</th><th>Turno</th><th>Horas</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><span class="badge-M turno-badge">M</span></td><td>Mañana</td><td>7am–1pm (6h)</td></tr>
                                <tr><td><span class="badge-T turno-badge">T</span></td><td>Tarde</td><td>1pm–7pm (6h)</td></tr>
                                <tr><td><span class="badge-MT turno-badge">MT</span></td><td>Mañana-Tarde</td><td>7am–7pm (12h)</td></tr>
                                <tr><td><span class="badge-N turno-badge">N</span></td><td>Noche</td><td>7pm–7am (12h)</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- HISTORIAL --}}
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-clock-history me-2 text-secondary"></i>Historial de Archivos Cargados</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $archivos->count() }} archivos</span>
            </div>
            <div class="panel-body p-0">
                @if($archivos->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
                        No hay archivos cargados aún.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Período</th>
                                    <th>Archivo</th>
                                    <th>Médicos</th>
                                    <th>Turnos</th>
                                    <th>Estado</th>
                                    <th>Cargado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($archivos as $a)
                                <tr>
                                    <td>
                                        <strong>{{ $a->nombre_mes }} {{ $a->anio }}</strong>
                                    </td>
                                    <td>
                                        <span class="text-muted small" title="{{ $a->nombre_archivo }}">
                                            {{ Str::limit($a->nombre_archivo, 25) }}
                                        </span>
                                    </td>
                                    <td><span class="badge bg-primary-subtle text-primary">{{ $a->total_medicos }}</span></td>
                                    <td><span class="badge bg-success-subtle text-success">{{ $a->total_turnos }}</span></td>
                                    <td>
                                        @if($a->procesado)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-check-circle me-1"></i>Procesado
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('archivos.destroy', $a) }}"
                                              onsubmit="return confirm('¿Eliminar este período y todos sus datos?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @if(!empty($a->errores))
                                <tr>
                                    <td colspan="7">
                                        <div class="alert alert-danger alert-sm mb-0 py-2 small">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            {{ implode(' | ', $a->errores) }}
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @if(!empty($a->advertencias))
                                <tr>
                                    <td colspan="7">
                                        <div class="alert alert-warning alert-sm mb-0 py-2 small">
                                            <i class="bi bi-info-circle me-1"></i>
                                            @php $numAdv = count($a->advertencias); @endphp
                                            {{ $numAdv }} advertencia(s): {{ implode(' | ', array_slice($a->advertencias, 0, 2)) }}
                                            {{ $numAdv > 2 ? '... y ' . ($numAdv - 2) . ' más' : '' }}
                                        </div>
                                    </td>
                                </tr>
                                @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Drag & drop
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('archivoInput');
const fileNameDiv = document.getElementById('fileName');
const form = document.getElementById('uploadForm');
const btn = document.getElementById('btnUpload');
const loading = document.getElementById('loadingMsg');

fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) {
        fileNameDiv.textContent = '📎 ' + fileInput.files[0].name;
        fileNameDiv.classList.remove('d-none');
        dropZone.style.borderColor = '#2196F3';
        dropZone.style.background = '#f0f8ff';
    }
});

dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.style.borderColor = '#2196F3';
    dropZone.style.background = '#f0f8ff';
});
dropZone.addEventListener('dragleave', () => {
    dropZone.style.borderColor = '#CBD5E0';
    dropZone.style.background = '';
});
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        fileNameDiv.textContent = '📎 ' + file.name;
        fileNameDiv.classList.remove('d-none');
        dropZone.style.borderColor = '#2196F3';
    }
});

form.addEventListener('submit', () => {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    loading.classList.remove('d-none');
});
</script>
@endpush
