@extends('layouts.app')
@section('title','Gestión de Usuarios')
@section('page-title','Gestión de Usuarios')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@push('styles')
<style>
.user-card {
    background: #fff;
    border: 1px solid #e5e9f0;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    overflow: hidden;
}
.user-card-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid #f0f4f8;
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-card-header .icon-wrap {
    width: 36px; height: 36px; border-radius: 10px;
    background: #e3f2fd;
    display: flex; align-items: center; justify-content: center;
}
.user-card-header h6 { margin: 0; font-weight: 700; color: #1a2340; font-size: 1rem; }
.user-card-body { padding: 20px 22px; }
.form-label { font-size: .82rem; font-weight: 600; color: #4a5568; margin-bottom: 5px; }
.rol-btn-group .btn { border-radius: 8px !important; font-size: .85rem; font-weight: 600; padding: 8px 18px; }
.user-row { padding: 12px 16px; border-bottom: 1px solid #f0f4f8; display: flex; align-items: center; gap: 12px; }
.user-row:last-child { border-bottom: none; }
.user-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .95rem; flex-shrink: 0;
}
.user-info .name { font-weight: 600; font-size: .9rem; color: #1a2340; }
.user-info .email { font-size: .78rem; color: #6c757d; }
.badge-master  { background: #fee2e2; color: #dc2626; }
.badge-operativo { background: #dcfce7; color: #16a34a; }
.badge-rol { font-size: .72rem; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
.btn-accion { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-dot.activo   { background: #22c55e; }
.status-dot.inactivo { background: #94a3b8; }
</style>
@endpush

@section('content')
<div class="fade-in">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show rounded-3">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show rounded-3">
    <i class="bi bi-exclamation-circle-fill me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger rounded-3">
    <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
</div>
@endif

<div class="row g-4">

{{-- ── COLUMNA IZQUIERDA: Crear usuario ── --}}
<div class="col-lg-4">

    {{-- Crear usuario --}}
    <div class="user-card mb-4">
        <div class="user-card-header">
            <div class="icon-wrap"><i class="bi bi-person-plus-fill text-primary fs-5"></i></div>
            <h6>Nuevo Usuario</h6>
        </div>
        <div class="user-card-body">
            <form method="POST" action="{{ route('usuarios.crear') }}" id="formCrear">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="name" id="inpNombre" class="form-control"
                           placeholder="Ej: Diego Escobar"
                           value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" id="inpEmail" class="form-control"
                           placeholder="correo@ejemplo.com"
                           value="{{ old('email') }}" required>
                </div>

                {{-- Rol --}}
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <div class="d-flex gap-2" id="rolBtns">
                        <button type="button" class="btn btn-outline-success flex-fill rol-btn @if(old('rol','medico')==='medico') active @endif"
                                onclick="setRol('medico')" id="btnOperativo">
                            <i class="bi bi-person-badge me-1"></i>Operativo
                        </button>
                        <button type="button" class="btn btn-outline-danger flex-fill rol-btn @if(old('rol')==='master') active @endif"
                                onclick="setRol('master')" id="btnMaster">
                            <i class="bi bi-shield-fill me-1"></i>Master
                        </button>
                    </div>
                    <input type="hidden" name="rol" id="hidRol" value="{{ old('rol','medico') }}" required>
                </div>

                {{-- Vincular médico (solo rol operativo) --}}
                <div class="mb-3" id="secMedico">
                    <label class="form-label">Vincular médico <span class="text-muted fw-normal">(opcional)</span></label>
                    <select name="medico_id" id="selMedico" class="form-select" onchange="autoFill()">
                        <option value="">— Sin vincular —</option>
                        @foreach($medicosSinUsuario as $m)
                            <option value="{{ $m->id }}"
                                    data-nombre="{{ $m->nombre_completo }}"
                                    data-email="{{ Str::lower(Str::ascii($m->nombre)) }}@medico.uci.local"
                                    @selected(old('medico_id')==$m->id)>
                                {{ $m->nombre_completo }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Al seleccionar, se autocompletan nombre y correo.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password" id="inpPwd"
                               class="form-control" required minlength="6"
                               placeholder="Mínimo 6 caracteres">
                        <button type="button" class="btn btn-outline-secondary px-3"
                                onclick="toggleVer('inpPwd','eyeA')">
                            <i class="bi bi-eye" id="eyeA"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirmar contraseña</label>
                    <div class="input-group">
                        <input type="password" name="password_confirmation" id="inpPwd2"
                               class="form-control" required
                               placeholder="Repetir contraseña">
                        <button type="button" class="btn btn-outline-secondary px-3"
                                onclick="toggleVer('inpPwd2','eyeB')">
                            <i class="bi bi-eye" id="eyeB"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100" style="border-radius:10px;padding:11px">
                    <i class="bi bi-plus-circle me-2"></i>Crear usuario
                </button>
            </form>
        </div>
    </div>

    {{-- Médicos sin cuenta --}}
    @if($medicosSinUsuario->count())
    <div class="user-card">
        <div class="user-card-header">
            <div class="icon-wrap" style="background:#fff3cd">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-6"></i>
            </div>
            <h6>Médicos sin acceso <span class="badge bg-warning text-dark ms-1">{{ $medicosSinUsuario->count() }}</span></h6>
        </div>
        <div class="user-card-body p-0">
            @foreach($medicosSinUsuario->take(8) as $m)
            <div class="user-row">
                <div class="user-avatar" style="background:#f0f4f8;color:#64748b;font-size:.78rem">
                    {{ strtoupper(substr($m->nombre,0,1)) }}{{ strtoupper(substr($m->apellido??'',0,1)) }}
                </div>
                <div class="user-info flex-grow-1">
                    <div class="name">{{ $m->nombre_completo }}</div>
                    <div class="email">{{ $m->uci?->codigo ?? 'Sin UCI' }}</div>
                </div>
                <button class="btn btn-sm btn-outline-primary btn-accion" style="width:auto;padding:4px 10px"
                        onclick="crearParaMedico({{ $m->id }},'{{ addslashes($m->nombre_completo) }}',
                                 '{{ Str::lower(Str::ascii($m->nombre)) }}@medico.uci.local')"
                        title="Crear cuenta">
                    <i class="bi bi-person-plus"></i>
                </button>
            </div>
            @endforeach
            @if($medicosSinUsuario->count() > 8)
            <div class="text-center py-2 text-muted small">
                + {{ $medicosSinUsuario->count() - 8 }} más sin cuenta
            </div>
            @endif
        </div>
    </div>
    @endif

</div>

{{-- ── COLUMNA DERECHA: Lista de usuarios ── --}}
<div class="col-lg-8">
    <div class="user-card">
        <div class="user-card-header justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="icon-wrap" style="background:#ede9fe">
                    <i class="bi bi-people-fill fs-6" style="color:#7c3aed"></i>
                </div>
                <h6>Usuarios registrados</h6>
            </div>
            <span class="badge bg-secondary rounded-pill">{{ $usuarios->count() }} total</span>
        </div>

        {{-- Buscador rápido --}}
        <div class="px-3 pt-3 pb-2">
            <input type="text" id="buscarUsuario" class="form-control form-control-sm"
                   placeholder="Buscar por nombre o correo..." oninput="filtrarUsuarios()">
        </div>

        <div id="listaUsuarios">
        @foreach($usuarios as $u)
        @php
            $esActivo = !is_null($u->email_verified_at);
            $esMaster = $u->rol === 'master';
            $initials = collect(explode(' ', $u->name))->take(2)->map(fn($w) => strtoupper(substr($w,0,1)))->join('');
            $bgAvatar = $esMaster ? '#fee2e2' : '#dcfce7';
            $clAvatar = $esMaster ? '#dc2626' : '#16a34a';
        @endphp
        <div class="user-row fila-usuario" data-buscar="{{ strtolower($u->name . ' ' . $u->email) }}">
            <div class="user-avatar" style="background:{{ $bgAvatar }};color:{{ $clAvatar }}">
                {{ $initials ?: '?' }}
            </div>
            <div class="user-info flex-grow-1">
                <div class="name d-flex align-items-center gap-2">
                    {{ $u->name }}
                    @if($u->id === auth()->id())
                        <span class="badge bg-primary-subtle text-primary" style="font-size:.65rem">Tú</span>
                    @endif
                </div>
                <div class="email">{{ $u->email }}</div>
                @if($u->medico)
                <div class="email" style="color:#7c3aed">
                    <i class="bi bi-person-badge me-1"></i>{{ $u->medico->nombre_completo }}
                </div>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2">
                {{-- Badge rol --}}
                <span class="badge-rol {{ $esMaster ? 'badge-master' : 'badge-operativo' }}">
                    {{ $esMaster ? 'Master' : 'Operativo' }}
                </span>

                {{-- Estado --}}
                <span title="{{ $esActivo ? 'Activo' : 'Inactivo' }}">
                    <span class="status-dot {{ $esActivo ? 'activo' : 'inactivo' }}"></span>
                </span>

                {{-- Toggle activo --}}
                @if($u->id !== auth()->id())
                <form method="POST" action="{{ route('usuarios.toggle', $u) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="btn btn-sm btn-accion {{ $esActivo ? 'btn-outline-warning' : 'btn-outline-success' }}"
                            title="{{ $esActivo ? 'Desactivar' : 'Activar' }}">
                        <i class="bi bi-{{ $esActivo ? 'pause' : 'play' }}"></i>
                    </button>
                </form>
                @endif

                {{-- Cambiar contraseña --}}
                <button class="btn btn-sm btn-accion btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#pwd{{ $u->id }}"
                        title="Cambiar contraseña">
                    <i class="bi bi-key"></i>
                </button>

                {{-- Eliminar --}}
                @if($u->id !== auth()->id())
                <form method="POST" action="{{ route('usuarios.eliminar', $u) }}"
                      onsubmit="return confirm('¿Eliminar a {{ addslashes($u->name) }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-accion btn-outline-danger" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
                @endif
            </div>
        </div>

        {{-- Modal: cambiar contraseña --}}
        <div class="modal fade" id="pwd{{ $u->id }}" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content" style="border-radius:14px">
                    <div class="modal-header border-0 pb-0">
                        <h6 class="modal-title fw-bold">
                            <i class="bi bi-key me-1 text-primary"></i>Cambiar contraseña
                        </h6>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-1">
                        <div class="small text-muted mb-3">{{ $u->name }}</div>
                        <form method="POST" action="{{ route('usuarios.password', $u) }}">
                            @csrf @method('PATCH')
                            <div class="mb-2">
                                <label class="form-label" style="font-size:.8rem;font-weight:600">Nueva contraseña</label>
                                <div class="input-group input-group-sm">
                                    <input type="password" name="password" id="np{{ $u->id }}"
                                           class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                                    <button type="button" class="btn btn-outline-secondary"
                                            onclick="toggleVer('np{{ $u->id }}','')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" style="font-size:.8rem;font-weight:600">Confirmar</label>
                                <input type="password" name="password_confirmation"
                                       class="form-control form-control-sm" required placeholder="Repetir">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-check me-1"></i>Guardar contraseña
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @endforeach
        </div>{{-- /listaUsuarios --}}

        @if($usuarios->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-people fs-1 d-block mb-2"></i>
            No hay usuarios registrados.
        </div>
        @endif
    </div>
</div>

</div>{{-- /row --}}
</div>

{{-- Modal: Crear cuenta individual para médico --}}
<div class="modal fade" id="modalCrearUno" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-person-plus me-2 text-primary"></i>Crear acceso
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('usuarios.crear') }}">
                @csrf
                <input type="hidden" name="rol" value="medico">
                <input type="hidden" name="medico_id" id="mIdUno">
                <div class="modal-body">
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Se creará una cuenta <strong>Operativo</strong> para este médico.
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.82rem;font-weight:600">Nombre</label>
                        <input type="text" name="name" id="mNomUno" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.82rem;font-weight:600">Correo de acceso</label>
                        <input type="email" name="email" id="mEmailUno" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.82rem;font-weight:600">Contraseña</label>
                        <div class="input-group">
                            <input type="password" name="password" id="mPwdUno"
                                   class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="toggleVer('mPwdUno','eyeM')">
                                <i class="bi bi-eye" id="eyeM"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" style="font-size:.82rem;font-weight:600">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation"
                               class="form-control" required placeholder="Repetir contraseña">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-check me-1"></i>Crear cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Selección de rol con botones
function setRol(rol) {
    document.getElementById('hidRol').value = rol;
    document.getElementById('btnOperativo').classList.toggle('active', rol === 'medico');
    document.getElementById('btnMaster').classList.toggle('active', rol === 'master');
    document.getElementById('secMedico').classList.toggle('d-none', rol === 'master');
}
// Inicializar estado del botón activo
setRol(document.getElementById('hidRol').value || 'medico');

// Auto-completar desde selector de médico
function autoFill() {
    const sel = document.getElementById('selMedico');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('inpNombre').value = opt.dataset.nombre || '';
    document.getElementById('inpEmail').value  = opt.dataset.email  || '';
}

// Ver/ocultar contraseña
function toggleVer(id) {
    const inp = document.getElementById(id);
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
}

// Filtrar usuarios por nombre/correo
function filtrarUsuarios() {
    const q = document.getElementById('buscarUsuario').value.toLowerCase();
    document.querySelectorAll('.fila-usuario').forEach(row => {
        row.style.display = row.dataset.buscar.includes(q) ? '' : 'none';
    });
}

// Modal crear cuenta individual
function crearParaMedico(id, nombre, email) {
    document.getElementById('mIdUno').value  = id;
    document.getElementById('mNomUno').value = nombre;
    document.getElementById('mEmailUno').value = email;
    new bootstrap.Modal(document.getElementById('modalCrearUno')).show();
}
</script>
@endpush
