@extends('layouts.app')
@section('title','Gestión de Usuarios')
@section('page-title','Usuarios del Sistema')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
<div class="fade-in">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
</div>
@endif

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUsuarios">
            <i class="bi bi-people me-1"></i>Usuarios del sistema
            <span class="badge bg-secondary ms-1">{{ $usuarios->count() }}</span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMedicos">
            <i class="bi bi-person-badge me-1"></i>Acceso de médicos
            @if($medicosSinUsuario->count())
            <span class="badge bg-warning text-dark ms-1">{{ $medicosSinUsuario->count() }} sin cuenta</span>
            @endif
        </button>
    </li>
</ul>

<div class="tab-content">

{{-- ══════════════════════════════════════════
     TAB 1: USUARIOS DEL SISTEMA
══════════════════════════════════════════ --}}
<div class="tab-pane fade show active" id="tabUsuarios">
<div class="row g-4">

    {{-- Formulario crear usuario --}}
    <div class="col-lg-4">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-person-plus me-2 text-primary"></i>Nuevo usuario</span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('usuarios.crear') }}" id="formCrearUsuario">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="inputNombre" class="form-control form-control-sm"
                               required value="{{ old('name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Correo electrónico <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="inputEmail" class="form-control form-control-sm"
                               required value="{{ old('email') }}">
                        <div class="form-text">Será usado para iniciar sesión.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Contraseña <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" name="password" id="inputPwd"
                                   class="form-control" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('inputPwd')">
                                <i class="bi bi-eye" id="eyePwd"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Confirmar contraseña <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" name="password_confirmation" id="inputPwd2"
                                   class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('inputPwd2')">
                                <i class="bi bi-eye" id="eyePwd2"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rol <span class="text-danger">*</span></label>
                        <select name="rol" class="form-select form-select-sm" id="selectRol"
                                onchange="onRolChange()" required>
                            <option value="">Seleccione...</option>
                            <option value="medico"       @selected(old('rol')=='medico')>Médico (acceso a su portal)</option>
                            <option value="master"       @selected(old('rol')=='master')>Master (acceso total)</option>
                            <option value="coordinador"  @selected(old('rol')=='coordinador')>Coordinador</option>
                            <option value="visualizador" @selected(old('rol')=='visualizador')>Visualizador (solo lectura)</option>
                        </select>
                    </div>

                    {{-- Solo visible cuando rol = medico --}}
                    <div class="mb-3 d-none" id="secMedico">
                        <label class="form-label small fw-bold">Vincular médico</label>
                        <select name="medico_id" id="selectMedico" class="form-select form-select-sm"
                                onchange="autocompletarMedico()">
                            <option value="">— Sin vincular —</option>
                            @foreach($medicosSinUsuario as $m)
                                <option value="{{ $m->id }}"
                                        data-nombre="{{ $m->nombre_completo }}"
                                        data-email="{{ strtolower(str_replace([' ','á','é','í','ó','ú','ñ'],['.',  'a','e','i','o','u','n'], $m->nombre)) }}@medico.uci.local"
                                        @selected(old('medico_id')==$m->id)>
                                    {{ $m->nombre_completo }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Al seleccionar un médico, se autocompletan nombre y correo.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-person-check me-1"></i>Crear usuario
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Lista de usuarios --}}
    <div class="col-lg-8">
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-list-ul me-2"></i>Usuarios registrados</span>
            </div>
            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Médico vinculado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuarios as $u)
                            <tr>
                                <td class="fw-semibold small">
                                    {{ $u->name }}
                                    @if($u->id === auth()->id())
                                        <span class="badge bg-primary-subtle text-primary ms-1 small">Tú</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $u->email }}</td>
                                <td>
                                    @php
                                        $rc = match($u->rol){
                                            'master'     => 'bg-danger-subtle text-danger',
                                            'medico'     => 'bg-success-subtle text-success',
                                            'coordinador'=> 'bg-warning-subtle text-warning',
                                            default      => 'bg-secondary-subtle text-secondary',
                                        };
                                        $rl = match($u->rol){
                                            'master'     => 'Master',
                                            'medico'     => 'Médico',
                                            'coordinador'=> 'Coordinador',
                                            default      => 'Visualizador',
                                        };
                                    @endphp
                                    <span class="badge {{ $rc }}">{{ $rl }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('usuarios.toggle', $u) }}" class="d-inline"
                                          @if($u->id === auth()->id()) style="pointer-events:none;opacity:.5" @endif>
                                        @csrf @method('PATCH')
                                        @php $activo = !is_null($u->email_verified_at); @endphp
                                        <button type="submit" class="btn btn-sm {{ $activo ? 'btn-success' : 'btn-outline-secondary' }}"
                                                title="{{ $activo ? 'Activo — clic para desactivar' : 'Inactivo — clic para activar' }}"
                                                @if($u->id === auth()->id()) disabled @endif>
                                            <i class="bi bi-{{ $activo ? 'check-circle-fill' : 'x-circle' }}"></i>
                                            {{ $activo ? 'Activo' : 'Inactivo' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="small text-muted">
                                    {{ $u->medico?->nombre_completo ?? '—' }}
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#pwd-{{ $u->id }}"
                                                title="Cambiar contraseña">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('usuarios.eliminar', $u) }}"
                                              onsubmit="return confirm('¿Eliminar usuario {{ addslashes($u->name) }}?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Modal contraseña --}}
                            <div class="modal fade" id="pwd-{{ $u->id }}" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header py-2">
                                            <h6 class="modal-title"><i class="bi bi-key me-1"></i>{{ $u->name }}</h6>
                                            <button class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('usuarios.password', $u) }}">
                                            @csrf @method('PATCH')
                                            <div class="modal-body">
                                                <div class="mb-2">
                                                    <label class="form-label small fw-bold">Nueva contraseña</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="password" name="password" id="mpwd-{{ $u->id }}"
                                                               class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                                onclick="togglePwd('mpwd-{{ $u->id }}')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="form-label small fw-bold">Confirmar</label>
                                                    <input type="password" name="password_confirmation"
                                                           class="form-control form-control-sm" required placeholder="Repetir contraseña">
                                                </div>
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-check me-1"></i>Guardar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /row --}}
</div>{{-- /tab usuarios --}}


