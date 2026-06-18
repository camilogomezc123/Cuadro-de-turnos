@extends('layouts.app')

@section('title', 'Burnout – Gestión de Preguntas')

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ route('burnout.index') }}" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="fw-bold fs-5">Gestión de preguntas de burnout</span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Aviso legal --}}
    <div class="alert alert-warning border border-warning">
        <i class="bi bi-shield-lock-fill me-2"></i>
        <strong>Aviso importante:</strong> El Maslach Burnout Inventory (MBI) es un instrumento con derechos de autor.
        Los ítems oficiales deben ser adquiridos a través de los canales autorizados por su institución.
        Los textos de referencia son marcadores de posición — el administrador debe reemplazarlos con el texto institucionalmente autorizado.
    </div>

    {{-- Configuración de encuesta --}}
    @if($encuesta)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Configuración de la encuesta activa</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('burnout.configurar') }}" class="row g-3">
                @csrf
                <div class="col-md-5">
                    <label class="form-label">Nombre de la encuesta</label>
                    <input type="text" name="nombre" class="form-control" value="{{ $encuesta->nombre }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Periodicidad</label>
                    <select name="periodo" class="form-select">
                        <option value="mensual"      @selected($encuesta->periodo=='mensual')>Mensual</option>
                        <option value="bimestral"    @selected($encuesta->periodo=='bimestral')>Bimestral</option>
                        <option value="trimestral"   @selected($encuesta->periodo=='trimestral')>Trimestral</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="permite_posponer" id="posponer" value="1"
                            {{ $encuesta->permite_posponer ? 'checked' : '' }}>
                        <label class="form-check-label" for="posponer">Permite posponer</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Guardar config.</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Preguntas --}}
    @if($encuesta && $encuesta->preguntas->isNotEmpty())
    @php
        $dims = ['agotamiento_emocional' => 'Agotamiento Emocional (AE)', 'despersonalizacion' => 'Despersonalización (DP)', 'realizacion_personal' => 'Realización Personal (RP)'];
        $colors = ['agotamiento_emocional' => 'danger', 'despersonalizacion' => 'warning', 'realizacion_personal' => 'success'];
    @endphp

    @foreach($dims as $dimKey => $dimLabel)
    @php $pregsGrupo = $encuesta->preguntas->where('dimension', $dimKey)->sortBy('orden'); @endphp
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-{{ $colors[$dimKey] }} bg-opacity-10 border-0 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-bold text-{{ $colors[$dimKey] }}">
                {{ $dimLabel }} <span class="badge bg-{{ $colors[$dimKey] }} ms-2">{{ $pregsGrupo->count() }} ítems</span>
            </h6>
            @if($dimKey === 'realizacion_personal')
                <small class="text-muted">Puntuación inversa — mayor puntaje = mejor realización</small>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th style="width:50px">#</th><th>Texto del ítem</th><th style="width:120px">Acciones</th></tr></thead>
                    <tbody>
                        @foreach($pregsGrupo as $p)
                        <tr>
                            <td class="text-muted small">{{ $p->orden }}</td>
                            <td>
                                <form method="POST" action="{{ route('burnout.pregunta.update', $p) }}" class="d-flex gap-2">
                                    @csrf @method('PATCH')
                                    <input type="text" name="texto_pregunta" class="form-control form-control-sm"
                                        value="{{ $p->texto_pregunta }}" {{ !$p->activa ? 'disabled' : '' }}>
                                    <button class="btn btn-sm btn-outline-primary flex-shrink-0" {{ !$p->activa ? 'disabled' : '' }}>
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <span class="badge {{ $p->activa ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $p->activa ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    @else
        <div class="alert alert-info">No hay preguntas configuradas. Ejecute el seeder de burnout para cargar los ítems de referencia.</div>
    @endif

    {{-- Otras encuestas --}}
    @if($encuestas->count() > 1)
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0">
            <h6 class="mb-0 fw-bold">Historial de encuestas</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Nombre</th><th>Período</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @foreach($encuestas as $enc)
                        <tr>
                            <td>{{ $enc->nombre }}</td>
                            <td>{{ ucfirst($enc->periodo) }}</td>
                            <td><span class="badge {{ $enc->activa ? 'bg-success' : 'bg-secondary' }}">{{ $enc->activa ? 'Activa' : 'Inactiva' }}</span></td>
                            <td>
                                @if(!$enc->activa)
                                <form method="POST" action="{{ route('burnout.toggle', $enc) }}">@csrf
                                    <button class="btn btn-xs btn-outline-primary btn-sm py-0 px-1">Activar</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
