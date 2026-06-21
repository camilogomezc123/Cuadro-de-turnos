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
                @foreach($ucis as $uci)
                @php $cfg = $configs[$uci->id]; @endphp
                <details class="mb-3 border rounded-3 p-3" id="uci-cfg-{{ $uci->id }}">
                    <summary class="fw-semibold" style="cursor:pointer">
                        <i class="bi bi-hospital me-1 text-primary"></i>{{ $uci->nombre }}
                    </summary>
                    <form method="POST" action="{{ route('configuracion.cobertura', $uci) }}" class="mt-3">
                        @csrf @method('PUT')
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Min. médicos mañana</label>
                                <input type="number" name="min_medicos_manana" class="form-control form-control-sm"
                                       value="{{ $cfg->min_medicos_manana }}" min="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Min. médicos tarde</label>
                                <input type="number" name="min_medicos_tarde" class="form-control form-control-sm"
                                       value="{{ $cfg->min_medicos_tarde }}" min="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Min. médicos noche</label>
                                <input type="number" name="min_medicos_noche" class="form-control form-control-sm"
                                       value="{{ $cfg->min_medicos_noche }}" min="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Min. médicos finde</label>
                                <input type="number" name="min_medicos_finde" class="form-control form-control-sm"
                                       value="{{ $cfg->min_medicos_finde }}" min="0" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Horas mín. mes</label>
                                <input type="number" name="horas_minimas_mensual" class="form-control form-control-sm"
                                       value="{{ $cfg->horas_minimas_mensual }}" min="0" step="0.5" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Horas máx. mes</label>
                                <input type="number" name="horas_maximas_mensual" class="form-control form-control-sm"
                                       value="{{ $cfg->horas_maximas_mensual }}" min="0" step="0.5" required>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small">Horas máx. semana</label>
                                <input type="number" name="horas_maximas_semanales" class="form-control form-control-sm"
                                       value="{{ $cfg->horas_maximas_semanales }}" min="0" step="0.5" required>
                            </div>
                            <div class="col-6 col-md-3 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permite_mtn" id="mtn_{{ $uci->id }}"
                                           {{ $cfg->permite_mtn ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="mtn_{{ $uci->id }}">
                                        Permite <span class="turno-badge badge-MTN" style="font-size:10px">MTN</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-save me-1"></i>Guardar configuración
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
    const uciId = @json(session('success_uci_id'));
    if (uciId) {
        const el = document.getElementById('uci-cfg-' + uciId);
        if (el) {
            el.open = true;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>
@endpush
