@extends('layouts.app')

@section('title', 'Cuadro de Turnos')
@section('page-title', 'Cuadro de Turnos UCI')

@section('breadcrumb')
    <li class="breadcrumb-item active">Cuadro de Turnos</li>
@endsection

@push('styles')
<style>
/* ── Colores de turno ── */
.t-M   { background:#dbeafe; color:#1d4ed8; border:1px solid #bfdbfe; }
.t-T   { background:#fef9c3; color:#854d0e; border:1px solid #fde68a; }
.t-MT  { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
.t-N   { background:#ede9fe; color:#5b21b6; border:1px solid #ddd6fe; }
.t-MTN { background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-weight:700; }
.t-MN  { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
.t-VAC { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-style:italic; }
.t-PER { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-style:italic; }
.t-INC { background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-style:italic; }

.turno-badge {
    display:inline-block;
    padding:2px 7px;
    border-radius:6px;
    font-size:.78rem;
    font-weight:600;
    letter-spacing:.02em;
    white-space:nowrap;
}

/* ── Grid semanal ── */
.tabla-semana {
    width:100%;
    border-collapse:collapse;
    font-size:.82rem;
}
.tabla-semana th, .tabla-semana td {
    border:1px solid #e5e7eb;
    padding:5px 8px;
    text-align:center;
    vertical-align:middle;
}
.tabla-semana th {
    background:#f8fafc;
    font-weight:600;
    font-size:.78rem;
    color:#374151;
    white-space:nowrap;
}
.tabla-semana .col-medico {
    text-align:left;
    white-space:nowrap;
    font-weight:500;
    min-width:140px;
    max-width:180px;
    overflow:hidden;
    text-overflow:ellipsis;
    background:#fafafa;
    color:#1f2937;
}
.col-hoy    { background:#eff6ff !important; }
.col-finde  { background:#fafaf0 !important; }
.fila-alt   { background:#fafafa; }

/* ── UCI tabs ── */
.uci-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
.uci-tab {
    padding:6px 14px;
    border-radius:8px;
    border:2px solid #e5e7eb;
    background:#fff;
    cursor:pointer;
    font-size:.8rem;
    font-weight:600;
    color:#6b7280;
    transition:all .15s;
}
.uci-tab.active, .uci-tab:hover {
    border-color:#2563eb;
    color:#2563eb;
    background:#eff6ff;
}
.uci-panel { display:none; }
.uci-panel.active { display:block; }

/* ── Resumen hoy ── */
.card-uci-hoy {
    border:1px solid #e5e7eb;
    border-radius:10px;
    overflow:hidden;
    margin-bottom:10px;
}
.card-uci-hoy .header {
    background:#f1f5f9;
    padding:8px 14px;
    font-weight:700;
    font-size:.82rem;
    color:#1e40af;
    border-bottom:1px solid #e5e7eb;
    display:flex;
    align-items:center;
    gap:6px;
}
.card-uci-hoy .body {
    padding:10px 14px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    min-height:36px;
}
.medico-chip {
    display:flex;
    align-items:center;
    gap:5px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:20px;
    padding:3px 10px 3px 6px;
    font-size:.78rem;
}
.medico-chip.activo {
    border-color:#16a34a;
    background:#f0fdf4;
}
.dot { width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0; }
.dot-activo  { background:#16a34a; }
.dot-proximo { background:#f59e0b; }

/* ── Vista día tabs ── */
.dia-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:12px; }
.dia-tab {
    padding:5px 12px;
    border-radius:7px;
    border:1.5px solid #e5e7eb;
    background:#fff;
    cursor:pointer;
    font-size:.78rem;
    font-weight:600;
    color:#6b7280;
    transition:all .15s;
    text-align:center;
}
.dia-tab.hoy { border-color:#ef4444; color:#ef4444; }
.dia-tab.active { border-color:#2563eb; color:#2563eb; background:#eff6ff; }
.dia-panel { display:none; }
.dia-panel.active { display:block; }

.tabla-scroll { overflow-x:auto; }
.sin-datos { text-align:center; padding:40px 20px; color:#9ca3af; }
</style>
@endpush

@section('content')

{{-- ── HEADER: navegación de semana + turno activo ── --}}
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="?fecha={{ $semanaAnterior }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i>
        </a>
        <span class="fw-bold" style="font-size:1rem">
            {{ $inicioSemana->locale('es')->isoFormat('D MMM') }}
            –
            {{ $finSemana->locale('es')->isoFormat('D MMM YYYY') }}
        </span>
        <a href="?fecha={{ $semanaSiguiente }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-right"></i>
        </a>
        <a href="?" class="btn btn-sm btn-outline-primary">Hoy</a>
    </div>
    <div>
        <span class="badge bg-primary-subtle text-primary px-3 py-2" style="font-size:.82rem">
            <i class="bi bi-clock me-1"></i>{{ $ahora->format('H:i') }} · {{ $turnoActivo }}
        </span>
    </div>
</div>

@if(!$hayDatos)
<div class="sin-datos panel">
    <i class="bi bi-calendar-x" style="font-size:3rem;display:block;opacity:.2;margin-bottom:12px"></i>
    <div class="fw-semibold text-muted">No hay datos para esta semana</div>
    <div class="text-muted small mt-1">
        Cargue un archivo Excel desde <a href="{{ route('archivos.index') }}">Importar Excel</a>
    </div>
</div>
@else

{{-- ── PESTAÑAS PRINCIPALES ── --}}
<ul class="nav nav-tabs mb-3" id="mainTabs">
    <li class="nav-item">
        <button class="nav-link active" data-tab="tab-uci">
            <i class="bi bi-building me-1"></i>Por UCI
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="tab-dia">
            <i class="bi bi-calendar-day me-1"></i>Por Día
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="tab-ahora">
            <i class="bi bi-broadcast me-1"></i>Turno Activo
        </button>
    </li>
</ul>

{{-- ══════════════════════════════════════════════════
     TAB 1: Vista semanal por UCI
══════════════════════════════════════════════════ --}}
<div id="tab-uci" class="main-panel">

    <div class="uci-tabs" id="uciTabs">
        @foreach($ucis as $idx => $uci)
            @php $tieneData = !empty($datosUci[$uci->codigo]['medicos']); @endphp
            <button class="uci-tab {{ $idx === 0 ? 'active' : '' }} {{ !$tieneData ? 'opacity-50' : '' }}"
                    data-uci="{{ $uci->codigo }}">
                {{ $uci->nombre }}
                @if($tieneData)
                    <span class="badge bg-primary-subtle text-primary ms-1" style="font-size:.7rem">
                        {{ count($datosUci[$uci->codigo]['medicos']) }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach($ucis as $idx => $uci)
    @php
        $datosPanelUci = $datosUci[$uci->codigo] ?? [];
        $medicosUci    = $datosPanelUci['medicos'] ?? [];
        $horasMap      = ['M'=>6,'T'=>6,'MT'=>12,'N'=>12,'MTN'=>24,'MN'=>18];
    @endphp
    <div class="uci-panel panel {{ $idx === 0 ? 'active' : '' }}" id="uci-{{ $uci->codigo }}">

        @if(empty($medicosUci))
            <div class="sin-datos">
                <i class="bi bi-person-x" style="font-size:2rem;display:block;opacity:.2;margin-bottom:8px"></i>
                <div class="text-muted small">Sin datos para {{ $uci->nombre }} esta semana</div>
            </div>
        @else
            <div class="tabla-scroll">
                <table class="tabla-semana">
                    <thead>
                        <tr>
                            <th class="col-medico" style="text-align:left">Médico</th>
                            @foreach($diasSemana as $diaCol)
                            <th class="{{ $diaCol['es_hoy'] ? 'col-hoy' : ($diaCol['es_finde'] ? 'col-finde' : '') }}"
                                style="min-width:68px">
                                {{ $diaCol['label_corto'] }}
                                @if($diaCol['es_hoy'])
                                    <div style="font-size:.65rem;color:#2563eb;font-weight:700">HOY</div>
                                @endif
                            </th>
                            @endforeach
                            <th style="min-width:50px">Hrs</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($medicosUci as $nombreMed => $turnosSemana)
                        @php
                            $horasSemana = 0;
                            foreach ($turnosSemana as $codT) {
                                $horasSemana += $horasMap[$codT] ?? 0;
                            }
                        @endphp
                        <tr class="{{ $loop->even ? 'fila-alt' : '' }}">
                            <td class="col-medico" title="{{ $nombreMed }}">{{ $nombreMed }}</td>
                            @foreach($diasSemana as $diaCol)
                            @php $codCelda = $turnosSemana[$diaCol['fecha']] ?? ''; @endphp
                            <td class="{{ $diaCol['es_hoy'] ? 'col-hoy' : ($diaCol['es_finde'] ? 'col-finde' : '') }}">
                                @if($codCelda)
                                    <span class="turno-badge t-{{ $codCelda }}">{{ $codCelda }}</span>
                                @else
                                    <span style="color:#d1d5db;font-size:.7rem">—</span>
                                @endif
                            </td>
                            @endforeach
                            <td>
                                <span class="fw-semibold" style="font-size:.8rem;color:#374151">
                                    {{ $horasSemana }}h
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8fafc;font-weight:600;font-size:.78rem">
                            <td style="text-align:left;color:#374151">
                                <i class="bi bi-people me-1 text-primary"></i>
                                {{ count($medicosUci) }} médico(s)
                            </td>
                            @foreach($diasSemana as $diaCol)
                            @php
                                $nHoy = 0;
                                foreach($medicosUci as $tt) {
                                    if (!empty($tt[$diaCol['fecha']])) $nHoy++;
                                }
                            @endphp
                            <td class="{{ $diaCol['es_hoy'] ? 'col-hoy' : '' }}">
                                @if($nHoy > 0)
                                    <span style="color:#16a34a">{{ $nHoy }}</span>
                                @else
                                    <span style="color:#ef4444">0</span>
                                @endif
                            </td>
                            @endforeach
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
                @foreach(['M'=>'Mañana 6h','T'=>'Tarde 6h','MT'=>'Mañana-Tarde 12h','N'=>'Noche 12h','MTN'=>'24h','MN'=>'18h'] as $cod => $desc)
                    <span class="turno-badge t-{{ $cod }}">{{ $cod }}</span>
                    <span class="text-muted me-2" style="font-size:.72rem">{{ $desc }}</span>
                @endforeach
            </div>
        @endif
    </div>
    @endforeach
</div>

{{-- ══════════════════════════════════════════════════
     TAB 2: Vista por día
══════════════════════════════════════════════════ --}}
<div id="tab-dia" class="main-panel d-none">

    <div class="dia-tabs">
        @foreach($diasSemana as $didx => $diaTab)
        <button class="dia-tab {{ $diaTab['es_hoy'] ? 'hoy' : '' }} {{ $didx === 0 ? 'active' : '' }}"
                data-fecha="{{ $diaTab['fecha'] }}">
            <div>{{ ucfirst($diaTab['label_dia']) }}</div>
            <div style="font-size:1rem;font-weight:700">{{ $diaTab['numero'] }}</div>
        </button>
        @endforeach
    </div>

    @foreach($diasSemana as $didx => $diaTab)
    <div class="dia-panel {{ $didx === 0 ? 'active' : '' }}" id="dia-{{ $diaTab['fecha'] }}">
        <div class="row g-3">
            @foreach($ucis as $uciDia)
            @php
                $medicosEnDia = [];
                foreach (($datosUci[$uciDia->codigo]['medicos'] ?? []) as $nom => $dias) {
                    if (!empty($dias[$diaTab['fecha']])) {
                        $medicosEnDia[$nom] = $dias[$diaTab['fecha']];
                    }
                }
            @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card-uci-hoy">
                    <div class="header">
                        <i class="bi bi-hospital text-primary"></i>
                        {{ $uciDia->nombre }}
                        @if(!empty($medicosEnDia))
                            <span class="badge bg-success-subtle text-success ms-auto">
                                {{ count($medicosEnDia) }} de turno
                            </span>
                        @else
                            <span class="badge bg-danger-subtle text-danger ms-auto">Sin cobertura</span>
                        @endif
                    </div>
                    <div class="body">
                        @forelse($medicosEnDia as $nom => $cod)
                            <div class="medico-chip">
                                <span class="turno-badge t-{{ $cod }}">{{ $cod }}</span>
                                <span>{{ $nom }}</span>
                            </div>
                        @empty
                            <span class="text-muted small">Sin turnos asignados</span>
                        @endforelse
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>

{{-- ══════════════════════════════════════════════════
     TAB 3: Turno activo ahora
══════════════════════════════════════════════════ --}}
<div id="tab-ahora" class="main-panel d-none">
    <div class="alert alert-primary py-2 mb-3 small">
        <i class="bi bi-clock me-1"></i>
        <strong>{{ $ahora->format('H:i') }}</strong> —
        Turno activo: <strong>{{ $turnoActivo }}</strong> ·
        {{ $ahora->locale('es')->isoFormat('dddd D [de] MMMM') }}
    </div>
    <div class="row g-3">
        @foreach($ucis as $uciAhora)
        @php $rhUci = $resumenHoy[$uciAhora->codigo] ?? []; @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card-uci-hoy">
                <div class="header">
                    <i class="bi bi-hospital text-primary"></i>
                    {{ $uciAhora->nombre }}
                    @if(!empty($rhUci['activos']))
                        <span class="badge bg-success-subtle text-success ms-auto">
                            {{ count($rhUci['activos']) }} activo(s)
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning ms-auto">Sin turno activo</span>
                    @endif
                </div>
                <div class="body flex-column" style="gap:4px">
                    @forelse($rhUci['activos'] ?? [] as $tActivo)
                    <div class="medico-chip activo">
                        <span class="dot dot-activo"></span>
                        <span class="turno-badge t-{{ $tActivo['codigo'] }}">{{ $tActivo['codigo'] }}</span>
                        <span>{{ $tActivo['medico'] }}</span>
                        <span class="text-muted" style="font-size:.7rem">{{ $tActivo['label'] }}</span>
                    </div>
                    @empty
                    @endforelse

                    @foreach($rhUci['proximos'] ?? [] as $tProx)
                    <div class="medico-chip" style="opacity:.65">
                        <span class="dot dot-proximo"></span>
                        <span class="turno-badge t-{{ $tProx['codigo'] }}">{{ $tProx['codigo'] }}</span>
                        <span>{{ $tProx['medico'] }}</span>
                        <span class="text-muted" style="font-size:.7rem">próximo</span>
                    </div>
                    @endforeach

                    @if(empty($rhUci['activos']) && empty($rhUci['proximos']))
                        <span class="text-muted small">Sin médicos asignados hoy</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

@endif
@endsection

@push('scripts')
<script>
// Tabs principales
document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.main-panel').forEach(p => p.classList.add('d-none'));
        btn.classList.add('active');
        document.getElementById(btn.dataset.tab).classList.remove('d-none');
    });
});

// Tabs UCI
document.querySelectorAll('.uci-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.uci-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.uci-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.getElementById('uci-' + tab.dataset.uci);
        if (panel) panel.classList.add('active');
    });
});

// Tabs día
document.querySelectorAll('.dia-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.dia-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.dia-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.getElementById('dia-' + tab.dataset.fecha);
        if (panel) panel.classList.add('active');
    });
});

// Abrir la UCI con más datos al cargar
const primerConDatos = document.querySelector('.uci-tab:not(.opacity-50)');
if (primerConDatos) primerConDatos.click();

// Abrir día de hoy en vista por día
const tabHoy = document.querySelector('.dia-tab.hoy');
if (tabHoy) tabHoy.click();
</script>
@endpush
