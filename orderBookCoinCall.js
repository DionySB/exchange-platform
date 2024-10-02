const WebSocket = require('ws');
const crypto = require('crypto');
const express = require('express');
const bodyParser = require('body-parser');
require('dotenv').config();

const app = express();
const apiKey = process.env.API_KEY;
const secretKey = process.env.SECRET_KEY;
const port = 8080;
let socket;

let orderBookDataOptions = null;
let orderBookDataFutures = null;
let orderBookDataSpots = null;
let heartbeatTimerOptions = null;
let heartbeatTimerFutures = null;
let heartbeatTimerSpots = null;

let wsOptions;
let wsFutures;
let wsSpots;

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

app.post('/getOrderBook', (req, res) => {
    const market = req.body.market;
    if(market === 'options'){
        res.send({
            success: true,
            data: orderBookDataOptions,  
        });
    }else if(market === 'futures'){
        res.send({
            success: true,
            data: orderBookDataFutures
        });
    }else if(market === 'spots'){
        res.send({
            success: true,
            data: orderBookDataSpots,
        });
    }else if(market === 'all'){
        res.send({
            sucess: true,
            data: {orderBookDataOptions, orderBookDataFutures, orderBookDataSpots}
        })
    }else{
        res.send({
            sucess: false,
            message: 'Tipo de market inválido. Market: (Spot/Futures/Options/All)'
        })
    }
});

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

function connect(symbol, type) {
    const ts = Date.now();
    const sign = coincallSignature(ts);
    let ws;

    if(type != 'spots'){
        socket = `wss://ws.coincall.com/${type}?code=10&uuid=${apiKey}&ts=${ts}&sign=${sign}&apiKey=${apiKey}`;
        ws = new WebSocket(socket);
        ws.on('open', () => {
            const subscribe = {
                action: 'subscribe',
                dataType: 'orderBook',
                payload: { symbol: symbol }
            };
            ws.send(JSON.stringify(subscribe));
        });
    }else{
        socket = `wss://ws.coincall.com/spot/ws`;
        ws = new WebSocket(socket);
        const step = 1;
        ws.on('open', () => {
            const subscribe = {
                sub: `market.${symbol}.mbp.${step}`,
                id: `${ts}`,
            };
            ws.send(JSON.stringify(subscribe));
        });
    }

    ws.on('message', (data) => {
        const message = JSON.parse(data);

        if (message.dt === 5){
            updateOrderbook(message, 'options');
            console.log('Orderbook Options Atualizado:', JSON.stringify(message, null, 2));
        }else if(message.dt === 32){
            updateOrderbook(message, 'futures');
            console.log('Orderbook Futures Atualizado:', JSON.stringify(message, null, 2));
        }else if(message.status === 'ok'){
            updateOrderbook(message, 'spots');
            console.log('Orderbook Spots Atualizado:', JSON.stringify(message, null, 2));
        }

        /* Filtro de mensagens esperadas */
        if(message.c === 11){
            console.log('Recebido batimento cardíaco, ignorando...');
        }

        resetHeartbeat(type);
    });

    ws.on('error', console.error);

    ws.on('close', () => {
        console.log(`Conexão com ${type} fechada. Tentando reconectar...`);
        reconnect(symbol, type);
    });

    return ws;
}

function updateOrderbook(message, type) {
    if(type === 'options'){
        orderBookDataOptions = message;
    }else if(type === 'futures'){
        orderBookDataFutures = message;
    }else{
        orderBookDataSpots = message;
    }
}

function reconnect(symbol, type) {
    setTimeout(() => {
        console.log(`Reconectando ${type}...`);
        if(type === 'options'){
            wsOptions = connect(symbol, type);
        }else if (type === 'futures'){
            wsFutures = connect(symbol, type);
        }else{
            wsSpots = connect(symbol, type);
        }
    }, 5000);
}

function sendHeartbeat(type) {
    const heartbeatMessage = { action: 'heartbeat' }
    if(type === 'options' && wsOptions.readyState === WebSocket.OPEN){
        wsOptions.send(JSON.stringify(heartbeatMessage));
    }else if(type === 'futures' && wsFutures.readyState === WebSocket.OPEN){
        wsFutures.send(JSON.stringify(heartbeatMessage));
    }else if(type === 'spots' && wsSpots.readyState === WebSocket.OPEN){
        wsSpots.send(JSON.stringify(heartbeatMessage));
    }
}

function resetHeartbeat(type) {
    if(type === 'options'){
        if(heartbeatTimerOptions) {
            clearTimeout(heartbeatTimerOptions);
        }
        heartbeatTimerOptions = setTimeout(() => {
            sendHeartbeat('options');
        }, 25000);
    }else if (type === 'futures'){
        if(heartbeatTimerFutures){
            clearTimeout(heartbeatTimerFutures);
        }
        heartbeatTimerFutures = setTimeout(() => {
            sendHeartbeat('futures');
        }, 25000);
    }else{
        if(heartbeatTimerSpots){
            clearTimeout(heartbeatTimerSpots);
        }
        heartbeatTimerSpots = setTimeout(() => {
            sendHeartbeat('spots');
        }, 25000);
    }
}

wsOptions = connect('BTCUSD-26SEP25-75000-P', 'options');
wsFutures = connect('BTCUSD', 'futures');
wsSpots = connect('BTCUSDT', 'spots');

app.listen(port);
