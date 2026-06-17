@extends('layouts.app')

@section('title', $medico->nombre)
@section('page-title', $medico->nombre)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('medicos.index') }}">Médicos</a></li>
    <li class="breadcrumb-item active">{{ Str::limit($medico->nombre, 30) }}</li>
@endsection

@section('content')
{{-- SELECTOR PERÍODO --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="fw-semibold text-secondary small">Período:</label>
        <select name="archivo_id" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
            @foreach($archivos as $a)
                <option value="{{ $a->id }}" {{ $a->id == ($archivo?->id) ? 'selected' : '' }}>
                    {{ $a->nombre_mes }} {{ $a->anio }}
                </option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('medicos.index', ['archivo_id' => $archivo?->id]) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
    @if($archivo && $indicador)
    <div class="ms-auto d-flex gap-2">
        <form method="POST" action="{{ route('reportes.medico.excel') }}" class="d-inline">
            @csrf
            <input type="hidden" name="medico_id" value="{{ $medico->id }}">
            <input type="hidden" name="archivo_id" value="{{ $archivo->id }}">
            <button class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        </form>
        <form method="POST" action="{{ route('reportes.medico.pdf') }}" class="d-inline">
            @csrf
            <input type="hidden" name="medico_id" value="{{ $medico->id }}">
            <input type="hidden" name="archivo_id" value="{{ $archivo->id }}">
            <button class="btn btn-sm btn-danger"><i class="bi bi-file-pdf me-1"></i>PDF</button>
        </form>
    </div>
    @endif
</div>

@if($indicador)
{{-- KPIs --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['Total Horas', number_format($indicador->total_horas, 1), 'bi-clock-fill', '#E3F2FD', 'text-primary'],
        ['H. Diurnas', number_format($indicador->horas_diurnas, 1), 'bi-sun-fill', '#FFF8E1', 'text-warning'],
        ['H. Nocturnas', number_format($indicador->horas_nocturnas, 1), 'bi-moon-stars-fill', '#EDE7F6', 'text-purple'],
        ['T. Fin Semana', $indicador->turnos_fin_semana, 'bi-calendar-heart-fill', '#FCE4EC', 'text-danger'],
        ['Prom. Semanal', number_format($indicador->promedio_semanal, 1).'h', 'bi-graph-up-arrow', '#E8F5E9', 'text-success'],
        ['% Ocupación', number_format($indicador->porcentaje_ocupacion, 1).'%', 'bi-pie-chart-fill', '#E0F7FA', 'text-info'],
    ];
    @endphp
    @foreach($kpis as [$label, $value, $icon, $bg, $color])
    <div class="col-6 col-md-4 col-lg-2">
        <div class="kpi-card text-center">
            <div class="kpi-icon mx-auto mb-2" style="background:{{ $bg }}">
                <i class="bi {{ $icon }} {{ $color }} fs-5"></i>
            </div>
            <div class="kpi-value" style="font-size:1.4rem">{{ $value }}</div>
            <div class="kpi-label">{{ $label }}</div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    {{-- Distribución de turnos --}}
    <div class="col-md-5">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title"><i class="bi bi-pie-chart me-2 text-primary"></i>Distribución de Turnos</span>
            </div>
            <div class="panel-body">
                @php
                $distribRows = [
                    ['M', 'Mañana (7am–1pm)', $indicador->turnos_m, 'badge-M'],
                    ['T', 'Tarde (1pm–7pm)', $indicador->turnos_t, 'badge-T'],
                    ['MT', 'Mañana-Tarde (7am–7pm)', $indicador->turnos_mt, 'badge-MT'],
                    ['N', 'Noche (7pm–7am)', $indicador->turnos_n, 'badge-N'],
                ];
                $totalTurnos = $indicador->turnos_m + $indicador->turnos_t + $indicador->turnos_mt + $indicador->turnos_n;
                @endphp
                @foreach($distribRows as [$cod, $desc, $cnt, $badgeClass])
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="{{ $badgeClass }} turno-badge" style="width:36px;text-align:center">{{ $cod }}</span>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">{{ $desc }}</span>
                            <strong>{{ $cnt }} {{ Str::plural('turno', $cnt) }}</strong>
                        </div>
                        <div class="progress mt-1" style="height:6px">
                            <div class="progress-bar" style="width:{{ $totalTurnos>0 ? round($cnt/$totalTurnos*100) : 0 }}%;
                                background:{{ ['M'=>'#2196F3','T'=>'#4CAF50','MT'=>'#FF9800','N'=>'#9C27B0'][$cod] }}"></div>
                        </div>
                    </div>
                </div>
                @endforeach

                <div class="border-top pt-3 mt-2">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="small text-muted">Turnos Domingo</div>
                            <div class="fw-bold text-danger">{{ $indicador->turnos_domingo }}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Prom. Diario</div>
                            <div class="fw-bold text-info">{{ number_format($indicador->promedio_diario, 2) }}h</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Calendario del mes --}}
    <div class="col-md-7">
        <div class="panel h-100">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="bi bi-calendar3 me-2 text-success"></i>
                    Calendario — {{ $archivo->nombre_mes }} {{ $archivo->anio }}
                </span>
            </div>
            <div class="panel-body">
                @if($turnos->isNotEmpty())
                @php
                $turnosPorDia = $turnos->keyBy('dia_numero');
                $primerDia = \Carbon\Carbon::create($archivo->anio, $archivo->mes, 1)->dayOfWeek;
                $primerDia = $primerDia === 0 ? 6 : $primerDia - 1; // 0=Lun
                $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $archivo->mes, $archivo->anio);
                $coloresTurno = ['M'=>'#E3F2FD','T'=>'#E8F5E9','MT'=>'#FFF3E0','N'=>'#EDE7F6'];
                $textColor = ['M'=>'#1565C0','T'=>'#2E7D32','MT'=>'#E65100','N'=>'#4527A0'];
                @endphp
                <div class="mb-2">
                    <div class="calendar-grid text-center mb-1">
                        @foreach(['Lu','Ma','Mi','Ju','Vi','Sá','Do'] as $d)
                            <div class="small fw-bold text-muted py-1">{{ $d }}</div>
                        @endforeach
                    </div>
                    <div class="calendar-grid">
                        @for($i = 0; $i < $primerDia; $i++)
                            <div></div>
                        @endfor
                        @for($d = 1; $d <= $diasEnMes; $d++)
                        @php
                            $t = $turnosPorDia->get($d);
                            $cod = $t?->codigo_turno ?? '';
                            $bg = $coloresTurno[$cod] ?? '#F5F5F5';
                            $fg = $textColor[$cod] ?? '#9E9E9E';
                            $esFinde = $t?->es_fin_semana;
                        @endphp
                        <div class="calendar-cell {{ $cod ? 'has-turno' : '' }}"
                             style="background:{{ $bg }};color:{{ $fg }};{{ $esFinde ? 'border:1px solid #FFB74D' : '' }}"
                             title="{{ $t?->dia_semana }} {{ $d }} - {{ $cod ?: 'Libre' }}">
                            <div class="day-num">{{ $d }}</div>
                            @if($cod)<div style="font-size:.65rem;font-weight:700">{{ $cod }}</div>@endif
                        </div>
                        @endfor
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach(['M'=>['#E3F2FD','#1565C0','Mañana'],'T'=>['#E8F5E9','#2E7D32','Tarde'],'MT'=>['#FFF3E0','#E65100','M-Tarde'],'N'=>['#EDE7F6','#4527A0','Noche']] as $c => [$bg,$fg,$lbl])
                    <div class="d-flex align-items-center gap-1 small">
                        <div style="width:12px;height:12px;border-radius:3px;background:{{ $bg }};border:1px solid {{ $fg }}"></div>
                        <span class="text-muted">{{ $lbl }}</span>
                    </div>
                    @endforeach
                    <div class="d-flex align-items-center gap-1 small">
                        <div style="width:12px;height:12px;border-radius:3px;border:1px solid #FFB74D"></div>
                        <span class="text-muted">Fin de semana</span>
                    </div>
                </div>
                @else
                    <div class="text-center text-muted py-4">Sin turnos registrados para este período.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@else
