@extends('layouts.app')
@section('title', 'Configuración')
@section('page-title', 'Configuración del Sistema')
@section('breadcrumb')
    <li class="breadcrumb-item active">Configuración</li>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i><strong>Error al guardar:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">
    {{-- Tipos de turno --}}
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-clock me-2 text-primary"></i>Tipos de Turno</span>
            </div>
            <div class="panel-body p-0">
                <table class="table table-custom mb-0">
                    <thead><tr><th>Código</th><th>Nombre</th><th>H. Diurnas</th><th>H. Nocturnas</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($tiposTurno as $t)
                        <tr>
                            <td><span class="turno-badge badge-{{ $t->codigo }}">{{ $t->codigo }}</span></td>
                            <td class="small">{{ $t->nombre }}</td>
                            <td class="small text-center">{{ $t->horas_diurnas }}h</td>
                            <td class="small text-center">{{ $t->horas_nocturnas }}h</td>
                            <td class="small text-center fw-bold">{{ $t->horas_total }}h</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Configuración de cobertura por UCI --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-sliders me-2 text-primary"></i>Configuración de Cobertura por UCI</span>
            </div>
            <div class="panel-body">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Haga clic en el nombre de la UCI para expandir su configuración y editar los valores.
                </p>
                @foreach($ucis as $uci)
                @php $cfg = $configs[$uci->id]; @endphp
                <details class="mb-3 border rounded-3 p-3" id="uci-cfg-{{ $uci->id }}">
                    <summary class="fw-semibold d-flex align-items-center gap-2" style="cursor:pointer; list-style:none">
                        <i class="bi bi-chevron-right cfg-chevron" style="transition:transform .2s;font-size:12px"></i>
                        <i class="bi bi-hospital text-primary"></i>{{ $uci->nombre }}
                        <span class="ms-auto text-muted fw-normal small">
                            {{ $cfg->horas_minimas_mensual }}h – {{ $cfg->horas_maximas_mensual }}h/mes
                        </span>
                    </summary>
                    <form method="POST" action="{{ route('configuracion.cobertura', $uci) }}" class="mt-3" novalidate>
                        @csrf @method('PUT')
                        <input type="hidden" name="_uci_editando" value="{{ $uci->id }}">
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">Min. médicos mañana</label>
                                <input type="number" name="min_medicos_manana" class="form-control form-control-sm"
                                       value="{{ old('min_medicos_manana', $cfg->min_medicos_manana) }}"
                                       min="0" step="1" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">Min. médicos tarde</label>
                                <input type="number" name="min_medicos_tarde" class="form-control form-control-sm"
                                       value="{{ old('min_medicos_tarde', $cfg->min_medicos_tarde) }}"
                                       min="0" step="1" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">Min. médicos noche</label>
                                <input type="number" name="min_medicos_noche" class="form-control form-control-sm"
                                       value="{{ old('min_medicos_noche', $cfg->min_medicos_noche) }}"
                                       min="0" step="1" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small fw-semibold">Min. médicos fin de semana</label>
                                <input type="number" name="min_medicos_finde" class="form-control form-control-sm"
                                       value="{{ old('min_medicos_finde', $cfg->min_medicos_finde) }}"
                                       min="0" step="1" required>
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold">
                                    <i class="bi bi-arrow-down-circle text-success me-1"></i>Horas mínimas/mes
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="horas_minimas_mensual" class="form-control"
                                           value="{{ old('horas_minimas_mensual', $cfg->horas_minimas_mensual) }}"
                                           min="0" step="any" required>
                                    <span class="input-group-text">h</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold">
                                    <i class="bi bi-arrow-up-circle text-danger me-1"></i>Horas máximas/mes
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="horas_maximas_mensual" class="form-control"
                                           value="{{ old('horas_maximas_mensual', $cfg->horas_maximas_mensual) }}"
                                           min="0" step="any" required>
                                    <span class="input-group-text">h</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small fw-semibold">
                                    <i class="bi bi-calendar-week text-warning me-1"></i>Horas máximas/semana
                                </label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="horas_maximas_semanales" class="form-control"
                                           value="{{ old('horas_maximas_semanales', $cfg->horas_maximas_semanales) }}"
                                           min="0" step="any" required>
                                    <span class="input-group-text">h</span>
                                </div>
                            </div>

                            <div class="col-12 d-flex align-items-center justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permite_mtn" id="mtn_{{ $uci->id }}"
                                           {{ old('permite_mtn', $cfg->permite_mtn) ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="mtn_{{ $uci->id }}">
                                        Permite turno <span class="turno-badge badge-MTN" style="font-size:10px">MTN</span> (24h)
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm px-3">
                                    <i class="bi bi-save me-1"></i>Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </details>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Rotar flecha al abrir/cerrar
    document.querySelectorAll('details').forEach(d => {
        d.addEventListener('toggle', function() {
            const ch = this.querySelector('.cfg-chevron');
            if (ch) ch.style.transform = this.open ? 'rotate(90deg)' : '';
        });
    });

    // Abrir panel tras guardado exitoso
    const uciIdSuccess = @json(session('success_uci_id'));
    if (uciIdSuccess) {
        const el = document.getElementById('uci-cfg-' + uciIdSuccess);
        if (el) { el.open = true; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }

    // Abrir panel que tuvo error
    const uciIdError = @json(session('_uci_editando'));
    if (uciIdError && !uciIdSuccess) {
        const el = document.getElementById('uci-cfg-' + uciIdError);
        if (el) { el.open = true; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }

    // Si hay errores de validación, abrir el panel del campo con error
    @if($errors->any())
    const uciOld = {{ old('_uci_editando', 0) }};
    if (uciOld) {
        const el = document.getElementById('uci-cfg-' + uciOld);
        if (el) { el.open = true; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    }
    @endif
});
</script>
@endpush