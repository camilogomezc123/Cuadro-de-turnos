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
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            transition: width .25s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
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

        /* ── SIDEBAR DRAWER (mobile) ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .5);
            z-index: 999;
            opacity: 0;
            transition: opacity .25s ease;
        }
        @media (max-width: 991.98px) {
            #sidebar {
                width: var(--sidebar-width);
                max-width: 82vw;
                transform: translateX(-100%);
                transition: transform .25s ease;
            }
            #sidebar.show { transform: translateX(0); width: var(--sidebar-width); }
            #main-content { margin-left: 0; }
            .sidebar-overlay.show { display: block; opacity: 1; }
            body.sidebar-open { overflow: hidden; }
        }

        /* ── RESPONSIVE TWEAKS (mobile) ── */
        @media (max-width: 575.98px) {
            .topbar { padding: .5rem .75rem; height: auto; min-height: 56px; gap: .4rem; flex-wrap: wrap; }
            .topbar .page-title { font-size: .9rem; }
            .topbar .breadcrumb { display: none; }
            .topbar .ms-auto { gap: .35rem !important; }
            .topbar .badge { font-size: .65rem; padding: .3rem .5rem; }
            .content-area { padding: .85rem; }
            .kpi-card { padding: 1rem; }
            .kpi-card .kpi-value { font-size: 1.4rem; }
            .kpi-card .kpi-icon { width: 40px; height: 40px; font-size: 1.1rem; }
            .panel-header { padding: .75rem .9rem; flex-wrap: wrap; gap: .5rem; }
            .panel-body { padding: .9rem; }
            h1 { font-size: 1.3rem; }
            h2 { font-size: 1.15rem; }
            h3, h4 { font-size: 1.05rem; }
            .btn { font-size: .85rem; }
            .table-custom { font-size: .78rem; }
            .table-custom thead th { padding: .55rem .6rem; }
            .table-custom tbody td { padding: .5rem .6rem; }
            .modal-dialog { margin: .5rem; }
        }

        /* Cualquier fila de botones/acciones se apila en pantallas chicas */
        @media (max-width: 575.98px) {
            .panel-header .d-flex,
            .topbar .ms-auto,
            .d-flex.flex-wrap { flex-wrap: wrap; }
            .panel-header > .d-flex { width: 100%; }
        }

        /* Tablas: aunque una vista olvide envolverla en .table-responsive,
           esto fuerza scroll horizontal en vez de romper el layout. */
        .table-responsive { -webkit-overflow-scrolling: touch; }
        @media (max-width: 767.98px) {
            .content-area table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .content-area table thead,
            .content-area table tbody,
            .content-area table tr {
                display: revert;
            }
        }

        /* Calendario: en pantallas muy chicas se compacta en vez de desbordar */
        @media (max-width: 575.98px) {
            .calendar-grid { gap: 2px; }
            .calendar-cell { padding: .25rem .15rem; font-size: .65rem; border-radius: 6px; }
            .calendar-cell .day-num { font-size: .7rem; }
        }

        /* Formularios: que los grupos en línea se apilen en vez de comprimirse */
        @media (max-width: 575.98px) {
            .row > [class*="col-"] { margin-bottom: .5rem; }
        }

        /* Pestañas (nav-tabs): en vez de desbordar o partirse, se desliza */
        @media (max-width: 767.98px) {
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
            }
            .nav-tabs .nav-item { flex-shrink: 0; }
            .nav-tabs .nav-link { padding: .5rem .75rem; font-size: .85rem; }
        }

        /* Modales: ocupan casi todo el ancho y permiten alto scrollable en móvil */
        @media (max-width: 575.98px) {
            .modal-dialog:not(.modal-fullscreen) { max-width: calc(100% - 1rem); }
            .modal-content { border-radius: 14px; }
            .modal-body { max-height: 70vh; overflow-y: auto; }
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
    <style>
    @media print {
        .sidebar, .topbar, .d-print-none, .nav, .btn, .alert, .breadcrumb,
        .sidebar-overlay, form.d-print-none { display:none !important; }
        .main-content { margin-left:0 !important; padding:0 !important; }
        .content-area { padding:0 !important; }
        .panel { box-shadow:none !important; border:1px solid #ccc !important; page-break-inside:avoid; }
        body { background:#fff !important; font-size:11px; }
        .table { font-size:11px; }
        h4, h5, h6 { font-size:13px; }
    }
    </style>
</head>
<body>

<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- SIDEBAR -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-hospital"></i></div>
        <div>
            <div class="brand-text">Cuadro de Turnos</div>
            <div class="brand-sub">UCI · Gestión de Turnos</div>
        </div>
        <button type="button" class="btn-close btn-close-white ms-auto d-lg-none" aria-label="Cerrar menú" onclick="toggleSidebar()"></button>
    </div>

    <div class="sidebar-nav">
        @auth
        @php $user = auth()->user(); @endphp

        @if($user->esMedico())
        {{-- ── MENÚ MÉDICO ───────────────────────────── --}}
        @php
            $pendientesMedico = 0;
            if ($user->medico_id) {
                $cacheKey = 'sidebar_medico_pendientes_' . $user->medico_id;
                $pendientesMedico = \Illuminate\Support\Facades\Cache::remember($cacheKey, 120, fn() =>
                    \App\Models\SolicitudCambioTurno::where('medico_receptor_id', $user->medico_id)
                        ->whereIn('estado',['pendiente','enviado_a_receptor'])->count()
                );
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
        <a class="nav-link {{ request()->routeIs('novedades.*') ? 'active' : '' }}" href="{{ route('novedades.index') }}">
            <i class="bi bi-clipboard2-pulse"></i> Mis Novedades
        </a>
        <a class="nav-link {{ request()->routeIs('mi-turno.*') ? 'active' : '' }}" href="{{ route('mi-turno.index') }}">
            <i class="bi bi-calendar-check"></i> Mi Turno del Mes
        </a>
        <a class="nav-link {{ request()->routeIs('cambios-turno.*') ? 'active' : '' }}" href="{{ route('cambios-turno.index') }}">
            <i class="bi bi-arrow-left-right"></i> Mis Cambios de Turno
        </a>

        @else
        {{-- ── MENÚ MAESTRO ──────────────────────────── --}}
        @php
            try {
                $alertasAbiertas   = \Illuminate\Support\Facades\Cache::remember('sidebar_alertas_abiertas', 120, fn() => \App\Models\AlertaTurno::where('estado','abierta')->count());
                $cambiosPendientes = \Illuminate\Support\Facades\Cache::remember('sidebar_cambios_pendientes', 120, fn() => \App\Models\SolicitudCambioTurno::pendientesParaMaestro()->count());
            } catch (\Throwable $e) {
                $alertasAbiertas   = 0;
                $cambiosPendientes = 0;
            }
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
        <a class="nav-link {{ request()->routeIs('secuencias.index') ? 'active' : '' }}" href="{{ route('secuencias.index') }}">
            <i class="bi bi-calendar-week"></i> Secuencias UCI
        </a>
        <a class="nav-link {{ request()->routeIs('secuencias.cargar-excel') ? 'active' : '' }}" href="{{ route('secuencias.cargar-excel') }}">
            <i class="bi bi-file-earmark-arrow-up"></i> Cargar Secuencia Excel
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

        <div class="sidebar-section">Bienestar</div>
        @php
            try { $alertasBurnout = \App\Models\BurnoutAlerta::where('estado','activa')->count(); }
            catch (\Throwable $e) { $alertasBurnout = 0; }
        @endphp
        <a class="nav-link {{ request()->routeIs('burnout.*') ? 'active' : '' }}" href="{{ route('burnout.index') }}">
            <i class="bi bi-heart-pulse"></i> Burnout
            @if($alertasBurnout > 0)
                <span class="nav-badge">{{ $alertasBurnout }}</span>
            @endif
        </a>

        <div class="sidebar-section">Control</div>
        <a class="nav-link {{ request()->routeIs('alertas.*') ? 'active' : '' }}" href="{{ route('alertas.index') }}">
            <i class="bi bi-exclamation-triangle"></i> Alertas
            @if($alertasAbiertas > 0)
                <span class="nav-badge">{{ $alertasAbiertas }}</span>
            @endif
        </a>
        <a class="nav-link {{ request()->routeIs('consolidado.index') || request()->routeIs('consolidado.excel') || request()->routeIs('consolidado.cuadro-excel') ? 'active' : '' }}" href="{{ route('consolidado.index') }}">
            <i class="bi bi-bar-chart-line"></i> Consolidado mensual
        </a>
        <a class="nav-link {{ request()->routeIs('consolidado.anual') ? 'active' : '' }}" href="{{ route('consolidado.anual') }}">
            <i class="bi bi-calendar-range"></i> Consolidado anual
        </a>
        <a class="nav-link {{ request()->routeIs('reportes.*') ? 'active' : '' }}" href="{{ route('reportes.index') }}">
            <i class="bi bi-file-earmark-bar-graph"></i> Reportes
        </a>
        <a class="nav-link {{ request()->routeIs('historial.*') ? 'active' : '' }}" href="{{ route('historial.index') }}">
            <i class="bi bi-clock-history"></i> Historial de Ediciones
        </a>
        <a class="nav-link {{ request()->routeIs('configuracion.*') ? 'active' : '' }}" href="{{ route('configuracion.index') }}">
            <i class="bi bi-gear"></i> Configuración
        </a>
        <a class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">
            <i class="bi bi-people"></i> Usuarios Médicos
        </a>
        @php
            try {
                $nDup = cache()->remember('medicos_dup_count', 120, fn() =>
                    count(\Illuminate\Support\Facades\DB::select("
                        SELECT 1 FROM medicos
                        GROUP BY LOWER(TRIM(CONCAT_WS(' ', nombre, NULLIF(TRIM(IFNULL(apellido,'')),''))))
                        HAVING COUNT(*) > 1
                    "))
                );
            } catch (\Throwable) { $nDup = 0; }
        @endphp
        <a class="nav-link {{ request()->routeIs('medicos.duplicados.*') ? 'active' : '' }}" href="{{ route('medicos.duplicados.index') }}">
            <i class="bi bi-person-exclamation {{ $nDup>0 ? 'text-warning' : '' }}"></i> Médicos Duplicados
            @if($nDup > 0)<span class="badge bg-warning text-dark ms-1">{{ $nDup }}</span>@endif
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
        <button class="btn btn-sm btn-light me-3 d-lg-none" onclick="toggleSidebar()" aria-label="Abrir menú">
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

    <!-- ALERTAS GLOBALES MAESTRO -->
    @auth
    @if(auth()->user()->esMaster())
    @php
        try {
            $totalAlertasAbiertas  = \Illuminate\Support\Facades\Cache::remember('sidebar_alertas_abiertas', 120, fn() => \App\Models\AlertaTurno::where('estado','abierta')->count());
            $alertasBurnoutActivas = \Illuminate\Support\Facades\Cache::remember('sidebar_burnout_criticas', 120, fn() => \App\Models\BurnoutAlerta::where('estado','activa')->where('nivel_riesgo','critico')->count());
            $cambiosPend           = \Illuminate\Support\Facades\Cache::remember('sidebar_cambios_pendientes', 120, fn() => \App\Models\SolicitudCambioTurno::pendientesParaMaestro()->count());
        } catch (\Throwable $e) {
            $totalAlertasAbiertas  = 0;
            $alertasBurnoutActivas = 0;
            $cambiosPend           = 0;
        }
    @endphp
    @if($totalAlertasAbiertas > 0 || $alertasBurnoutActivas > 0 || $cambiosPend > 0)
    <div class="px-4 pb-0 pt-1">
        <div class="alert alert-warning border-warning d-flex gap-3 align-items-center py-2 mb-2" style="border-left:4px solid #f59e0b !important">
            <i class="bi bi-bell-fill text-warning fs-5 flex-shrink-0"></i>
            <div class="d-flex gap-3 flex-wrap align-items-center flex-fill">
                @if($totalAlertasAbiertas > 0)
                <a href="{{ route('alertas.index') }}" class="text-decoration-none">
                    <span class="badge bg-danger me-1">{{ $totalAlertasAbiertas }}</span>
                    <span class="text-dark small">alerta(s) de turno abiertas</span>
                </a>
                @endif
                @if($cambiosPend > 0)
                <a href="{{ route('cambios-turno.index') }}" class="text-decoration-none">
                    <span class="badge bg-warning text-dark me-1">{{ $cambiosPend }}</span>
                    <span class="text-dark small">cambio(s) de turno pendiente(s)</span>
                </a>
                @endif
                @if($alertasBurnoutActivas > 0)
                <a href="{{ route('burnout.index') }}" class="text-decoration-none">
                    <span class="badge bg-danger me-1">{{ $alertasBurnoutActivas }}</span>
                    <span class="text-dark small">alerta(s) críticas de burnout</span>
                </a>
                @endif
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif
    @endif
    @endauth

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
function toggleSidebar(forceClose = false) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const opening = forceClose ? false : !sidebar.classList.contains('show');

    sidebar.classList.toggle('show', opening);
    overlay.classList.toggle('show', opening);
    document.body.classList.toggle('sidebar-open', opening);
}

// Cerrar el menú al tocar un enlace (mejor respuesta táctil mientras navega)
document.querySelectorAll('#sidebar .nav-link').forEach(link => {
    link.addEventListener('click', () => toggleSidebar(true));
});

// Cerrar con la tecla Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') toggleSidebar(true);
});

// Auto-dismiss alerts after 6s
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert?.close();
    });
}, 6000);
</script>

