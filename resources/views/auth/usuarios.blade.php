@extends('layouts.app')
@section('title', 'Gestión de Usuarios')
@section('page-title', 'Usuarios del Sistema')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuarios</li>
@endsection
@section('content')

<div class="row g-4">
    {{-- Crear usuario --}}
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-person-plus me-2 text-primary"></i>Nuevo Usuario</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('usuarios.crear') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nombre completo</label>
                        <input type="text" name="name" class="form-control form-control-sm" required value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Correo electrónico</label>
                        <input type="email" name="email" class="form-control form-control-sm" required value="{{ old('email') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contraseña</label>
                        <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Rol</label>
                        <select name="rol" class="form-select form-select-sm" required>
                            <option value="visualizador">Visualizador (solo lectura)</option>
                            <option value="coordinador">Coordinador (gestión)</option>
                            <option value="master">Master (acceso total)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">UCI asignada (opcional)</label>
                        <input type="text" name="uci_asignada" class="form-control form-control-sm"
                               placeholder="UCI-C, UCI-CARDIO, ..." value="{{ old('uci_asignada') }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-person-check me-1"></i>Crear usuario
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listado --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-people me-2"></i>Usuarios Registrados</span>
            </div>
            <div class="panel-body p-0">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>UCI</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($usuarios as $u)
                        <tr>
                            <td class="fw-semibold small">
                                {{ $u->name }}
                                @if($u->id === auth()->id())
                                    <span class="badge bg-primary-subtle text-primary ms-1">Tú</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $u->email }}</td>
                            <td>
                                @php
                                    $rolClase = match($u->rol){
                                        'master'=>'bg-danger-subtle text-danger',
                                        'coordinador'=>'bg-warning-subtle text-warning',
                                        default=>'bg-secondary-subtle text-secondary'
                                    };
                                    $rolLabel = match($u->rol){
                                        'master'=>'Master','coordinador'=>'Coordinador',default=>'Visualizador'
                                    };
                                @endphp
                                <span class="badge {{ $rolClase }}">{{ $rolLabel }}</span>
                            </td>
                            <td class="small text-muted">{{ $u->uci_asignada ?? '—' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    {{-- Cambiar contraseña --}}
                                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#pwd-modal-{{ $u->id }}">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    @if($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('usuarios.eliminar', $u) }}"
                                          onsubmit="return confirm('¿Eliminar usuario {{ $u->name }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                    @endif
                                </div>
                                {{-- Modal contraseña --}}
                                <div class="modal fade" id="pwd-modal-{{ $u->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title">Cambiar contraseña — {{ $u->name }}</h6>
                                                <button class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="{{ route('usuarios.password', $u) }}">
                                                @csrf @method('PATCH')
                                                <div class="modal-body">
                                                    <input type="password" name="password" class="form-control form-control-sm mb-2"
                                                           placeholder="Nueva contraseña" required minlength="6">
                                                    <input type="password" name="password_confirmation" class="form-control form-control-sm"
                                                           placeholder="Confirmar" required>
                                                </div>
                                                <div class="modal-footer py-2">
                                                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
