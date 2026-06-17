@extends('layouts.app')
@section('title', 'Semanas Molde')
@section('page-title', 'Semanas Molde')
@section('breadcrumb')
    <li class="breadcrumb-item active">Semanas Molde</li>
@endsection
@section('content')
@php
$codigosTurno = ['M','T','MT','N','MTN','MN','VAC','PER','INC','LIBRE'];
$dias = ['lunes'=>'Lun','martes'=>'Mar','miercoles'=>'Mié','jueves'=>'Jue','viernes'=>'Vie','sabado'=>'Sáb','domingo'=>'Dom'];
@endphp

<div class="row g-4">
    {{-- Crear semana molde --}}
    <div class="col-lg-5">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Nueva Semana Molde</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('semanas-molde.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre</label>
                        <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Ej: Semana par UCI-CARDIO" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">UCI (opcional)</label>
                        <select name="uci_id" class="form-select form-select-sm">
                            <option value="">General (aplica a cualquier UCI)</option>
                            @foreach($ucis as $u)<option value="{{ $u->id }}">{{ $u->nombre }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Turnos por día</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody>
                                    @foreach($dias as $diaClave => $diaNombre)
                                    <tr>
                                        <td class="fw-semibold small align-middle" style="width:60px">{{ $diaNombre }}</td>
                                        <td>
                                            <select name="turnos[{{ $diaClave }}]" class="form-select form-select-sm">
                                                @foreach($codigosTurno as $c)
                                                    <option value="{{ $c }}" {{ ($c === (in_array($diaClave,['sabado','domingo']) ? 'LIBRE' : 'M')) ? 'selected' : '' }}>{{ $c }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save me-1"></i>Guardar semana molde
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listado y aplicar --}}
    <div class="col-lg-7">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-layout-wtf me-2"></i>Semanas Molde Guardadas</span>
            </div>
            <div class="panel-body p-0">
                @forelse($semanas as $s)
                <div class="border-bottom p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-semibold">{{ $s->nombre }}</div>
                            @if($s->uci)<div class="text-muted small">UCI: {{ $s->uci->nombre }}</div>@endif
                        </div>
                        <form method="POST" action="{{ route('semanas-molde.destroy', $s) }}"
                              onsubmit="return confirm('¿Eliminar esta semana molde?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                        </form>
                    </div>
                    {{-- Patrón de la semana --}}
                    <div class="d-flex gap-1 flex-wrap mb-3">
                        @foreach($dias as $diaClave => $diaNombre)
                            @php $codigo = $s->turnoParaDia($diaClave); @endphp
                            <div class="text-center" style="min-width:50px">
                                <div class="text-muted" style="font-size:10px">{{ $diaNombre }}</div>
                                <span class="turno-badge badge-{{ $codigo }}" style="font-size:11px">{{ $codigo }}</span>
                            </div>
                        @endforeach
                    </div>
                    {{-- Aplicar a médicos --}}
                    <details>
                        <summary class="text-primary small" style="cursor:pointer">Aplicar a médicos…</summary>
                        <form method="POST" action="{{ route('semanas-molde.aplicar', $s) }}" class="mt-2">
                            @csrf
                            <div class="row g-2">
                                <div class="col-8">
                                    <select name="archivo_id" class="form-select form-select-sm" required>
                                        <option value="">— Período —</option>
                                        @foreach(\App\Models\ArchivoCargado::where('procesado',true)->orderByDesc('anio')->orderByDesc('mes')->get() as $a)
                                            <option value="{{ $a->id }}">{{ $a->nombre_mes }} {{ $a->anio }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="small text-muted">Seleccionar médicos:</label>
                                    <div style="max-height:120px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px;padding:6px">
                                        @foreach(\App\Models\Medico::where('activo',true)->orderBy('nombre')->get() as $m)
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input" type="checkbox" name="medico_ids[]" value="{{ $m->id }}" id="m_{{ $s->id }}_{{ $m->id }}">
                                            <label class="form-check-label small" for="m_{{ $s->id }}_{{ $m->id }}">
                                                {{ $m->nombre }} <span class="text-muted">({{ $m->uci->codigo ?? '?' }})</span>
                                            </label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="bi bi-play-fill me-1"></i>Aplicar molde
                                    </button>
                                </div>
                            </div>
                        </form>
                    </details>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block opacity-25 mb-2"></i>
                    No hay semanas molde creadas.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
