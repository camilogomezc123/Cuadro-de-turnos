<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a2340; }
        h1 { font-size: 15px; color: #1565C0; margin-bottom: 2px; }
        h2 { font-size: 11px; color: #1565C0; margin: 14px 0 5px; border-bottom: 1px solid #e0e7ef; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        thead th { background: #1565C0; color: #fff; padding: 5px 8px; text-align: left; font-size: 9px; }
        tbody td { padding: 4px 8px; border-bottom: 1px solid #f0f4f8; }
        tbody tr:nth-child(even) { background: #f8faff; }
        .kpi-grid { display: table; width: 100%; margin-bottom: 12px; }
        .kpi-item { display: table-cell; text-align: center; background: #f0f4f8; border-radius: 6px; padding: 8px; }
        .kpi-v { font-size: 16px; font-weight: bold; color: #1565C0; }
        .kpi-l { font-size: 7px; color: #6b7a99; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 9px; }
        .M  { background: #E3F2FD; color: #1565C0; }
        .T  { background: #E8F5E9; color: #2E7D32; }
        .MT { background: #FFF3E0; color: #E65100; }
        .N  { background: #EDE7F6; color: #4527A0; }
        .libre { background: #F5F5F5; color: #9E9E9E; }
    </style>
</head>
<body>
<h1>{{ $indicador->medico->nombre }}</h1>
<div>UCI: {{ $indicador->uci->nombre }} &nbsp;·&nbsp; Período: {{ $indicador->mes }}/{{ $indicador->anio }} &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y H:i') }}</div>

<h2>Indicadores del Período</h2>
<table>
    <tr><td><strong>Total Horas Trabajadas</strong></td><td>{{ $indicador->total_horas }} hrs</td>
        <td><strong>Horas Diurnas</strong></td><td>{{ $indicador->horas_diurnas }} hrs</td></tr>
    <tr><td><strong>Horas Nocturnas</strong></td><td>{{ $indicador->horas_nocturnas }} hrs</td>
        <td><strong>Promedio Semanal</strong></td><td>{{ number_format($indicador->promedio_semanal, 2) }} hrs</td></tr>
    <tr><td><strong>Turnos Mañana (M)</strong></td><td>{{ $indicador->turnos_m }}</td>
        <td><strong>Turnos Tarde (T)</strong></td><td>{{ $indicador->turnos_t }}</td></tr>
    <tr><td><strong>Turnos Mañana-Tarde (MT)</strong></td><td>{{ $indicador->turnos_mt }}</td>
        <td><strong>Turnos Noche (N)</strong></td><td>{{ $indicador->turnos_n }}</td></tr>
    <tr><td><strong>Turnos Fin de Semana</strong></td><td>{{ $indicador->turnos_fin_semana }}</td>
        <td><strong>Turnos Domingo</strong></td><td>{{ $indicador->turnos_domingo }}</td></tr>
    <tr><td><strong>Promedio Diario</strong></td><td>{{ number_format($indicador->promedio_diario, 2) }} hrs</td>
        <td><strong>% Ocupación</strong></td><td>{{ number_format($indicador->porcentaje_ocupacion, 2) }}%</td></tr>
</table>

<h2>Detalle de Turnos</h2>
<table>
    <thead>
        <tr><th>Fecha</th><th>Día</th><th>Turno</th><th>H. Diurnas</th><th>H. Nocturnas</th><th>Total</th><th>F/S</th></tr>
    </thead>
    <tbody>
    @foreach($turnos as $t)
    <tr>
        <td>{{ $t->fecha->format('d/m/Y') }}</td>
        <td>{{ ucfirst($t->dia_semana) }}</td>
        <td>
            @if($t->codigo_turno)
                <span class="badge {{ $t->codigo_turno }}">{{ $t->codigo_turno }}</span>
            @else
                <span class="badge libre">Libre</span>
            @endif
        </td>
        <td>{{ $t->horas_diurnas > 0 ? $t->horas_diurnas . 'h' : '-' }}</td>
        <td>{{ $t->horas_nocturnas > 0 ? $t->horas_nocturnas . 'h' : '-' }}</td>
        <td><strong>{{ $t->horas_total > 0 ? $t->horas_total . 'h' : '-' }}</strong></td>
        <td>{{ $t->es_fin_semana ? '✓' : '' }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
