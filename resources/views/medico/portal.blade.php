@extends('layouts.app')

@section('title', 'Mi Portal — ' . $medico->nombre_completo)
@section('page-title', 'Mi Portal Médico')
@section('breadcrumb')
    <li class="breadcrumb-item active">Mi Portal</li>
@endsection

@push('styles')
<style>
.t-M{background:#BBDEFB;color:#1565C0}.t-T{background:#C8E6C9;color:#2E7D32}
.t-MT{background:#FFE0B2;color:#E65100}.t-N{background:#D1C4E9;color:#4527A0}
.t-MTN{background:#F48FB1;color:#880E4F}.t-MN{background:#FCE4EC;color:#880E4F}
.t-PER{background:#FFFDE7;color:#F57F17}.t-INC{background:#FFEBEE;color:#C62828}
.shift-badge{display:inline-block;padding:.2rem .55rem;border-radius:6px;font-size:.75rem;font-weight:700}
.cal-cell{border:1px solid #e8eef7;border-radius:8px;padding:.35rem .25rem;text-align:center;min-height:58px;font-size:.7rem}
.cal-cell.weekend{background:#fafbff}.cal-cell.today{border-color:#2196F3;background:#e3f2fd}
.cal-cell .day-num{font-weight:700;font-size:.8rem;display:block;margin-bottom:1px}
.cal-cell .dia-let{font-size:.6rem;color:#9aa5c0;display:block}
</style>
@endpush

@section('content')
<div class="fade-in">

{{-- ══════ PANEL DE ALERTAS PRIORITARIAS ══════ --}}
@php
    $tieneAlertasCriticas = $supera200 || $alertas12h->isNotEmpty() || $pendientesRecibidas > 0;
@endphp
@if($tieneAlertasCriticas)
<div class="card border-0 mb-4" style="border-left:5px solid #dc3545 !important;box-shadow:0 4px 20px rgba(220,53,69,.2)">
    <div class="card-header border-0 py-2" style="background:linear-gradient(90deg,#fff5f5,#fff);border-left:5px solid #dc3545">
        <span class="fw-bold text-danger"><i class="bi bi-bell-fill me-2"></i>Notificaciones pendientes — requieren su atención</span>
    </div>
    <div class="card-body py-2 px-3">

        @if($supera200)
        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:38px;height:38px;background:#fee2e2">
                <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
            </div>
            <div>
                <div class="fw-semibold text-danger">Exceso de horas mensuales</div>
                <div class="text-muted small">Tiene <strong>{{ number_format($resumen['horas_reconocidas'],1) }}h</strong> programadas — supera el límite de 200h. Coordine con el administrador.</div>
            </div>
        </div>
        @endif

        @foreach($alertas12h as $al)
        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:38px;height:38px;background:#fff3cd">
                <i class="bi bi-clock-history text-warning fs-5"></i>
            </div>
            <div class="fw-semibold small">{{ $al->mensaje_medico ?? $al->mensaje }}</div>
        </div>
        @endforeach

        @if($pendientesRecibidas > 0)
        <div class="d-flex align-items-center gap-3 py-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:38px;height:38px;background:#dbeafe">
                <i class="bi bi-arrow-left-right text-primary fs-5"></i>
            </div>
            <div>
                <div class="fw-semibold">Solicitudes de cambio pendientes</div>
                <div class="text-muted small">
                    Tiene <strong>{{ $pendientesRecibidas }}</strong> solicitud(es) esperando su respuesta.
                    <a href="#tab-recibidas" class="ms-1">Ver solicitudes →</a>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>
@endif

{{-- Encabezado --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h4 class="mb-0 fw-bold" style="color:#1a2340">
            <i class="bi bi-person-circle text-primary me-2"></i>{{ $medico->nombre_completo }}
        </h4>
        <small class="text-muted">{{ $medico->uci?->nombre ?? 'Varias UCIs' }}</small>
    </div>
    <form class="d-flex gap-2 align-items-center">
        <select name="mes" class="form-select form-select-sm" style="width:130px">
            @foreach($meses as $i=>$m)
                <option value="{{ $i+1 }}" @selected($mes==$i+1)>{{ $m }}</option>
            @endforeach
        </select>
        <select name="anio" class="form-select form-select-sm" style="width:85px">
            @foreach($mesesDisponibles->pluck('anio')->unique()->sortDesc() as $y)
                <option value="{{ $y }}" @selected($anio==$y)>{{ $y }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-primary">Ver</button>
    </form>
</div>

{{-- KPIs --}}
@php $clCarga = $resumen['estado_carga']==='exceso'?'danger':($resumen['estado_carga']==='bajo'?'warning':'success'); @endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#dbeafe"><i class="bi bi-clock text-primary fs-4"></i></div>
            <div class="kpi-value">{{ number_format($resumen['horas_reconocidas'],1) }}</div>
            <div class="kpi-label">Horas del mes</div>
            <span class="badge bg-{{ $clCarga }}-subtle text-{{ $clCarga }}">{{ ucfirst($resumen['estado_carga']) }}</span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#dcfce7"><i class="bi bi-sun text-success fs-4"></i></div>
            <div class="kpi-value">{{ number_format($resumen['horas_diurnas'],1) }}</div>
            <div class="kpi-label">Horas diurnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#ede9fe"><i class="bi bi-moon-stars fs-4" style="color:#7c3aed"></i></div>
            <div class="kpi-value">{{ number_format($resumen['horas_nocturnas'],1) }}</div>
            <div class="kpi-label">Horas nocturnas</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:#fef9c3"><i class="bi bi-calendar-week text-warning fs-4"></i></div>
            <div class="kpi-value">{{ $resumen['total_fines_semana'] }}</div>
            <div class="kpi-label">Fines de semana</div>
        </div>
    </div>
</div>

{{-- Barra 200h --}}
@php $pct = min(100, ($resumen['horas_reconocidas']/200)*100); @endphp
<div class="panel mb-4">
    <div class="panel-body py-3">
        <div class="d-flex justify-content-between mb-1">
            <small class="fw-bold text-muted">Progreso mensual de horas</small>
            <small class="fw-bold {{ $supera200?'text-danger':'text-success' }}">
                {{ number_format($resumen['horas_reconocidas'],1) }} / 200h
            </small>
        </div>
        <div class="progress mb-2"><div class="progress-bar bg-{{ $supera200?'danger':($pct>75?'warning':'success') }}" style="width:{{ $pct }}%"></div></div>
        <div class="d-flex gap-3 flex-wrap">
            @foreach(['M','T','MT','N','MTN'] as $c)
                <small><span class="shift-badge t-{{ $c }}">{{ $c }}</span> <strong>{{ $resumen['turnos_'.$c] }}</strong></small>
            @endforeach
        </div>
    </div>
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-3" id="portalTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-cal"><i class="bi bi-calendar3 me-1"></i>Mis Turnos</a></li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-disponibles">
            <i class="bi bi-gift me-1"></i>Disponibles
            @if($turnosDisponibles->count()>0)<span class="badge bg-info ms-1">{{ $turnosDisponibles->count() }}</span>@endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-recibidas">
            <i class="bi bi-inbox me-1"></i>Recibidas
            @if($pendientesRecibidas>0)<span class="badge bg-danger ms-1">{{ $pendientesRecibidas }}</span>@endif
        </a>
    </li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-enviadas"><i class="bi bi-send me-1"></i>Enviadas</a></li>
    @if($misNovedades->isNotEmpty())
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-novedades"><i class="bi bi-clipboard2 me-1"></i>Novedades</a></li>
    @endif
</ul>

<div class="tab-content">

{{-- Calendario --}}
<div class="tab-pane fade show active" id="tab-cal">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">{{ $meses[$mes-1] }} {{ $anio }}</span>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalCambio">
                <i class="bi bi-arrow-left-right me-1"></i>Solicitar cambio / donar
            </button>
        </div>
        <div class="panel-body">
            <div class="row g-1 mb-2 text-center">
                @foreach(['Lu','Ma','Mi','Ju','Vi','Sá','Do'] as $d)
                    <div class="col"><small class="fw-bold text-muted">{{ $d }}</small></div>
                @endforeach
            </div>
            @php
                $primerDia = \Carbon\Carbon::create($anio,$mes,1)->dayOfWeek;
                $offsetDias = $primerDia === 0 ? 6 : $primerDia - 1;
            @endphp
            <div class="row g-1">
                @for($i=0;$i<$offsetDias;$i++)
                    <div class="col"><div style="min-height:58px"></div></div>
                @endfor
                @foreach($calendario as $dNum => $info)
                @php
                    $dow    = $info['dia_sem'];
                    $esFin  = in_array($dow,[0,6]);
                    $esHoy  = \Carbon\Carbon::parse($info['fecha'])->isToday();
                    $turno  = $info['turno'];
                    $codigo = $turno?->codigo_turno ?? '';
                @endphp
                <div class="col">
                    <div class="cal-cell {{ $esFin?'weekend':'' }} {{ $esHoy?'today':'' }}">
                        <span class="dia-let">{{ ['Do','Lu','Ma','Mi','Ju','Vi','Sá'][$dow] }}</span>
                        <span class="day-num">{{ $dNum }}</span>
                        @if($codigo)
                            <span class="shift-badge t-{{ $codigo }} d-block mt-1" style="font-size:.65rem">{{ $codigo }}</span>
                            @if($turno && $turno->esTurnoActivo() && !\Carbon\Carbon::parse($info['fecha'])->isPast())
                                <button class="btn btn-link p-0 mt-1" style="font-size:.58rem;color:#f59e0b"
                                        onclick="abrirOfrecer({{ $turno->id }},'{{ $info['fecha'] }}','{{ $codigo }}')">
                                    Ofrecer
                                </button>
                            @endif
                        @else
                            <span class="text-muted" style="font-size:.6rem">libre</span>
                        @endif
                        @if($turno?->uci)
                            <span class="d-block" style="font-size:.55rem;color:#9aa5c0">{{ $turno->uci->codigo }}</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Disponibles --}}
<div class="tab-pane fade" id="tab-disponibles">
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Turnos disponibles</span></div>
        <div class="panel-body">
            @if($turnosDisponibles->isEmpty())
                <p class="text-center text-muted py-3">No hay turnos ofrecidos disponibles para usted.</p>
            @else
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead><tr><th>Fecha</th><th>UCI</th><th>Turno</th><th>Ofrece</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        @foreach($turnosDisponibles as $sol)
                        @php $t = $sol->turnoOrigen; @endphp
                        <tr>
                            <td>{{ $t?->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $t?->uci?->nombre ?? '—' }}</td>
                            <td><span class="shift-badge t-{{ $t?->codigo_turno }}">{{ $t?->codigo_turno }}</span></td>
                            <td>{{ $sol->medicoSolicitante?->nombre_completo }}</td>
                            <td><small class="text-muted">{{ $sol->motivo ?? '—' }}</small></td>
                            <td>
                                <form method="POST" action="{{ route('medico.aceptar-oferta', $sol) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Aceptar</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Recibidas --}}
<div class="tab-pane fade" id="tab-recibidas">
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Solicitudes pendientes de responder</span></div>
        <div class="panel-body">
            @if($solicitudesRecibidas->isEmpty())
                <p class="text-center text-muted py-3">No tiene solicitudes pendientes.</p>
            @else
                @foreach($solicitudesRecibidas as $sol)
                <div class="card mb-3 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <span class="badge bg-warning text-dark">{{ $sol->label_tipo }}</span>
                                <strong class="ms-2">{{ $sol->medicoSolicitante?->nombre_completo }}</strong>
                            </div>
                            <small class="text-muted">{{ $sol->created_at->format('d/m/Y H:i') }}</small>
                        </div>
                        <p class="mb-1">
                            Su turno: <strong>{{ $sol->turnoOrigen?->fecha?->format('d/m/Y') }}
                            <span class="shift-badge t-{{ $sol->turnoOrigen?->codigo_turno }}">{{ $sol->turnoOrigen?->codigo_turno }}</span></strong>
                            ({{ $sol->turnoOrigen?->uci?->nombre }})
                        </p>
                        @if($sol->motivo)<p class="text-muted small mb-2">Motivo: {{ $sol->motivo }}</p>@endif
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('medico.responder-cambio', $sol) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="accion" value="aceptar">
                                <button class="btn btn-sm btn-success"><i class="bi bi-check2 me-1"></i>Aceptar</button>
                            </form>
                            <form method="POST" action="{{ route('medico.responder-cambio', $sol) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="accion" value="rechazar">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x me-1"></i>Rechazar</button>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

{{-- Enviadas --}}
<div class="tab-pane fade" id="tab-enviadas">
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Mis solicitudes enviadas</span></div>
        <div class="panel-body">
            @if($solicitudesEnviadas->isEmpty())
                <p class="text-center text-muted py-3">No ha enviado solicitudes.</p>
            @else
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead><tr><th>Tipo</th><th>Mi turno</th><th>Receptor</th><th>Estado</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                        @foreach($solicitudesEnviadas as $sol)
                        <tr>
                            <td><span class="badge bg-secondary">{{ $sol->label_tipo }}</span></td>
                            <td>
                                {{ $sol->turnoOrigen?->fecha?->format('d/m') ?? '—' }}
                                <span class="shift-badge t-{{ $sol->turnoOrigen?->codigo_turno }}">{{ $sol->turnoOrigen?->codigo_turno }}</span>
                            </td>
                            <td>{{ $sol->medicoReceptor?->nombre_completo ?? '—' }}</td>
                            <td><span class="badge bg-{{ $sol->badge_estado }}">{{ $sol->label_estado }}</span></td>
                            <td><small>{{ $sol->created_at->format('d/m/Y') }}</small></td>
                            <td>
                                @if($sol->esta_abierta)
                                <form method="POST" action="{{ route('medico.cancelar-cambio', $sol) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-secondary" onclick="return confirm('¿Cancelar?')">Cancelar</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

@if($misNovedades->isNotEmpty())
<div class="tab-pane fade" id="tab-novedades">
    <div class="panel">
        <div class="panel-header"><span class="panel-title">Mis novedades</span></div>
        <div class="panel-body">
            <table class="table table-custom">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Horas</th></tr></thead>
                <tbody>
                    @foreach($misNovedades as $n)
                    <tr>
                        <td>{{ $n->fecha->format('d/m/Y') }}</td>
                        <td>{{ $n->label_tipo }}</td>
                        <td>{{ $n->descripcion ?? '—' }}</td>
                        <td>{{ $n->horas_afectadas>0 ? ($n->resta_horas?'-':'').$n->horas_afectadas.'h' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

</div>{{-- tab-content --}}
</div>{{-- fade-in --}}

{{-- Modal ofrecer --}}
<div class="modal fade" id="modalOfrecer" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-gift me-2"></i>Ofrecer turno</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('medico.ofrecer-turno') }}">
            @csrf
            <div class="modal-body">
                <input type="hidden" name="turno_id" id="ofertar_turno_id">
                <div class="alert alert-info" id="ofertar_info"></div>
                <div class="mb-3">
                    <label class="form-label">Motivo (opcional)</label>
                    <textarea name="motivo" class="form-control" rows="2"></textarea>
                </div>
                <p class="text-muted small">El turno quedará disponible para otros médicos. Un administrador debe aprobar el cambio final.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-gift me-1"></i>Ofrecer</button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal cambio/donación --}}
<div class="modal fade" id="modalCambio" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Solicitar cambio / donar turno</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="{{ route('medico.solicitar-cambio') }}">
            @csrf
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tipo</label>
                        <select name="tipo_movimiento" id="tipoMov" class="form-select">
                            <option value="cambio_directo">Cambio directo (intercambio)</option>
                            <option value="donacion_directa">Donación (le doy mi turno)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mi turno</label>
                        <select name="turno_origen_id" class="form-select" required>
                            <option value="">Seleccione...</option>
                            @foreach($misTurnos->filter(fn($t)=>$t->esTurnoActivo() && \Carbon\Carbon::parse($t->fecha)->isFuture()) as $t)
                                <option value="{{ $t->id }}">{{ $t->fecha->format('d/m') }} — {{ $t->codigo_turno }} ({{ $t->uci?->codigo }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Médico receptor</label>
                        <select name="medico_receptor_id" id="medicoReceptor" class="form-select" required>
                            <option value="">Seleccione médico...</option>
                            @foreach($todosMedicos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre_completo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="divTurnoDest">
                        <label class="form-label fw-bold">Turno del receptor (solo cambio)</label>
                        <select name="turno_destino_id" id="turnoDest" class="form-select">
                            <option value="">— Seleccione médico primero —</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Motivo</label>
                        <textarea name="motivo" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar solicitud</button>
            </div>
        </form>
    </div></div>
</div>
{{-- Modal evaluación burnout --}}
@include('burnout.encuesta-modal')

@endsection

@push('scripts')
<script>
function abrirOfrecer(id,fecha,codigo){
    document.getElementById('ofertar_turno_id').value=id;
    document.getElementById('ofertar_info').textContent='Turno: '+codigo+' — '+fecha;
    new bootstrap.Modal(document.getElementById('modalOfrecer')).show();
}
const MES={{ $mes }}, ANIO={{ $anio }};
document.getElementById('medicoReceptor')?.addEventListener('change',function(){
    const id=this.value, tipo=document.getElementById('tipoMov').value;
    const sel=document.getElementById('turnoDest');
    if(!id||tipo==='donacion_directa'){sel.innerHTML='<option value="">No aplica para donación</option>';return;}
    sel.innerHTML='<option>Cargando...</option>';
    fetch(`{{ route('medico.turnos-api') }}?medico_id=${id}&mes=${MES}&anio=${ANIO}`)
        .then(r=>r.json()).then(ts=>{
            sel.innerHTML='<option value="">Seleccione...</option>'+
                ts.map(t=>`<option value="${t.id}">${t.label}</option>`).join('');
        });
});
document.getElementById('tipoMov')?.addEventListener('change',function(){
    const don=this.value==='donacion_directa';
    document.getElementById('divTurnoDest').style.opacity=don?'.4':'1';
    if(don) document.getElementById('turnoDest').innerHTML='<option value="">No aplica</option>';
});
</script>
@endpush
