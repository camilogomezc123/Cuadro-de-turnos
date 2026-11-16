# Servicio de WhatsApp (no oficial)

Microservicio Node.js que mantiene una sesión de WhatsApp Web abierta (vía `whatsapp-web.js`)
y expone una API HTTP interna que la app de Laravel llama para enviar alertas de cambios de turno.

**Importante — riesgo de bloqueo:** esto NO es la API oficial de WhatsApp (Meta/Twilio). Usa
un número de WhatsApp normal automatizado mediante el navegador. Meta puede bloquear ese número
sin aviso si detecta un patrón de automatización. Por eso:

- Usa un número de WhatsApp **dedicado** para las alertas (no el personal del coordinador).
- No lo uses para enviar mensajes masivos ni muy seguidos; solo alertas puntuales de cambios de turno.
- Si el número se bloquea, hay que vincular otro y escanear el QR de nuevo.

## Instalación

```bash
cd whatsapp-service
npm install
copy .env.example .env    # en Windows; en Linux/Mac: cp .env.example .env
```

Edita `.env` y pon una `API_KEY` larga y aleatoria (debe coincidir con `WHATSAPP_API_KEY` en el `.env` de Laravel).

## Primer arranque (escanear el QR)

```bash
npm start
```

Va a imprimir un código QR en la terminal. Ábrelo desde el celular con el número dedicado:
WhatsApp → Configuración → Dispositivos vinculados → Vincular un dispositivo → escanea el QR.

Una vez conectado, queda la sesión guardada en la carpeta `.wwebjs_auth/` — no hace falta
escanear de nuevo salvo que se cierre sesión desde el celular o se borre esa carpeta.

## Dejarlo corriendo siempre

Igual que el programador de tareas que ya se configuró para el correo mensual, este servicio
necesita quedar corriendo en segundo plano. En Windows, la forma más simple es con `pm2`:

```bash
npm install -g pm2
pm2 start index.js --name whatsapp-service
pm2 save
pm2-startup install   # (o configurar una Tarea Programada de Windows que ejecute "pm2 resurrect")
```

## Endpoints

- `GET /status` — `{ ok: true, whatsapp_listo: true|false }`
- `POST /enviar` — body `{ "telefono": "3001234567", "mensaje": "..." }`
  - Requiere el header `X-API-KEY` con la misma clave del `.env`.
  - El teléfono se normaliza agregando el código de país (`CODIGO_PAIS_DEFECTO`, por defecto 57)
    si viene sin él.