<div class="panel p-4 text-center text-muted">
    <i class="bi bi-info-circle fs-1 opacity-25 d-block mb-2"></i>
    No hay indicadores para este médico en el período seleccionado.
</div>
@endif

{{-- HISTORIAL --}}
@if($historial->isNotEmpty())
<div class="panel mt-3">
    <div class="panel-header">
        <span class="panel-title"><i class="bi bi-clock-history me-2 text-secondary"></i>Historial Mensual</span>
    </div>
    <div class="panel-body p-0">
        <div class="table-responsive">
            <table class="table table-custom mb-0">
                <thead>
                    <tr><th>Período</th><th>UCI</th><th>Total Horas</th><th>H. Nocturnas</th><th>Turnos N</th><th>% Ocup.</th></tr>
                </thead>
                <tbody>
                    @foreach($historial as $h)
                    <tr>
                        <td>{{ ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'][$h->mes] }} {{ $h->anio }}</td>
                        <td><span class="badge bg-primary-subtle text-primary">{{ $h->uci->nombre ?? '-' }}</span></td>
                        <td>{{ number_format($h->total_horas, 1) }}</td>
                        <td>{{ number_format($h->horas_nocturnas, 1) }}</td>
                        <td>{{ $h->turnos_n }}</td>
                        <td>{{ number_format($h->porcentaje_ocupacion, 1) }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
