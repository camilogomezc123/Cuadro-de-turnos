<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a2340; }
        h1 { font-size: 14px; color: #1565C0; margin-bottom: 4px; }
        h2 { font-size: 11px; color: #1976D2; margin: 12px 0 4px; border-bottom: 1px solid #e0e7ef; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        thead th { background: #1565C0; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #f0f4f8; }
        tbody tr:nth-child(even) { background: #f8faff; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 4px; font-size: 8px; font-weight: bold; }
        .badge-M  { background: #E3F2FD; color: #1565C0; }
        .badge-T  { background: #E8F5E9; color: #2E7D32; }
        .badge-MT { background: #FFF3E0; color: #E65100; }
        .badge-N  { background: #EDE7F6; color: #4527A0; }
        .kpi-row { display: flex; gap: 10px; margin-bottom: 12px; }
        .kpi { background: #f0f4f8; border-radius: 6px; padding: 6px 10px; text-align: center; flex: 1; }
        .kpi-v { font-size: 14px; font-weight: bold; color: #1565C0; }
        .kpi-l { font-size: 7px; color: #6b7a99; }
        .header { border-bottom: 2px solid #1565C0; padding-bottom: 6px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="header">
    <h1>CUADRO DE TURNOS UCI</h1>
    <div>{{ $archivo->nombre_mes }} {{ $archivo->anio }} &nbsp;·&nbsp;
         Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

<table style="width:auto;margin-bottom:12px">
    <tr>
        <td style="padding:4px 10px;background:#E3F2FD;border-radius:6px;text-align:center">
            <div style="font-size:14px;font-weight:bold;color:#1565C0">{{ $totalMedicos }}</div>
            <div style="font-size:7px;color:#6b7a99">Médicos</div>
        </td>
        <td style="padding:0 4px"></td>
        <td style="padding:4px 10px;background:#E8F5E9;border-radius:6px;text-align:center">
            <div style="font-size:14px;font-weight:bold;color:#2E7D32">{{ number_format($totalHoras, 1) }}</div>
            <div style="font-size:7px;color:#6b7a99">Horas Totales</div>
        </td>
        <td style="padding:0 4px"></td>
        <td style="padding:4px 10px;background:#EDE7F6;border-radius:6px;text-align:center">
            <div style="font-size:14px;font-weight:bold;color:#4527A0">{{ number_format($totalNocturnas, 1) }}</div>
            <div style="font-size:7px;color:#6b7a99">Horas Nocturnas</div>
        </td>
    </tr>
</table>

<h2>Indicadores por UCI</h2>
<table>
    <thead>
        <tr>
            <th>UCI</th><th>Especialistas</th><th>Horas Totales</th>
            <th>Prom/Médico</th><th>Cob. Mensual</th><th>Cob. Nocturna</th><th>Cob. F/S</th>
        </tr>
    </thead>
    <tbody>
    @foreach($indicadoresUci as $i)
    <tr>
        <td>{{ $i->uci->nombre }}</td>
        <td>{{ $i->num_especialistas }}</td>
        <td>{{ number_format($i->horas_totales, 1) }}</td>
        <td>{{ number_format($i->horas_promedio_medico, 1) }}</td>
        <td>{{ number_format($i->cobertura_mensual, 1) }}%</td>
        <td>{{ number_format($i->cobertura_nocturna, 1) }}%</td>
        <td>{{ number_format($i->cobertura_fin_semana, 1) }}%</td>
    </tr>
    @endforeach
    </tbody>
</table>

<h2>Indicadores por Médico</h2>
<table>
    <thead>
        <tr>
            <th>Médico</th><th>UCI</th><th>Total Hrs</th><th>H. Diurnas</th><th>H. Noct.</th>
            <th>M</th><th>T</th><th>MT</th><th>N</th><th>F/S</th><th>% Ocup.</th>
        </tr>
    </thead>
    <tbody>
    @foreach($indicadoresMedico as $i)
    <tr>
        <td>{{ $i->medico->nombre }}</td>
        <td>{{ str_replace('UCI ', '', $i->uci->nombre) }}</td>
        <td><strong>{{ number_format($i->total_horas, 1) }}</strong></td>
        <td>{{ number_format($i->horas_diurnas, 1) }}</td>
        <td>{{ number_format($i->horas_nocturnas, 1) }}</td>
        <td><span class="badge badge-M">{{ $i->turnos_m }}</span></td>
        <td><span class="badge badge-T">{{ $i->turnos_t }}</span></td>
        <td><span class="badge badge-MT">{{ $i->turnos_mt }}</span></td>
        <td><span class="badge badge-N">{{ $i->turnos_n }}</span></td>
        <td>{{ $i->turnos_fin_semana }}</td>
        <td>{{ number_format($i->porcentaje_ocupacion, 1) }}%</td>
    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