{{-- ══════════════════════════════════════════
     TAB 2: ACCESO DE MÉDICOS
══════════════════════════════════════════ --}}
<div class="tab-pane fade" id="tabMedicos">

    {{-- Creación masiva --}}
    @if($medicosSinUsuario->count())
    <div class="panel mb-4">
        <div class="panel-header">
            <span class="panel-title">
                <i class="bi bi-people-fill me-2 text-warning"></i>
                {{ $medicosSinUsuario->count() }} médico(s) sin acceso al sistema
            </span>
        </div>
        <div class="panel-body">
            <form method="POST" action="{{ route('usuarios.medicos-masivo') }}">
                @csrf
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Contraseña por defecto <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="password" name="password_default" id="pwdMasivo"
                                   class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwdMasivo')">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Se asignará a todos los seleccionados. Deberán cambiarla.</div>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-warning btn-sm" id="btnMasivo" disabled>
                            <i class="bi bi-people-fill me-1"></i>
                            Crear cuentas para seleccionados (<span id="cntSel">0</span>)
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px">
                                    <input type="checkbox" id="chkTodos" class="form-check-input"
                                           onchange="toggleTodos(this)">
                                </th>
                                <th>Médico</th>
                                <th>UCI</th>
                                <th>Correo generado</th>
                                <th>Acción individual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($medicosSinUsuario as $m)
                            @php
                                $emailGen = strtolower(str_replace(
                                    [' ','á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'],
                                    ['.','a','e','i','o','u','n','a','e','i','o','u','n'],
                                    $m->nombre
                                )) . '@medico.uci.local';
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" name="medico_ids[]" value="{{ $m->id }}"
                                           class="form-check-input chk-med" onchange="actualizarCnt()">
                                </td>
                                <td class="fw-semibold">{{ $m->nombre_completo }}</td>
                                <td><small class="text-muted">{{ $m->uci?->codigo ?? '—' }}</small></td>
                                <td><code class="small">{{ $emailGen }}</code></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="abrirCrearUno({{ $m->id }}, '{{ addslashes($m->nombre_completo) }}', '{{ $emailGen }}')">
                                        <i class="bi bi-person-plus me-1"></i>Crear cuenta
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Médicos con cuenta --}}
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="bi bi-person-check me-2 text-success"></i>Médicos con acceso al sistema</span>
        </div>
        <div class="panel-body p-0">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Médico</th>
                            <th>UCI</th>
                            <th>Correo de acceso</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($medicosConUsuario as $m)
                        @php $u = $m->user; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $m->nombre_completo }}</td>
                            <td><small class="text-muted">{{ $m->uci?->codigo ?? '—' }}</small></td>
                            <td><code class="small">{{ $u?->email }}</code></td>
                            <td>
                                @php $activo = $u && !is_null($u->email_verified_at); @endphp
                                <span class="badge {{ $activo ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if($u)
                                    {{-- Toggle --}}
                                    <form method="POST" action="{{ route('usuarios.toggle', $u) }}">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm {{ $activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                                title="{{ $activo ? 'Desactivar acceso' : 'Activar acceso' }}">
                                            <i class="bi bi-{{ $activo ? 'person-x' : 'person-check' }}"></i>
                                        </button>
                                    </form>
                                    {{-- Cambiar contraseña --}}
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#pwd-{{ $u->id }}"
                                            title="Cambiar contraseña">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    @endif
                                </div>
                                @if($u)
                                <div class="modal fade" id="pwd-{{ $u->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <div class="modal-content">
                                            <div class="modal-header py-2">
                                                <h6 class="modal-title"><i class="bi bi-key me-1"></i>{{ $m->nombre_completo }}</h6>
                                                <button class="btn-close btn-sm" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="{{ route('usuarios.password', $u) }}">
                                                @csrf @method('PATCH')
                                                <div class="modal-body">
                                                    <label class="form-label small fw-bold">Nueva contraseña</label>
                                                    <div class="input-group input-group-sm mb-2">
                                                        <input type="password" name="password" id="pm-{{ $u->id }}"
                                                               class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                                                        <button type="button" class="btn btn-outline-secondary"
                                                                onclick="togglePwd('pm-{{ $u->id }}')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                    <input type="password" name="password_confirmation"
                                                           class="form-control form-control-sm" required placeholder="Confirmar">
                                                </div>
                                                <div class="modal-footer py-2">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Ningún médico tiene cuenta aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>{{-- /tab medicos --}}

</div>{{-- /tab-content --}}
</div>

{{-- Modal: Crear cuenta individual para médico --}}
<div class="modal fade" id="modalCrearUno" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Crear acceso — <span id="mNombreUno"></span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('usuarios.crear') }}">
            @csrf
            <input type="hidden" name="rol" value="medico">
            <input type="hidden" name="medico_id" id="mIdUno">
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nombre</label>
                    <input type="text" name="name" id="mNombreInputUno" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Correo de acceso</label>
                    <input type="email" name="email" id="mEmailUno" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="mPwdUno"
                               class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('mPwdUno')">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="form-control" required placeholder="Repetir">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-check me-1"></i>Crear cuenta
                </button>
            </div>
        </form>
    </div></div>
