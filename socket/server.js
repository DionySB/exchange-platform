const io = require('socket.io')();
const jwt = require('jsonwebtoken');
const SECRET_KEY = 'nebula';

const users = {
  '12345': {
    name: 'Diony',
    balance: 1000.00,
    statement: [
      { date: '2024-12-05', description: 'Depósito', amount: 200 }
    ]
  },
  '54321': {
    name: 'Luccas',
    balance: 500.00,
    statement: [
      { date: '2024-12-06', description: 'Depósito', amount: 300 }
    ]
  }
};
/*
gerar token pra teste
  const generateToken = (userId) => {
    const user = users[userId];
    if (!user) {
      console.log(`Usuário com ID ${userId} não encontrado.`);
      return;
    }

    const payload = {
      userId,
      name: user.name,
      balance: user.balance,
      statement: user.statement
    };

    const token = jwt.sign(payload, SECRET_KEY, { expiresIn: '1h' });
    return token;
  };

  const tokenDiony = generateToken('12345');
  const tokenLuccas = generateToken('54321');

  console.log(tokenDiony, ' xxxxxxx ' , tokenLuccas);
*/
//  autenticação com JWT
io.use((socket, next) => {
  const token = socket.handshake.auth.token;
  if (token) {
    jwt.verify(token, SECRET_KEY, (err, decoded) => {
      if (err) return next(new Error('Authentication error')); // Se o token for inválido
      socket.decoded = decoded;
      next();
    });
  } else {
    next(new Error('Authentication error'));
  }
});

// Evento de conexão
io.on('connection', (socket) => {
  const { userId } = socket.decoded; // userId do token

  // Verifica se o usuário existe no banco de dados
  const user = users[userId];
  if (!user) {
    socket.disconnect();
    return console.log('Usuário não encontrado');
  }

  console.log(`Usuário conectado: ${userId}`);

  socket.join(userId);

  setInterval(() => {
    io.to(userId).emit('notification', {
      balance: user.balance,
      statement: user.statement
    });
  }, 50000);

  socket.on('disconnect', () => {
    console.log(`Usuário desconectado: ${userId}`);
  });
});

io.listen(3000, () => {
  console.log('Servidor WebSocket rodando na porta 3000');
});
