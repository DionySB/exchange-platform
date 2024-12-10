const io = require('socket.io-client');

const socket = io.connect('http://localhost:3000', {
   auth: { token: 'gerado ' }

});

  socket.on('notification', (data) => {
console.log('Notificação recebida:', data);

console.log('Saldo atualizado:', data.balance);
console.log('Extrato atualizado:', data.statement);
});

socket.on('connect_error', (error) => {
console.error('Erro de conexão:', error.message);
alert('Erro de autenticação: Token inválido');
});
