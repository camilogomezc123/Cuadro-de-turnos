{{-- Modal de encuesta de burnout — se incluye en medico/portal.blade.php --}}
<div class="modal fade" id="modalBurnout" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-heart-pulse me-2"></i>Evaluación de Bienestar Profesional</h5>
            </div>
            <div id="burnout-loading" class="modal-body text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-3 text-muted">Cargando evaluación...</p>
            </div>
            <div id="burnout-form-wrap" class="modal-body" style="display:none">
                <div class="alert alert-info border-0 small">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Nota:</strong> Esta encuesta breve busca identificar señales tempranas de desgaste profesional asociadas a carga laboral y programación de turnos. <strong>No corresponde a un diagnóstico clínico</strong> ni reemplaza una valoración profesional. La información será usada con fines de bienestar laboral y mejora organizacional.
                </div>
                <div class="mb-3 p-3 rounded" style="background:#f8fafc">
                    <div class="small fw-semibold text-muted mb-2">Escala de respuesta (seleccione un valor del 0 al 6 para cada pregunta):</div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-light text-dark border">0 – Nunca</span>
                        <span class="badge bg-light text-dark border">1 – Pocas veces al año</span>
                        <span class="badge bg-light text-dark border">2 – Una vez al mes</span>
                        <span class="badge bg-light text-dark border">3 – Pocas veces al mes</span>
                        <span class="badge bg-light text-dark border">4 – Una vez a la semana</span>
                        <span class="badge bg-light text-dark border">5 – Pocas veces a la semana</span>
                        <span class="badge bg-light text-dark border">6 – Todos los días</span>
                    </div>
                </div>
                <form id="burnout-form">
                    <input type="hidden" id="b-encuesta-id" name="encuesta_id">
                    <input type="hidden" id="b-periodo"     name="periodo">
                    <div id="burnout-preguntas"></div>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <button type="button" id="btn-posponer-burnout" class="btn btn-outline-secondary btn-sm" style="display:none">
                            Completar más tarde
                        </button>
                        <button type="submit" class="btn btn-primary ms-auto">
                            <i class="bi bi-send me-1"></i>Enviar evaluación
                        </button>
                    </div>
                </form>
            </div>
            <div id="burnout-resultado" class="modal-body text-center py-5" style="display:none">
                <div id="burnout-resultado-icono" class="mb-3 fs-1"></div>
                <h5 id="burnout-resultado-titulo" class="fw-bold"></h5>
                <p id="burnout-resultado-mensaje" class="text-muted"></p>
                <button class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dimLabels = {
        agotamiento_emocional: 'Agotamiento Emocional',
        despersonalizacion: 'Despersonalización',
        realizacion_personal: 'Realización Personal',
    };
    const dimColors = {
        agotamiento_emocional: 'danger',
        despersonalizacion: 'warning',
        realizacion_personal: 'success',
    };

    function verificarBurnout() {
        fetch('{{ route("burnout.verificar") }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.mostrar) return;
            renderEncuesta(data);
            const modal = new bootstrap.Modal(document.getElementById('modalBurnout'), { keyboard: false, backdrop: 'static' });
            modal.show();
        })
        .catch(() => {});
    }

    function renderEncuesta(data) {
        document.getElementById('b-encuesta-id').value = data.encuesta_id;
        document.getElementById('b-periodo').value     = data.periodo;

        if (data.permite_posponer) {
            document.getElementById('btn-posponer-burnout').style.display = '';
        }

        const container = document.getElementById('burnout-preguntas');
        container.innerHTML = '';

        // Mostrar preguntas en orden sin agrupación por dimensión
        data.preguntas.sort((a, b) => a.orden - b.orden).forEach(p => {
            const wrap = document.createElement('div');
            wrap.className = 'mb-4 p-3 rounded border';
            wrap.innerHTML = `
              <label class="form-label fw-semibold mb-3">${p.orden}. ${p.texto_pregunta}</label>
              <div class="d-flex gap-3 flex-wrap justify-content-start">
                ${[0,1,2,3,4,5,6].map(v => `
                  <div class="text-center">
                    <input class="form-check-input d-block mx-auto mb-1" type="radio"
                           name="resp_${p.id}" id="r_${p.id}_${v}" value="${v}"
                           ${p.obligatoria ? 'required' : ''}>
                    <label class="form-check-label small text-muted" for="r_${p.id}_${v}">${v}</label>
                  </div>`).join('')}
              </div>`;
            container.appendChild(wrap);
        });

        document.getElementById('burnout-loading').style.display   = 'none';
        document.getElementById('burnout-form-wrap').style.display = '';
    }

    // Enviar respuestas
    document.getElementById('burnout-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const encuestaId = document.getElementById('b-encuesta-id').value;
        const periodo    = document.getElementById('b-periodo').value;
        const respuestas = [];
        const checked = document.querySelectorAll('#burnout-preguntas input[type=radio]:checked');
        checked.forEach(inp => {
            const pregId = parseInt(inp.name.replace('resp_', ''));
            respuestas.push({ pregunta_id: pregId, valor: parseInt(inp.value) });
        });

        fetch('{{ route("burnout.responder") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ encuesta_id: encuestaId, periodo, respuestas }),
        })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) { alert(data.mensaje); return; }
            document.getElementById('burnout-form-wrap').style.display = 'none';
            const nivel = data.nivel;
            const icono = nivel === 'severo' ? '🔴' : (nivel === 'positivo' ? '🟡' : '🟢');
            const titulo = nivel === 'severo' ? 'Señales importantes detectadas'
                         : nivel === 'positivo' ? 'Señales de alerta detectadas'
                         : 'Evaluación completada';
            document.getElementById('burnout-resultado-icono').textContent   = icono;
            document.getElementById('burnout-resultado-titulo').textContent  = titulo;
            document.getElementById('burnout-resultado-mensaje').textContent = data.mensaje;
            document.getElementById('burnout-resultado').style.display = '';
        })
        .catch(() => alert('Error al enviar. Intente nuevamente.'));
    });

    // Posponer
    document.getElementById('btn-posponer-burnout')?.addEventListener('click', function () {
        fetch('{{ route("burnout.posponer") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        }).then(() => bootstrap.Modal.getInstance(document.getElementById('modalBurnout'))?.hide());
    });

    // Verificar al cargar la página (tras 2 segundos)
    setTimeout(verificarBurnout, 2000);
});
</script>
