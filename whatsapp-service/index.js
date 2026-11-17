require('dotenv').config();

const express = require('express');
const qrcode = require('qrcode-terminal');
const QRCode = require('qrcode');
const { Client, LocalAuth } = require('whatsapp-web.js');

const PORT = process.env.PORT || 3001;
const API_KEY = process.env.API_KEY;
const PAIS_POR_DEFECTO = process.env.CODIGO_PAIS_DEFECTO || '57'; // Colombia

if (!API_KEY) {
    console.error('Falta API_KEY en whatsapp-service/.env — defínela antes de iniciar.');
    process.exit(1);
}

let clienteListo = false;
let ultimoQr = null;

const client = new Client({
    authStrategy: new LocalAuth({ dataPath: '.wwebjs_auth' }),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
});

client.on('qr', (qr) => {
    ultimoQr = qr;
    console.log('\nQR actualizado. Ábrelo en el navegador: http://localhost:' + PORT + '/qr?key=' + API_KEY + '\n');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    clienteListo = true;
    console.log('WhatsApp conectado y listo para enviar mensajes.');
});

client.on('disconnected', (motivo) => {
    clienteListo = false;
    ultimoQr = null;
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

// Sin autenticación por header para poder abrirla directo en el navegador (solo localhost).
app.get('/qr', async (req, res) => {
    if (req.query.key !== API_KEY) {
        return res.status(401).send('Clave inválida.');
    }
    if (clienteListo) {
        return res.status(200).send('WhatsApp ya está conectado. No hay QR pendiente.');
    }
    if (!ultimoQr) {
        return res.status(202).send('Todavía no se generó el QR, espera unos segundos y refresca.');
    }
    try {
        const buffer = await QRCode.toBuffer(ultimoQr, { width: 400 });
        res.set('Content-Type', 'image/png');
        res.set('Cache-Control', 'no-store');
        res.send(buffer);
    } catch (e) {
        res.status(500).send('No se pudo generar la imagen del QR.');
    }
});

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
