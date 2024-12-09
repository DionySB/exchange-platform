const WebSocket = require('ws');

const token = "";
const ws = new WebSocket('ws://localhost:3000', token);

ws.on('open', () => {
  console.log('Conectado ao WebSocket');
});

ws.on('message', (message) => {
    const parsedMessage = JSON.parse(message);
    console.log(`Mensagem recebida:`, parsedMessage);
});
