<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:20px; }
        .card { background:#fff; border-radius:8px; max-width:560px; margin:0 auto; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .header { background:#1565C0; color:#fff; border-radius:6px; padding:16px 24px; margin-bottom:24px; }
        .header h1 { margin:0; font-size:20px; }
        .footer { margin-top:24px; color:#888; font-size:12px; text-align:center; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>Consolidado de turnos UCI — {{ $nombreMes }} {{ $anio }}</h1>
    </div>

    <p style="color:#444;font-size:14px;">
        Adjunto el consolidado mensual actualizado de {{ $nombreMes }} {{ $anio }}, con el detalle de horas
        por médico, el cronograma completo día por día, el consolidado por UCI, las alertas y las novedades
        registradas hasta el momento del envío.
    </p>

    <div class="footer">
        Cuadro de Turnos UCI — envío automático de fin de mes
    </div>
</div>
</body>
</html>
