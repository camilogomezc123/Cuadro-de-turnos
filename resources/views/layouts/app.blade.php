<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cuadro de Turnos UCI')</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #0f1d3a;
            --sidebar-hover: #1a3260;
            --sidebar-active: #1e4080;
            --accent: #2196F3;
            --accent-light: #64B5F6;
            --card-shadow: 0 2px 12px rgba(0,0,0,.08);
            --header-height: 60px;
        }

        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f4f8; overflow-x: hidden; }

        /* ── SIDEBAR ── */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            transition: width .25s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 3px 0 15px rgba(0,0,0,.2);
        }

        .sidebar-brand {
            padding: 1.2rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.1);
            display: flex; align-items: center; gap: .75rem;
        }
        .sidebar-brand .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #2196F3, #1565C0);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; color: #fff; flex-shrink: 0;
        }
        .sidebar-brand .brand-text { color: #fff; font-weight: 700; font-size: .9rem; line-height: 1.2; }
        .sidebar-brand .brand-sub  { color: rgba(255,255,255,.5); font-size: .7rem; }

        .sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }
        .sidebar-section { padding: .4rem 1rem .2rem; font-size: .65rem; font-weight: 700;
            letter-spacing: .1em; color: rgba(255,255,255,.3); text-transform: uppercase; }

        .sidebar-nav .nav-link {
            color: rgba(255,255,255,.7);
            padding: .55rem 1rem;
            border-radius: 8px;
            margin: .15rem .5rem;
            display: flex; align-items: center; gap: .65rem;
            font-size: .875rem; font-weight: 500;
            transition: all .2s;
        }
        .sidebar-nav .nav-link:hover    { background: var(--sidebar-hover); color: #fff; }
        .sidebar-nav .nav-link.active   { background: var(--accent); color: #fff; box-shadow: 0 2px 8px rgba(33,150,243,.4); }
        .sidebar-nav .nav-link .bi      { font-size: 1.1rem; flex-shrink: 0; }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255,255,255,.1);
            color: rgba(255,255,255,.4);
            font-size: .7rem;
        }

        /* ── MAIN CONTENT ── */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex; flex-direction: column;
        }

        /* ── TOP BAR ── */
        .topbar {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #e0e7ef;
            display: flex; align-items: center;
            padding: 0 1.5rem;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .topbar .page-title { font-weight: 700; color: #1a2340; font-size: 1.1rem; }
        .topbar .breadcrumb { font-size: .8rem; margin: 0; }
        .topbar .breadcrumb-item a { color: var(--accent); text-decoration: none; }

        /* ── CONTENT AREA ── */
        .content-area { flex: 1; padding: 1.5rem; }

        /* ── KPI CARDS ── */
        .kpi-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid #e8eef7;
            transition: transform .2s, box-shadow .2s;
        }
        .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
        .kpi-card .kpi-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .kpi-card .kpi-value { font-size: 1.8rem; font-weight: 700; color: #1a2340; line-height: 1; }
        .kpi-card .kpi-label { font-size: .78rem; color: #6b7a99; font-weight: 500; margin-top: .25rem; }
        .kpi-card .kpi-sub   { font-size: .72rem; color: #9aa5c0; margin-top: .15rem; }

        /* ── PANEL CARD ── */
        .panel {
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e8eef7;
            overflow: hidden;
        }
        .panel-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f0f4f8;
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-header .panel-title { font-weight: 600; color: #1a2340; font-size: .9rem; }
        .panel-body { padding: 1.25rem; }

        /* ── TURNO BADGES ── */
        .badge-M    { background: #E3F2FD; color: #1565C0; }
        .badge-T    { background: #E8F5E9; color: #2E7D32; }
        .badge-MT   { background: #FFF3E0; color: #E65100; }
        .badge-N    { background: #EDE7F6; color: #4527A0; }
        .badge-MTN  { background: #880E4F; color: #fff; }
        .badge-MN   { background: #FCE4EC; color: #880E4F; }
        .badge-VAC  { background: #E0F7FA; color: #006064; }
        .badge-PER  { background: #FFFDE7; color: #F57F17; }
        .badge-INC  { background: #FFEBEE; color: #C62828; }
        .badge-LIBRE{ background: #F5F5F5; color: #757575; }
        .badge-libre{ background: #F5F5F5; color: #9E9E9E; }

        .turno-badge {
            display: inline-block;
            padding: .2rem .5rem;
            border-radius: 6px;
            font-size: .75rem;
            font-weight: 600;
        }

        /* Sidebar badge contador */
        .nav-link .nav-badge {
            margin-left: auto;
            font-size: .65rem;
            padding: 2px 6px;
            border-radius: 10px;
            background: rgba(255,0,0,.7);
            color: #fff;
            font-weight: 700;
        }

        /* ── TABLE ── */
        .table-custom { font-size: .85rem; }
        .table-custom thead th {
            background: #f0f4f8; color: #4a5568;
            font-size: .75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .05em;
            border: none; padding: .75rem 1rem;
        }
        .table-custom tbody td { padding: .65rem 1rem; border-color: #f0f4f8; vertical-align: middle; }
        .table-custom tbody tr:hover { background: #f8faff; }

        /* ── CALENDAR GRID ── */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .calendar-cell {
            border-radius: 8px;
            padding: .4rem .3rem;
            text-align: center;
            font-size: .75rem;
        }
        .calendar-cell .day-num { font-weight: 700; font-size: .8rem; }
        .calendar-cell.has-turno { cursor: pointer; }

        /* ── ALERTS ── */
        .alert { border-radius: 10px; border: none; }

        /* ── PROGRESS ── */
        .progress { border-radius: 10px; height: 8px; }
        .progress-bar { border-radius: 10px; }

        /* ── SIDEBAR TOGGLE (mobile) ── */
        @media (max-width: 768px) {
            #sidebar { width: 0; overflow: hidden; }
            #sidebar.show { width: var(--sidebar-width); }
            #main-content { margin-left: 0; }
        }

        /* ── ANIMATIONS ── */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeInUp .4s ease forwards; }
        .fade-in-delay-1 { animation-delay: .1s; opacity: 0; }
        .fade-in-delay-2 { animation-delay: .2s; opacity: 0; }
        .fade-in-delay-3 { animation-delay: .3s; opacity: 0; }
    </style>
    @stack('styles')
    @stack('head-styles')
</head>
<body>

<!-- SIDEBAR -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-hospital"></i></div>
        <div>
            <div class="brand-text">Cuadro de Turnos</div>
            <div class="brand-sub">UCI · Gestión de Turnos</div>
        </div>
    </div>

    <div class="sidebar-nav">
        @auth
        @php $user = auth()->user(); @endphp

        @if($user->esMedico())
        {{-- ── MENÚ MÉDICO ───────────────────────────── --}}
        @php
            $pendientesMedico = 0;
            if ($user->medico_id) {
                $pendientesMedico = \App\Models\SolicitudCambioTurno::where('medico_receptor_id', $user->medico_id)
                    ->whereIn('estado',['pendiente','enviado_a_receptor'])->count();
            }
        @endphp
        <div class="sidebar-section">Mi Portal</div>
        <a class="nav-link {{ request()->routeIs('medico.*') ? 'active' : '' }}" href="{{ route('medico.portal') }}">
            <i class="bi bi-person-circle"></i> Mis Turnos
            @if($pendientesMedico > 0)
                <span class="nav-badge">{{ $pendientesMedico }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('turno-ahora.*') ? 'active' : '' }}" href="{{ route('turno-ahora.index') }}">
            <i class="bi bi-activity"></i> De Turno Ahora
        </a>

        @else
        {{-- ── MENÚ MAESTRO ──────────────────────────── --}}
        @php
            $alertasAbiertas  = \App\Models\AlertaTurno::where('estado','abierta')->count();
            $cambiosPendientes= \App\Models\SolicitudCambioTurno::pendientesParaMaestro()->count();
        @endphp

        <div class="sidebar-section">Principal</div>
        <a class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a class="nav-link {{ request()->routeIs('turno-ahora.*') ? 'active' : '' }}" href="{{ route('turno-ahora.index') }}">
            <i class="bi bi-activity"></i> De Turno Ahora
        </a>
        <a class="nav-link {{ request()->routeIs('calendario.*') ? 'active' : '' }}" href="{{ route('calendario.index') }}">
            <i class="bi bi-calendar-month"></i> Calendario Visual
        </a>

        <div class="sidebar-section">Cuadro de Turnos</div>
        <a class="nav-link {{ request()->routeIs('archivos.*') ? 'active' : '' }}" href="{{ route('archivos.index') }}">
            <i class="bi bi-cloud-upload"></i> Importar Excel
        </a>
        <a class="nav-link {{ request()->routeIs('secuencias.*') ? 'active' : '' }}" href="{{ route('secuencias.index') }}">
            <i class="bi bi-calendar-week"></i> Secuencias UCI
        </a>
        <a class="nav-link {{ request()->routeIs('turno-editor.*') ? 'active' : '' }}" href="{{ route('turno-editor.index') }}">
            <i class="bi bi-pencil-square"></i> Editor de Turnos
        </a>
        <a class="nav-link {{ request()->routeIs('planificacion.*') ? 'active' : '' }}" href="{{ route('planificacion.index') }}">
            <i class="bi bi-table"></i> Editar Planificación
        </a>

        <div class="sidebar-section">Personal</div>
        <a class="nav-link {{ request()->routeIs('medicos.*') ? 'active' : '' }}" href="{{ route('medicos.index') }}">
            <i class="bi bi-person-badge"></i> Médicos
        </a>
        <a class="nav-link {{ request()->routeIs('ucis.*') ? 'active' : '' }}" href="{{ route('ucis.index') }}">
            <i class="bi bi-building-fill-cross"></i> UCIs
        </a>

        <div class="sidebar-section">Gestión</div>
        <a class="nav-link {{ request()->routeIs('novedades.*') ? 'active' : '' }}" href="{{ route('novedades.index') }}">
            <i class="bi bi-clipboard2-pulse"></i> Novedades
        </a>
        <a class="nav-link {{ request()->routeIs('cambios-turno.*') ? 'active' : '' }}" href="{{ route('cambios-turno.index') }}">
            <i class="bi bi-arrow-left-right"></i> Cambios de Turno
            @if($cambiosPendientes > 0)
                <span class="nav-badge">{{ $cambiosPendientes }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('ausencias.*') ? 'active' : '' }}" href="{{ route('ausencias.index') }}">
            <i class="bi bi-calendar-x"></i> Ausencias / Permisos
        </a>

        <div class="sidebar-section">Control</div>
        <a class="nav-link {{ request()->routeIs('alertas.*') ? 'active' : '' }}" href="{{ route('alertas.index') }}">
            <i class="bi bi-exclamation-triangle"></i> Alertas
            @if($alertasAbiertas > 0)
                <span class="nav-badge">{{ $alertasAbiertas }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('consolidado.*') ? 'active' : '' }}" href="{{ route('consolidado.index') }}">
            <i class="bi bi-bar-chart-line"></i> Consolidado / Excel
        </a>
        <a class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Reportes
        </a>
        <a class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}" href="{{ route('configuracion.index') }}">
            <i class="bi bi-gear"></i> Configuración
        </a>
        <a class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">
            <i class="bi bi-people"></i> Usuarios Médicos
        </a>
        @endif
        @endauth
    </div>

    @auth
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div style="width:32px;height:32px;background:rgba(255,255,255,.15);border-radius:50%;display:flex;align-items:center;justify-content:center">
                <i class="bi bi-person-fill text-white" style="font-size:1rem"></i>
            </div>
            <div>
                <div style="color:#f1f5f9;font-size:11px;font-weight:600">{{ auth()->user()->name }}</div>
                <div style="color:#94a3b8;font-size:10px">
                    @php $r=auth()->user()->rol; @endphp
                    {{ $r==='master'?'Maestro':($r==='medico'?'Médico':'Visualizador') }}
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm w-100" style="background:rgba(255,255,255,.1);color:#94a3b8;font-size:11px">
                <i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión
            </button>
        </form>
    </div>
    @endauth
</nav>

<!-- MAIN CONTENT -->
<div id="main-content">
    <!-- TOP BAR -->
    <div class="topbar">
        <button class="btn btn-sm btn-light me-3 d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div>
            <div class="page-title">@yield('page-title', 'Dashboard')</div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Inicio</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary rounded-pill">
                <i class="bi bi-calendar3 me-1"></i>{{ now()->locale('es')->isoFormat('MMMM YYYY') }}
            </span>
            @auth
            @if(auth()->user()->esMaster())
            <span class="badge bg-danger-subtle text-danger rounded-pill">
                <i class="bi bi-shield-fill-check me-1"></i>Maestro
            </span>
            @elseif(auth()->user()->esMedico())
            <span class="badge bg-success-subtle text-success rounded-pill">
                <i class="bi bi-person-heart me-1"></i>Médico
            </span>
            @else
            <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                <i class="bi bi-eye me-1"></i>Visualizador
            </span>
            @endif
            @endauth
        </div>
    </div>

    <!-- ALERTS -->
    <div class="px-4 pt-3">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>{{ session('success') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div>{{ session('error') }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-warning shadow-sm">
                <i class="bi bi-exclamation-circle me-2"></i>
                <strong>Errores de validación:</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <!-- PAGE CONTENT -->
    <div class="content-area">
        @yield('content')
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.0/dist/apexcharts.min.js"></script>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}
// Auto-dismiss alerts after 6s
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert?.close();
    });
}, 6000);
</script>

@stack('scripts')
</body>
</html>
