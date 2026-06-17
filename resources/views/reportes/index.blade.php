@extends('layouts.app')

@section('title', 'Reportes')
@section('page-title', 'Exportar Reportes')
@section('breadcrumb')
    <li class="breadcrumb-item active">Reportes</li>
@endsection

@section('content')
<div class="row g-4">
    {{-- Reporte Consolidado --}}
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-header" style="background:linear-gradient(135deg,#1565C0,#1E88E5);border-radius:13px 13px 0 0">
                <span class="panel-title text-white">
                    <i class="bi bi-file-earmark-bar-graph-fill me-2"></i>Reporte Consolidado General
                </span>
            </div>
            <div class="panel-body">
                <p class="text-muted small mb-4">
                    Exporta un reporte completo con todos los médicos y UCIs del período seleccionado.
                    Incluye indicadores por médico, por UCI y resumen ejecutivo.
                </p>

                <form method="POST" action="{{ route('reportes.excel') }}" class="mb-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Período</label>
                        <select name="archivo_id" class="form-select" required>
                            @forelse($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }} ({{ $a->total_medicos }} médicos)
                                </option>
                            @empty
                                <option disabled>Sin períodos disponibles</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-fill" {{ $archivos->isEmpty() ? 'disabled' : '' }}>
                            <i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('reportes.pdf') }}">
                    @csrf
                    <div class="mb-3">
                        <select name="archivo_id" class="form-select" required>
                            @forelse($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @empty
                                <option disabled>Sin períodos disponibles</option>
                            @endforelse
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" {{ $archivos->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-file-pdf me-2"></i>Exportar PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Reporte por médico --}}
    <div class="col-md-6">
        <div class="panel">
            <div class="panel-header" style="background:linear-gradient(135deg,#1B5E20,#388E3C);border-radius:13px 13px 0 0">
                <span class="panel-title text-white">
                    <i class="bi bi-person-vcard-fill me-2"></i>Reporte Individual por Médico
                </span>
            </div>
            <div class="panel-body">
                <p class="text-muted small mb-4">
                    Exporta el reporte detallado de un médico específico: turnos, horas y todos sus indicadores del período.
                </p>

                <form method="POST" action="{{ route('reportes.medico.excel') }}" class="mb-3">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Período</label>
                        <select name="archivo_id" class="form-select mb-2" id="periodoMedico" required>
                            @forelse($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @empty
                                <option disabled>Sin períodos</option>
                            @endforelse
                        </select>
                        <label class="form-label fw-semibold small">UCI</label>
                        <select name="uci_id" class="form-select mb-2" id="uciSelectMedico">
                            <option value="">Todas las UCIs</option>
                            @foreach($ucis as $u)
                                <option value="{{ $u->id }}">{{ $u->nombre }}</option>
                            @endforeach
                        </select>
                        <label class="form-label fw-semibold small">Médico</label>
                        <select name="medico_id" class="form-select" id="medicoSelect" required>
                            <option value="">Seleccione un médico...</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-fill" {{ $archivos->isEmpty() ? 'disabled' : '' }}>
                            <i class="bi bi-file-earmark-excel me-2"></i>Excel
                        </button>
                    </div>
                </form>

                <form method="POST" action="{{ route('reportes.medico.pdf') }}">
                    @csrf
                    <div class="mb-2">
                        <select name="archivo_id" class="form-select mb-2" id="periodoMedico2" required>
                            @forelse($archivos as $a)
                                <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                                    {{ $a->nombre_mes }} {{ $a->anio }}
                                </option>
                            @empty <option disabled>Sin períodos</option>
                            @endforelse
                        </select>
                        <select name="medico_id" class="form-select" id="medicoSelect2" required>
                            <option value="">Seleccione un médico...</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" {{ $archivos->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-file-pdf me-2"></i>PDF
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@if($archivos->isEmpty())
<div class="alert alert-warning mt-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    No hay archivos procesados. <a href="{{ route('archivos.index') }}">Cargue un archivo Excel</a> primero.
</div>
@endif
@endsection

@push('scripts')
<script>
// Cargar médicos dinámicamente según el período seleccionado
const archivoId = {{ $archivoId ?? 'null' }};

async function cargarMedicos(archivoId, selectId) {
    const select = document.getElementById(selectId);
    if (!archivoId) return;
    try {
        const resp = await fetch(`/api/medicos?archivo_id=${archivoId}`);
        const data = await resp.json();
        select.innerHTML = '<option value="">Seleccione un médico...</option>';
        data.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.nombre + ' (' + m.uci + ')';
            select.appendChild(opt);
        });
    } catch(e) {}
}

document.getElementById('periodoMedico')?.addEventListener('change', function() {
    cargarMedicos(this.value, 'medicoSelect');
});
document.getElementById('periodoMedico2')?.addEventListener('change', function() {
    cargarMedicos(this.value, 'medicoSelect2');
});

// Carga inicial
if (archivoId) {
    cargarMedicos(archivoId, 'medicoSelect');
    cargarMedicos(archivoId, 'medicoSelect2');
}
</script>
@endpush
