@extends('layouts.app')

@section('title', 'Alertas')
@section('page-title', 'Alertas del Sistema')
@section('breadcrumb')
    <li class="breadcrumb-item active">Alertas</li>
@endsection

@section('content')

{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-2 fw-bold text-danger">{{ $totalAbiertas }}</div>
            <div class="text-muted small">Alertas abiertas</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-2 fw-bold text-warning">{{ $totalAltas }}</div>
            <div class="text-muted small">Prioridad alta</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel text-center py-3">
            <div class="fs-2 fw-bold text-primary">{{ $alertas->total() }}</div>
            <div class="text-muted small">Total filtradas</div>
        </div>
    </div>
    <div class="col-sm-6 col-md-3">
        <div class="panel text-center py-3">
            @if($archivoId)
                <form method="POST" action="{{ route('alertas.ejecutar', $archivoId) }}">
                    @csrf
                    <button class="btn btn-warning btn-sm">
                        <i class="bi bi-lightning me-1"></i>Re-validar período
                    </button>
                </form>
            @else
                <div class="text-muted small">Seleccione un período</div>
            @endif
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Período</label>
                <select name="archivo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($archivos as $a)
                        <option value="{{ $a->id }}" {{ $a->id == $archivoId ? 'selected' : '' }}>
                            {{ $a->nombre_mes }} {{ $a->anio }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">UCI</label>
                <select name="uci_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">Todas</option>
                    @foreach($ucis as $u)
                        <option value="{{ $u->id }}" {{ $u->id == $uciId ? 'selected' : '' }}>{{ $u->codigo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Prioridad</label>
                <select name="prioridad" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <option value="alta" {{ $prioridad == 'alta' ? 'selected' : '' }}>Alta</option>
                    <option value="media" {{ $prioridad == 'media' ? 'selected' : '' }}>Media</option>
                    <option value="baja" {{ $prioridad == 'baja' ? 'selected' : '' }}>Baja</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="abierta" {{ $estado == 'abierta' ? 'selected' : '' }}>Abierta</option>
                    <option value="en_revision" {{ $estado == 'en_revision' ? 'selected' : '' }}>En revisión</option>
                    <option value="cerrada" {{ $estado == 'cerrada' ? 'selected' : '' }}>Cerrada</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos los tipos</option>
                    @foreach($tipos as $clave => $nombre)
                        <option value="{{ $clave }}" {{ $tipo == $clave ? 'selected' : '' }}>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <a href="{{ route('alertas.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Tabla de alertas --}}
<div class="panel">
    <div class="panel-body p-0">
        @if($alertas->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-check-circle fs-1 d-block mb-2 text-success opacity-50"></i>
                No hay alertas con los filtros seleccionados.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Prior.</th>
                            <th>Tipo</th>
                            <th>Mensaje</th>
                            <th>Médico</th>
                            <th>UCI</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alertas as $alerta)
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $alerta->badge_prioridad }}-subtle text-{{ $alerta->badge_prioridad }}">
                                        {{ ucfirst($alerta->prioridad) }}
                                    </span>
                                </td>
                                <td><span class="small text-muted">{{ $alerta->tipo }}</span></td>
                                <td style="max-width:300px;white-space:normal;font-size:12px">{{ $alerta->mensaje }}</td>
                                <td class="small">{{ $alerta->medico?->nombre ?? '—' }}</td>
                                <td class="small">{{ $alerta->uci?->codigo ?? '—' }}</td>
                                <td class="small">{{ $alerta->fecha?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ match($alerta->estado) {
                                        'abierta' => 'danger', 'en_revision' => 'warning', 'cerrada' => 'success', default => 'secondary' } }}-subtle
                                          text-{{ match($alerta->estado) {
                                        'abierta' => 'danger', 'en_revision' => 'warning', 'cerrada' => 'success', default => 'secondary' } }}">
                                        {{ ucfirst(str_replace('_',' ',$alerta->estado)) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        @if($alerta->estado === 'abierta')
                                            <form method="POST" action="{{ route('alertas.estado', $alerta) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="estado" value="en_revision">
                                                <button class="btn btn-outline-warning btn-sm" title="Marcar en revisión">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if($alerta->estado !== 'cerrada')
                                            <form method="POST" action="{{ route('alertas.estado', $alerta) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="estado" value="cerrada">
                                                <button class="btn btn-outline-success btn-sm" title="Cerrar alerta">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('alertas.destroy', $alerta) }}"
                                              onsubmit="return confirm('¿Eliminar esta alerta?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">
                {{ $alertas->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
