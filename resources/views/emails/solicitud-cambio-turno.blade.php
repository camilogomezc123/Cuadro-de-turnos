<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px; }
        .card { background:#fff; border-radius:8px; max-width:560px; margin:0 auto; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .header { background:#1565C0; color:#fff; border-radius:6px; padding:16px 24px; margin-bottom:24px; }
        .header h1 { margin:0; font-size:20px; }
        .row { display:flex; border-bottom:1px solid #f0f0f0; padding:10px 0; }
        .row:last-child { border-bottom:none; }
        .label { color:#666; font-size:13px; width:160px; flex-shrink:0; }
        .value { color:#1a2340; font-size:13px; font-weight:600; }
        .badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:12px; }
        .badge-blue { background:#dbeafe; color:#1e40af; }
        .badge-green { background:#dcfce7; color:#166534; }
        .footer { margin-top:24px; color:#888; font-size:12px; text-align:center; }
        .btn { display:inline-block; margin-top:20px; padding:10px 24px; background:#1565C0; color:#fff; border-radius:6px; text-decoration:none; font-size:14px; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>
            @if($solicitud->tipo_movimiento === 'donacion_directa')
                Solicitud de cedencia de turno
            @else
                Solicitud de cambio de turno
            @endif
        </h1>
    </div>

    <p style="color:#444;font-size:14px;">Hola, tienes una nueva solicitud de un colega:</p>

    <div class="row">
        <span class="label">Solicitante</span>
        <span class="value">{{ $solicitud->medicoSolicitante?->nombre ?? '—' }}</span>
    </div>
    <div class="row">
        <span class="label">Tipo</span>
        <span class="value">
            <span class="badge badge-blue">
                {{ $solicitud->tipo_movimiento === 'donacion_directa' ? 'Cedencia' : 'Cambio directo' }}
            </span>
        </span>
    </div>
    <div class="row">
        <span class="label">Turno ofrecido</span>
        <span class="value">
            {{ $solicitud->turnoOrigen?->fecha?->format('d/m/Y') ?? '—' }}
            — {{ $solicitud->turnoOrigen?->codigo_turno ?? '—' }}
            @if($solicitud->componente_turno)
                (componente: <strong>{{ $solicitud->componente_turno }}</strong>)
            @endif
        </span>
    </div>
    <div class="row">
        <span class="label">UCI</span>
        <span class="value">{{ $solicitud->turnoOrigen?->uci?->nombre ?? '—' }}</span>
    </div>
    <div class="row">
        <span class="label">Motivo</span>
        <span class="value">{{ $solicitud->motivo }}</span>
    </div>

    <p style="color:#444;font-size:13px;margin-top:20px;">
        Ingresa al sistema para aceptar o rechazar esta solicitud.
    </p>

    <div class="footer">
        Cuadro de Turnos UCI — mensaje automático
    </div>
</div>
</body>
</html>