</div>

@endsection

@push('scripts')
<script>
function onRolChange() {
    const rol = document.getElementById('selectRol').value;
    document.getElementById('secMedico').classList.toggle('d-none', rol !== 'medico');
}

function autocompletarMedico() {
    const sel = document.getElementById('selectMedico');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('inputNombre').value = opt.dataset.nombre ?? '';
    document.getElementById('inputEmail').value  = opt.dataset.email  ?? '';
}

function togglePwd(id) {
    const inp = document.getElementById(id);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Selección masiva
function toggleTodos(chk) {
    document.querySelectorAll('.chk-med').forEach(c => c.checked = chk.checked);
    actualizarCnt();
}
function actualizarCnt() {
    const n = document.querySelectorAll('.chk-med:checked').length;
    document.getElementById('cntSel').textContent = n;
    document.getElementById('btnMasivo').disabled = n === 0;
}

// Modal crear cuenta individual
function abrirCrearUno(id, nombre, email) {
    document.getElementById('mIdUno').value           = id;
    document.getElementById('mNombreUno').textContent  = nombre;
    document.getElementById('mNombreInputUno').value   = nombre;
    document.getElementById('mEmailUno').value         = email;
    new bootstrap.Modal(document.getElementById('modalCrearUno')).show();
}

// Activar tab por parámetro de URL
const urlTab = new URLSearchParams(location.search).get('tab');
if (urlTab === 'medicos') {
    document.querySelector('[data-bs-target="#tabMedicos"]')?.click();
}
</script>
@endpush
