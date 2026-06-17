@extends('layouts.app')

@section('title', 'De Turno Ahora')
@section('page-title', 'De Turno Ahora')
@section('breadcrumb')
    <li class="breadcrumb-item active">De turno ahora</li>
@endsection

@push('head-styles')
<style>
.turno-card { border-radius: 12px; transition: box-shadow .2s; }
.turno-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.turno-ahora-header {
    border-radius: 10px 10px 0 0;
    padding: 14px 18px;
    font-weight: 700;
    font-size: 15px;
}
.badge-turno-xl {
    font-size: 22px;
    font-weight: 800;
    padding: 8px 18px;
    border-radius: 8px;
    letter-spacing: 1px;
}
.cobertura-ok   { background: #e8f5e9; border-left: 4px solid #4CAF50; }
.cobertura-fail { background: #fff3e0; border-left: 4px solid #FF9800; }
.reloj-actual { font-size: 28px; font-weight: 800; letter-spacing: 2px; color: #1565C0; }
.turno-franja {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: #1565C0;
    color: #fff;
}
</style>
@endpush

@section('content')

{{-- Cabecera con reloj --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <div class="reloj-actual" id="reloj">{{ $ahora->format('H:i') }}</div>
        <div class="text-muted small">{{ $ahora->translatedFormat('l, d \d\e F \d\e Y') }}</div>
    </div>
    <div class="text-end">
        <span class="turno-franja">{{ $tarjetas[0]['turno_actual'] ?? '—' }}</span>
        @if($archivo)
            <div class="text-muted small mt-1">
                <i class="bi bi-calendar3 me-1"></i>Datos de {{ $archivo->nombre_mes }} {{ $archivo->anio }}
            </div>
        @else
            <div class="text-warning small mt-1">
                <i class="bi bi-exclamation-triangle me-1"></i>No hay archivo del período actual
            </div>
        @endif
        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
        </button>
    </div>
</div>

@if(empty($tarjetas))
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>No hay datos de turnos disponibles.
        <a href="{{ route('archivos.index') }}" class="alert-link">Cargar un archivo Excel</a> primero.
    </div>
@else
<div class="row g-3">
    @foreach($tarjetas as $t)
        @php $coberturaOk = !empty($t['activos']); @endphp
        <div class="col-xl-4 col-lg-6">
            <div class="turno-card card border-0 shadow-sm {{ $coberturaOk ? 'cobertura-ok' : 'cobertura-fail' }}">
                {{-- Header UCI --}}
                <div class="turno-ahora-header d-flex justify-content-between align-items-center
                     {{ $coberturaOk ? 'bg-success text-white' : 'bg-warning text-dark' }}">
                    <div>
                        <i class="bi bi-hospital me-2"></i>{{ $t['uci']->nombre }}
                    </div>
                    @if($coberturaOk)
                        <span class="badge bg-white text-success"><i class="bi bi-check-circle me-1"></i>Cubierta</span>
                    @else
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Sin cobertura</span>
                    @endif
                </div>

                <div class="card-body py-3">
                    {{-- Médicos activos ahora --}}
                    @if(!empty($t['activos']))
                        <div class="mb-2">
                            <div class="text-muted small fw-semibold mb-1">DE TURNO AHORA</div>
                            @foreach($t['activos'] as $a)
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge-turno-xl badge-{{ $a['codigo'] }}
                                          d-inline-block text-center" style="min-width:64px">
                                        {{ $a['codigo'] }}
                                    </span>
                                    <div>
                                        <div class="fw-semibold">{{ $a['medico'] }}</div>
                                        <div class="text-muted small">
                                            {{ $a['hora_inicio'] }} – {{ $a['hora_fin'] }}
                                            · {{ $a['horas'] }}h
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small py-2">
                            <i class="bi bi-person-x me-1"></i>Ningún médico de turno en este momento
                        </div>
                    @endif

                    {{-- Próximos relevos --}}
                    @if(!empty($t['proximos']))
                        <hr class="my-2">
                        <div class="text-muted small fw-semibold mb-1">PRÓXIMO RELEVO</div>
                        @foreach($t['proximos'] as $p)
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge badge-{{ $p['codigo'] }}"
                                      style="font-size:11px;padding:3px 8px;border-radius:5px;font-weight:700">
                                    {{ $p['codigo'] }}
                                </span>
                                <span class="small">{{ $p['medico'] }}</span>
                                <span class="text-muted small ms-auto">desde {{ $p['hora_inicio'] }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection

@push('scripts')
<script>
// Actualizar el reloj cada minuto
function actualizarReloj() {
    const ahora = new Date();
    document.getElementById('reloj').textContent =
        String(ahora.getHours()).padStart(2,'0') + ':' +
        String(ahora.getMinutes()).padStart(2,'0');
}
setInterval(actualizarReloj, 60000);

// Auto-recarga cada 5 minutos
setTimeout(() => location.reload(), 5 * 60 * 1000);
</script>
@endpush
