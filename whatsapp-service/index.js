require('dotenv').config();

const express = require('express');
const qrcode = require('qrcode-terminal');
const { Client, LocalAuth } = require('whatsapp-web.js');

const PORT = process.env.PORT || 3001;
const API_KEY = process.env.API_KEY;
const PAIS_POR_DEFECTO = process.env.CODIGO_PAIS_DEFECTO || '57'; // Colombia

if (!API_KEY) {
    console.error('Falta API_KEY en whatsapp-service/.env — defínela antes de iniciar.');
    process.exit(1);
}

let clienteListo = false;

const client = new Client({
    authStrategy: new LocalAuth({ dataPath: '.wwebjs_auth' }),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
});

client.on('qr', (qr) => {
    console.log('\nEscanea este código QR desde WhatsApp (Dispositivos vinculados) UNA SOLA VEZ:\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    clienteListo = true;
    console.log('WhatsApp conectado y listo para enviar mensajes.');
});

client.on('disconnected', (motivo) => {
    clienteListo = false;
    console.error('WhatsApp se desconectó:', motivo);
});

client.initialize();

function normalizarTelefono(telefono) {
    let numero = String(telefono).replace(/\D/g, '');
    if (numero.length <= 10) {
        numero = PAIS_POR_DEFECTO + numero;
    }
    return `${numero}@c.us`;
}

const app = express();
app.use(express.json());

app.use((req, res, next) => {
    if (req.header('X-API-KEY') !== API_KEY) {
        return res.status(401).json({ ok: false, error: 'API key inválida.' });
    }
    next();
});

app.get('/status', (req, res) => {
    res.json({ ok: true, whatsapp_listo: clienteListo });
});

app.post('/enviar', async (req, res) => {
    const { telefono, mensaje } = req.body || {};

    if (!telefono || !mensaje) {
        return res.status(422).json({ ok: false, error: 'telefono y mensaje son obligatorios.' });
    }

    if (!clienteListo) {
        return res.status(503).json({ ok: false, error: 'WhatsApp todavía no está listo (falta escanear el QR o se perdió la conexión).' });
    }

    try {
        const chatId = normalizarTelefono(telefono);
        await client.sendMessage(chatId, mensaje);
        res.json({ ok: true });
    } catch (error) {
        console.error('Error enviando mensaje de WhatsApp:', error);
        res.status(500).json({ ok: false, error: 'No se pudo enviar el mensaje.' });
    }
});

app.listen(PORT, () => {
    console.log(`Servicio de WhatsApp escuchando en http://localhost:${PORT}`);
});
