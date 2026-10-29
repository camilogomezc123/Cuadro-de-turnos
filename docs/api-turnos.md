# API de consulta diaria de turnos

Producción: `GET https://gruposdetrabajo.koqoi.com/api/v1/turnos`

Autenticación: encabezado `Authorization: Bearer CLAVE`. No enviar la clave en la URL ni en instrucciones compartidas. Solo lectura, hasta 60 solicitudes por minuto por IP. Respuestas sin caché.

Parámetros opcionales: `fecha` (YYYY-MM-DD, por defecto hoy en America/Bogota) y `uci` (código exacto de una UCI activa). Sin filtro se devuelven todas las UCI activas, incluso las que no tienen turnos.

Devuelve los intervalos que se superponen con el día civil de 00:00 a 24:00. Incluye la madrugada del turno nocturno anterior. Los intervalos conservan inicio y fin reales, aunque queden fuera del día consultado. MN se separa en mañana y noche, dejando libre la tarde. TN cubre 13:00 a 07:00 del día siguiente.

Se consulta la última programación procesada de cada mes y las asignaciones actuales, que incorporan cambios aprobados. Se excluyen ausencias, turnos cancelados, descubiertos, reemplazados, no asistidos o marcados como no laborados. No se confirma presencia física en tiempo real. No se exponen documentos, teléfonos, correos, motivos ni credenciales.

`programacion_cargada` y `programacion_dia_anterior_cargada` indican si existe una programación mensual procesada. No garantizan cobertura completa de cada UCI o día. `sin_turnos_registrados` significa que no hay asignaciones devueltas, nunca que se haya confirmado que la UCI está sin personal. Si falta programación, el GPT debe decirlo expresamente.

Errores JSON: 401 clave ausente/inválida, 404 UCI activa inexistente, 422 parámetros inválidos, 429 límite excedido, 503 sin claves configuradas. No inventar turnos ante errores o datos ausentes.

## Conexión del GPT

Importar `https://gruposdetrabajo.koqoi.com/openapi-turnos.json` en el cliente que soporte OpenAPI y configurar la autenticación HTTP Bearer en su campo de credenciales. La clave se entrega por separado. La ejecución diaria debe programarse en el entorno del consumidor; esta API responde a consultas y no programa tareas.

Instrucción sugerida: «Consulta consultarTurnosUci cada vez que te pidan quién está de turno. Usa fecha de Colombia o la fecha solicitada. Agrupa por UCI, muestra médico, código e inicio/fin y distingue la madrugada proveniente del día anterior. MN contiene dos intervalos. Explica programación faltante; no inventes cobertura ni afirmes asistencia real».

## Administración de claves (en cada entorno)

```bash
php artisan turnos:api-key gpt-externo --output=/ruta/privada/clave-nueva.txt
php artisan turnos:api-key gpt-externo --revoke
```

Crear nuevamente con el mismo nombre rota la clave e invalida la anterior. El archivo de salida debe ser nuevo y estar fuera del directorio público; contiene la clave en texto para su entrega privada. El registro `storage/app/private/turnos-api-keys.json` solo contiene hashes SHA-256 y debe ser legible por PHP-FPM. No versionar ninguno de estos archivos. Se admiten claves independientes para varios consumidores.
