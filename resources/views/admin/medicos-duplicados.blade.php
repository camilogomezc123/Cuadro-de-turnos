@extends('layouts.app')
@section('title','Médicos Duplicados')
@section('page-title','Deduplicación de Médicos')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('usuarios.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Médicos duplicados</li>
@endsection

@section('content')
<div class="container-fluid py-3">

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-4 gap-3 flex-wrap">
        <div>
            <h5 class="fw-bold mb-0"><i class="bi bi-people text-warning me-2"></i>Médicos con nombres duplicados</h5>
            <small class="text-muted">
                Se detectan médicos con el mismo nombre (sin importar mayúsculas/minúsculas).
                Al fusionar, <strong>todos los turnos se preservan</strong> bajo el registro principal.
            </small>
        </div>
        @if(count($grupos) > 0)
        <form method="POST" action="{{ route('medicos.duplicados.fusionar-todos') }}"
              onsubmit="return confirm('¿Fusionar TODOS los duplicados automáticamente? Se conservará el registro con más turnos.')">
            @csrf
            <button class="btn btn-warning">
                <i class="bi bi-magic me-1"></i>Fusionar todos automáticamente
            </button>
        </form>
        @endif
    </div>

    @if(count($grupos) === 0)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-3"></i>
            <h5 class="fw-bold text-success">¡Sin duplicados!</h5>
            <p class="text-muted">No se encontraron médicos con nombres duplicados en la base de datos.</p>
        </div>
    </div>
    @else

    <div class="alert alert-warning py-2 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Se encontraron <strong>{{ count($grupos) }} grupo(s)</strong> con nombres duplicados.
        El registro marcado como <span class="badge bg-success">Principal</span> conservará todos los turnos.
        Los <span class="badge bg-danger">Duplicados</span> serán eliminados después de transferir sus datos.
    </div>

    @foreach($grupos as $grupo)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-0 bg-warning bg-opacity-10 d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
                <strong>{{ ucwords($grupo['llave']) }}</strong>
                <span class="badge bg-secondary ms-2">{{ count($grupo['medicos']) }} registros</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Rol</th>
                            <th>ID</th>
                            <th>Nombre en BD</th>
                            <th>UCI</th>
                            <th class="text-center">Turnos</th>
                            <th class="text-center">Cuenta</th>
                            <th class="text-center">Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($grupo['medicos'] as $i => $m)
                    <tr class="{{ $i===0 ? 'table-success' : 'table-danger bg-opacity-10' }}">
                        <td>
                            @if($i===0)
                                <span class="badge bg-success">Principal</span>
                            @else
                                <span class="badge bg-danger">Duplicado</span>
                            @endif
                        </td>
                        <td class="text-muted small">#{{ $m['id'] }}</td>
                        <td class="fw-semibold">
                            {{ $m['nombre_completo'] }}
                            @if(strtoupper($m['nombre']) === $m['nombre'])
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem">TODO MAYÚSCULAS</span>
                            @endif
                        </td>
                        <td>{{ $m['uci'] }}</td>
                        <td class="text-center fw-bold {{ $m['total_turnos']>0?'text-primary':'' }}">{{ $m['total_turnos'] }}</td>
                        <td class="text-center">
                            @if($m['tiene_user'])
                                <i class="bi bi-person-check-fill text-success" title="Tiene usuario"></i>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($m['activo'])
                                <span class="badge bg-success-subtle text-success">Activo</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            @if($i > 0)
                            {{-- Fusionar este duplicado con el principal (primero de la lista) --}}
                            <form method="POST" action="{{ route('medicos.duplicados.fusionar') }}"
                                  onsubmit="return confirm('¿Fusionar \"{{ addslashes($m['nombre_completo']) }}\" con \"{{ addslashes($grupo['medicos'][0]['nombre_completo']) }}\"?\n\nTodos los {{ $m['total_turnos'] }} turno(s) se transferirán al registro principal.')">
                                @csrf
                                <input type="hidden" name="primario_id"  value="{{ $grupo['medicos'][0]['id'] }}">
                                <input type="hidden" name="duplicado_id" value="{{ $m['id'] }}">
                                <button class="btn btn-sm btn-danger">
                                    <i class="bi bi-arrow-up-circle me-1"></i>Fusionar con Principal
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if(count($grupo['medicos']) > 2)
            <div class="px-3 py-2 bg-light text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                Hay más de 2 duplicados. Fusione de uno en uno para mayor control, o use "Fusionar todos".
            </div>
            @endif
        </div>
    </div>
    @endforeach

    @endif

</div>
@endsection
