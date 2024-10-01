const WebSocket = require('ws');
const crypto = require('crypto');
const express = require('express');
const bodyParser = require('body-parser');
const app = express();
require('dotenv').config();

const apiKey = process.env.API_KEY;
const secretKey = process.env.SECRET_KEY;
const port = 8080;

const symbol = 'BTCUSD-2OCT24-58000-C';
let lastUpdated = null;
let orderBookData = null;
let heartbeatTimer = null;
let ws;

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

app.get('/getOrderBookOption', (req, res) => {
    res.send({
        success: true,
        data: orderBookData,
        updated_at: lastUpdated,
    });
});

function connect() {
    const ts = Date.now();
    const sign = coincallSignature(ts);
    const socket = `wss://ws.coincall.com/options?code=10&uuid=${apiKey}&ts=${ts}&sign=${sign}&apiKey=${apiKey}`;
    ws = new WebSocket(socket);

    ws.on('open', () => {
        const subscribe = {
            action: 'subscribe',
            dataType: 'orderBook',
            payload: { symbol: symbol }
        };
        ws.send(JSON.stringify(subscribe));
    });

    ws.on('message', (data) => {
        const message = JSON.parse(data);

        if (message.c === 20) {  // Filtra a mensagem do orderBook (descartando outras mensagens)
            updateOrderbook(message);
            console.log(JSON.stringify(message, null, 2)); // Atualização do Book
        } else if (message.c === 11) {
            console.log('Recebido batimento cardíaco, ignorando...'); // Envio ou Recebimento de sinal
        }

        resetHeartbeat();
    });

    ws.on('error', console.error);

    ws.on('close', () => {
        reconnect();
    });
}

/* Gerar Signature */
function coincallSignature(ts) {
    const verb = 'GET';
    const uri = '/users/self/verify';
    const auth = `${verb}${uri}?uuid=${apiKey}&ts=${ts}`;
    const signature = crypto.createHmac('sha256', secretKey)
        .update(auth)
        .digest('hex')
        .toUpperCase();

    return signature;
}

function updateOrderbook(message) {
    lastUpdated = formatDate(new Date());
    orderBookData = message;
}

/* Apenas formatação */
function formatDate(date) {
    let options = {
        hour: "numeric",
        minute: "numeric",
        second: "numeric",
    };
    return new Intl.DateTimeFormat('pt-BR', options).format(date);
}

function reconnect() {
    setTimeout(() => {
        console.log('Reconectando...');
        connect(symbol);
    }, 5000);
}

function sendHeartbeat() {
    const heartbeatMessage = {
        action: 'heartbeat'
    };
    ws.send(JSON.stringify(heartbeatMessage)); // Envia heartbeat e inicia um temporizador de 25 segundos
    heartbeatTimer = setTimeout(() => {
        console.error('Heartbeat não respondido. Reconectando...');
        ws.terminate(); // Se não for respondido, a conexão é reaberta iniciando um ciclo
        reconnect();
    }, 25000);
}

function resetHeartbeat() {
    if (heartbeatTimer) {
        clearTimeout(heartbeatTimer); // Reseta o temporizador a cada mensagem
    }

    heartbeatTimer = setTimeout(() => {
        sendHeartbeat();
    }, 25000);
}

connect();

app.listen(port, () => {
    console.log(`Servidor rodando na porta ${port}`);
});
