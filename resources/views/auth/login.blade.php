<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — Cuadro de Turnos UCI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #1565C0 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-card {
            background: #fff; border-radius: 18px; padding: 2.5rem 2rem;
            box-shadow: 0 24px 60px rgba(0,0,0,.35); width: 100%; max-width: 400px;
        }
        .login-logo { width: 56px; height: 56px; background: #1565C0; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; }
        .login-logo i { color: #fff; font-size: 2rem; }
        .form-control:focus { border-color: #1565C0; box-shadow: 0 0 0 .2rem rgba(21,101,192,.25); }
        .btn-login { background: #1565C0; border: none; font-weight: 600; padding: .7rem;
            letter-spacing: .03em; transition: background .2s; }
        .btn-login:hover { background: #0d47a1; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo"><i class="bi bi-hospital"></i></div>
    <h5 class="text-center fw-bold mb-1" style="color:#0d47a1">Cuadro de Turnos UCI</h5>
    <p class="text-center text-muted small mb-4">Clínica — Sistema de Gestión</p>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if($errors->has('email'))
        <div class="alert alert-danger py-2 small">{{ $errors->first('email') }}</div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label small fw-semibold">Correo electrónico</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" placeholder="usuario@ejemplo.com" autofocus required>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold">Contraseña</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label small text-muted" for="remember">Recordar sesión</label>
        </div>
        <button type="submit" class="btn btn-login btn-primary w-100 text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
        </button>
    </form>
</div>
</body>
</html>
