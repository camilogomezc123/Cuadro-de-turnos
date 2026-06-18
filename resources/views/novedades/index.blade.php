@extends('layouts.app')
@section('title','Novedades')
@section('page-title','Novedades')
@section('breadcrumb')
    <li class="breadcrumb-item active">Novedades</li>
@endsection

@section('content')
<div class="fade-in">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <h5 class="mb-0 fw-bold" style="color:#1a2340"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Novedades del mes</h5>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovedad">
            <i class="bi bi-plus-circle me-1"></i>Nueva novedad
        </button>
    </div>
</div>

{{-- Filtros --}}
<div class="panel mb-4">
    <div class="panel-body">
        <form class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Mes</label>
                <select name="mes" class="form-select form-select-sm">
                    @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i=>$m)
                        <option value="{{ $i+1 }}" @selected($mes==$i+1)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Año</label>
                <input type="number" name="anio" value="{{ $anio }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($tipos as $k=>$v)<option value="{{ $k }}" @selected(request('tipo')===$k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100">Filtrar</button>
            </div>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="panel">
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Médico</th><th>UCI</th><th>Tipo</th>
                        <th>Descripción</th><th>Horas</th><th>Resta</th>
                        <th>Visible médico</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($novedades as $n)
                    <tr>
                        <td>{{ $n->fecha->format('d/m/Y') }}</td>
                        <td>{{ $n->medico?->nombre_completo ?? '—' }}</td>
                        <td><small>{{ $n->uci?->codigo ?? '—' }}</small></td>
                        <td><small class="badge bg-secondary-subtle text-secondary">{{ $n->label_tipo }}</small></td>
                        <td><small>{{ Str::limit($n->descripcion ?? '—', 60) }}</small></td>
                        <td>{{ $n->horas_afectadas > 0 ? $n->horas_afectadas.'h' : '—' }}</td>
                        <td>
                            @if($n->resta_horas)
                                <span class="badge bg-danger-subtle text-danger">Sí</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">No</span>
                            @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('novedades.update',$n) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="{{ $n->estado }}">
                                <input type="hidden" name="visible_para_medico" value="{{ $n->visible_para_medico ? 0 : 1 }}">
                                <button class="btn btn-sm {{ $n->visible_para_medico ? 'btn-success' : 'btn-outline-secondary' }}" title="Toggle visibilidad">
                                    <i class="bi bi-eye{{ $n->visible_para_medico ? '-fill' : '' }}"></i>
                                </button>
                            </form>
                        </td>
                        <td><span class="badge bg-{{ $n->badge_estado }}">{{ ucfirst($n->estado) }}</span></td>
                        <td>
                            <form method="POST" action="{{ route('novedades.destroy',$n) }}" onsubmit="return confirm('¿Anular novedad?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No hay novedades para este período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $novedades->withQueryString()->links() }}</div>
    </div>
</div>

</div>

{{-- Modal nueva novedad --}}
<div class="modal fade" id="modalNovedad" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-clipboard2-plus me-2"></i>Registrar novedad</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('novedades.store') }}">
            @csrf
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Médico <span class="text-danger">*</span></label>
                        <select name="medico_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            @foreach(\App\Models\Medico::where('activo',true)->orderBy('nombre')->get() as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Tipo <span class="text-danger">*</span></label>
                        <select name="tipo_novedad" class="form-select" required>
                            @foreach($tipos as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Horas afectadas</label>
                        <input type="number" name="horas_afectadas" class="form-control" value="0" min="0" max="24" step="0.5">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="resta_horas" value="1" class="form-check-input" id="chkResta">
                            <label class="form-check-label" for="chkResta">Resta horas</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="visible_para_medico" value="1" class="form-check-input" id="chkVisible">
                            <label class="form-check-label" for="chkVisible">Visible médico</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar novedad</button>
            </div>
        </form>
    </div></div>
</div>
@endsection
