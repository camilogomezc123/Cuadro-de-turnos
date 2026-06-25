@extends('layouts.app')
@section('title', 'Ausencias')
@section('page-title', 'Ausencias y Vacaciones')
@section('breadcrumb')
    <li class="breadcrumb-item active">Ausencias</li>
@endsection
@section('content')

{{-- Nueva ausencia --}}
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Registrar Ausencia</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('ausencias.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Médico</label>
                        <select name="medico_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar —</option>
                            @foreach($medicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }} ({{ $m->uci->codigo ?? '?' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo</label>
                        <select name="tipo" class="form-select form-select-sm" required>
                            <option value="vacaciones">Vacaciones</option>
                            <option value="permiso">Permiso</option>
                            <option value="incapacidad">Incapacidad</option>
                            <option value="licencia">Licencia</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fecha inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Fecha fin</label>
                            <input type="date" name="fecha_fin" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Descripción / Documento</label>
                        <input type="text" name="descripcion" class="form-control form-control-sm"
                               placeholder="Resolución, número de incapacidad, etc.">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus me-1"></i>Registrar ausencia
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listado --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-calendar-x me-2"></i>Ausencias Registradas</span>
                <span class="badge bg-secondary-subtle text-secondary">{{ $ausencias->total() }}</span>
            </div>
            {{-- Filtros --}}
            <div class="panel-body border-bottom py-2">
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <select name="uci_id" class="form-select form-select-sm" style="max-width:180px" onchange="this.form.submit()">
                        <option value="0">Todas las UCIs</option>
                        @foreach($ucis as $u)<option value="{{ $u->id }}" {{ $u->id == $uciId ? 'selected' : '' }}>{{ $u->codigo }}</option>@endforeach
                    </select>
                    <select name="estado" class="form-select form-select-sm" style="max-width:150px" onchange="this.form.submit()">
                        <option value="">Todo estado</option>
                        <option value="pendiente" {{ $estado=='pendiente' ? 'selected' : '' }}>Pendiente</option>
                        <option value="aprobada" {{ $estado=='aprobada' ? 'selected' : '' }}>Aprobada</option>
                        <option value="rechazada" {{ $estado=='rechazada' ? 'selected' : '' }}>Rechazada</option>
                    </select>
                    <select name="tipo" class="form-select form-select-sm" style="max-width:160px" onchange="this.form.submit()">
                        <option value="">Todo tipo</option>
                        <option value="vacaciones" {{ $tipo=='vacaciones' ? 'selected' : '' }}>Vacaciones</option>
                        <option value="permiso" {{ $tipo=='permiso' ? 'selected' : '' }}>Permiso</option>
                        <option value="incapacidad" {{ $tipo=='incapacidad' ? 'selected' : '' }}>Incapacidad</option>
                        <option value="licencia" {{ $tipo=='licencia' ? 'selected' : '' }}>Licencia</option>
                    </select>
                </form>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr><th>Médico</th><th>UCI</th><th>Tipo</th><th>Fechas</th><th>Días</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($ausencias as $a)
                        <tr>
                            <td class="fw-semibold small">{{ $a->medico->nombre }}</td>
                            <td><span class="badge bg-primary-subtle text-primary">{{ $a->medico->uci->codigo ?? '?' }}</span></td>
                            <td><span class="badge bg-info-subtle text-info">{{ $a->nombre_tipo }}</span></td>
                            <td class="small">{{ $a->fecha_inicio->format('d/m/Y') }} – {{ $a->fecha_fin->format('d/m/Y') }}</td>
                            <td><span class="badge bg-secondary-subtle text-secondary">{{ $a->dias }}d</span></td>
                            <td>
                                <span class="badge bg-{{ match($a->estado) { 'aprobada'=>'success', 'rechazada'=>'danger', default=>'warning' } }}-subtle
                                      text-{{ match($a->estado) { 'aprobada'=>'success', 'rechazada'=>'danger', default=>'warning' } }}">
                                    {{ ucfirst($a->estado) }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($a->estado === 'pendiente')
                                        <form method="POST" action="{{ route('ausencias.aprobar', $a) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-success btn-sm" title="Aprobar"><i class="bi bi-check2"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('ausencias.rechazar', $a) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-danger btn-sm" title="Rechazar"><i class="bi bi-x"></i></button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('ausencias.destroy', $a) }}"
                                          onsubmit="return confirm('¿Eliminar esta ausencia?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay ausencias registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div class="px-4 py-3">{{ $ausencias->links() }}</div>
            </div>
        </div>
    </div>
</div>

@endsection