@stack('scripts')

@auth
@if(session('consentimiento_pendiente') && !auth()->user()->esMaster())
{{-- ── Modal de consentimiento — solo usuarios no-master ── --}}
<div class="modal fade" id="modalConsentimiento" tabindex="-1"
     data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="lblConsentimiento">
    <div class="modal-dialog modal-dialog-centered" style="max-width:540px">
        <div class="modal-content border-0 shadow-lg">

            {{-- Header --}}
            <div class="modal-header border-0 pb-2 px-4 pt-4"
                 style="background:linear-gradient(135deg,#1a2340,#2d3f6b);border-radius:.5rem .5rem 0 0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check fs-5 text-white"></i>
                    <h5 class="modal-title text-white mb-0 fw-semibold" id="lblConsentimiento" style="font-size:.95rem">
                        Aviso de uso de la plataforma
                    </h5>
                </div>
            </div>

            {{-- Body --}}
            <div class="modal-body px-4 py-3">
                {{-- Texto principal --}}
                <div class="rounded-3 p-3 mb-3" style="background:#f0f4ff;border-left:4px solid #6366f1">
                    <p class="mb-2" style="line-height:1.75;font-size:.9rem">
                        Confirmo que los turnos visibles en esta aplicación se organizan con base en la disponibilidad que he reportado libremente.
                    </p>
                    <p class="mb-0" style="line-height:1.75;font-size:.9rem">
                        Esta herramienta tiene como finalidad facilitar la planeación de la agenda asistencial, respetando la autonomía profesional y la posibilidad de actualizar la disponibilidad por los canales definidos.
                    </p>
                </div>

                {{-- Panel de rechazo (oculto por defecto) --}}
                <div id="panelRechazo" style="display:none">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="bi bi-chat-left-text me-1 text-danger"></i>¿Por qué rechazas? <span class="text-muted fw-normal">(opcional)</span>
                    </label>
                    <textarea id="motivoRechazo" class="form-control form-control-sm"
                              rows="3" maxlength="1000"
                              placeholder="Escribe aquí el motivo de tu rechazo…"
                              style="resize:none;font-size:.85rem"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 px-4 pb-4 pt-0 flex-column gap-2">
                {{-- Botones normales --}}
                <div id="botonesNormales" class="w-100 d-flex gap-2">
                    <button type="button"
                            class="btn btn-primary flex-fill fw-semibold py-2"
                            onclick="enviarConsentimiento('aceptar')">
                        <i class="bi bi-check2-circle me-1"></i>Acepto y continúo
                    </button>
                    <button type="button"
                            class="btn btn-outline-danger fw-semibold px-3 py-2"
                            onclick="mostrarPanelRechazo()">
                        <i class="bi bi-x-circle me-1"></i>Rechazar
                    </button>
                </div>
                {{-- Botones de rechazo --}}
                <div id="botonesRechazo" class="w-100 d-flex gap-2" style="display:none !important">
                    <button type="button"
                            class="btn btn-outline-secondary py-2 px-3"
                            onclick="ocultarPanelRechazo()">
                        <i class="bi bi-arrow-left me-1"></i>Volver
                    </button>
                    <button type="button"
                            class="btn btn-danger flex-fill fw-semibold py-2"
                            onclick="enviarConsentimiento('rechazar')">
                        <i class="bi bi-x-circle me-1"></i>Confirmar rechazo
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
(function() {
    const modal = new bootstrap.Modal(document.getElementById('modalConsentimiento'));
    modal.show();
})();

function mostrarPanelRechazo() {
    document.getElementById('panelRechazo').style.display = '';
    document.getElementById('botonesNormales').style.display = 'none';
    document.getElementById('botonesRechazo').style.removeProperty('display');
    document.getElementById('motivoRechazo').focus();
}

function ocultarPanelRechazo() {
    document.getElementById('panelRechazo').style.display = 'none';
    document.getElementById('botonesRechazo').style.display = 'none';
    document.getElementById('botonesNormales').style.removeProperty('display');
}

async function enviarConsentimiento(accion) {
    const btn = event.currentTarget;
    btn.disabled = true;

    const url   = accion === 'aceptar'
        ? '{{ route('consentimiento.aceptar') }}'
        : '{{ route('consentimiento.rechazar') }}';

    const body  = accion === 'rechazar'
        ? JSON.stringify({ motivo: document.getElementById('motivoRechazo').value })
        : null;

    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body,
        });
        const data = await resp.json();

        if (data.redirect) {
            window.location.href = data.redirect;
            return;
        }
    } catch(e) { /* continuar igual */ }

    bootstrap.Modal.getInstance(document.getElementById('modalConsentimiento')).hide();
}
</script>
@endif
@endauth

</body>
</html>
