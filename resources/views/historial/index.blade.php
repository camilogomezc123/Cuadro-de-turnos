@extends('layouts.app')

@section('title', 'Historial de Ediciones')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="fw-bold mb-0" style="color:#1a2340">
        <i class="bi bi-clock-history me-2 text-primary"></i>Historial de Ediciones
    </h4>
    <span class="badge bg-secondary">{{ $registros->total() }} registros</span>
</div>

{{-- Filtros --}}
<div class="panel mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Módulo</label>
            <select name="modulo" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach($modulos as $m)
                    <option value="{{ $m }}" @selected(request('modulo') == $m)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Usuario</label>
            <input type="text" name="usuario" class="form-control form-control-sm" value="{{ request('usuario') }}" placeholder="nombre...">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Acción</label>
            <input type="text" name="accion" class="form-control form-control-sm" value="{{ request('accion') }}" placeholder="ej. APROBAR_CAMBIO">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Desde</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Hasta</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta') }}">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                <i class="bi bi-search me-1"></i>Filtrar
            </button>
            <a href="{{ route('historial.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x"></i>
            </a>
        </div>
    </form>
</div>

{{-- Tabla --}}
<div class="panel">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:140px">Fecha/Hora</th>
                    <th style="width:130px">Módulo</th>
                    <th>Acción</th>
                    <th>Entidad</th>
                    <th>Usuario</th>
                    <th>Descripción</th>
                    <th class="text-center" style="width:70px">Datos</th>
                </tr>
            </thead>
            <tbody>
                @forelse($registros as $r)
                <tr>
                    <td class="text-muted small text-nowrap">
                        {{ $r->created_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td>
                        <span class="badge bg-secondary bg-opacity-25 text-dark small">{{ $r->modulo ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold small text-primary">{{ $r->accion }}</span>
                    </td>
                    <td class="text-muted small">
                        @if($r->entidad)
                            {{ $r->entidad }}
                            @if($r->entidad_id) #{{ $r->entidad_id }} @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="small">{{ $r->usuario ?? '—' }}</td>
                    <td class="small text-muted">{{ Str::limit($r->descripcion, 80) }}</td>
                    <td class="text-center">
                        @if($r->datos_anteriores || $r->datos_nuevos)
                        <button class="btn btn-outline-secondary btn-sm py-0 px-1"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#modalDatos"
                                data-anterior="{{ json_encode($r->datos_anteriores, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}"
                                data-nuevo="{{ json_encode($r->datos_nuevos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}">
                            <i class="bi bi-braces"></i>
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Sin registros con los filtros aplicados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($registros->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $registros->links() }}
    </div>
    @endif
</div>

{{-- Modal datos JSON --}}
<div class="modal fade" id="modalDatos" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Datos del registro</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="small fw-semibold text-muted mb-1">Datos anteriores</h6>
                        <pre id="preAnterior" class="bg-light p-3 rounded small" style="max-height:300px;overflow:auto;white-space:pre-wrap;word-break:break-all">—</pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="small fw-semibold text-muted mb-1">Datos nuevos</h6>
                        <pre id="preNuevo" class="bg-light p-3 rounded small" style="max-height:300px;overflow:auto;white-space:pre-wrap;word-break:break-all">—</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('modalDatos').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('preAnterior').textContent = btn.dataset.anterior || '—';
    document.getElementById('preNuevo').textContent    = btn.dataset.nuevo    || '—';
});
</script>
@endpush

@endsection