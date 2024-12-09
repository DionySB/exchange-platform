const WebSocket = require('ws');
const jwt = require('jsonwebtoken');
const express = require('express');
require('dotenv').config();
const app = express();
const port = 3000;

const keyword = process.env.JWT_KEYWORD;

// Gera o token com base no ID do usuário
const generateToken = (userId) => {
  return jwt.sign({ userId }, keyword, { expiresIn: '1h' });
};
console.log(keyword);
// verifica o token
const verifyToken = (token) => {
  try {
    return jwt.verify(token, keyword);
  } catch (e) {
    return null;
  }
};

const wss = new WebSocket.Server({ noServer: true });

// Simulação de armazenamento simples para conexoes 
const userConnections = {};

//A connection recebe o token presente no header (da conexao ws)
wss.on('connection', (ws, req) => {
  const token = req.headers['sec-websocket-protocol'];  // O token vem nos headers
  const decoded = verifyToken(token);

  if (!decoded) {
    ws.close(1008, 'Token inválido ou expirado');
    return;
  }

  const userId = decoded.userId;

  // Salva a conexão do usuário 
  userConnections[userId] = ws;

  console.log(`Usuário ${userId} conectado`);

  ws.on('message', (message) => {
    console.log(`Mensagem recebida de ${userId}: ${message}`);
  });

  ws.on('close', () => {
    console.log(`Usuário ${userId} desconectado`);
    delete userConnections[userId];
  });
});

// Rota HTTP 
app.server = app.listen(port, () => {
  console.log(`Servidor ouvindo na porta ${port}`);
});

app.server.on('upgrade', (request, socket, head) => {
  wss.handleUpgrade(request, socket, head, (ws) => {
    wss.emit('connection', ws, request);
  });
});

//Fiz um exemplo, quando o usuário loga recebe um token baseado no seu id
app.get('/login/:userId', (req, res) => {
  const userId = req.params.userId;
  const token = generateToken(userId);
  res.json({ token });
});

// o usuário com um determinado ID recebe uma notificação
app.post('/sendNotification', express.json(), (req, res) => {
    const { userId, message } = req.body;
    
    const ws = userConnections[userId];
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ message })); 
      res.json({ status: 'Mensagem enviada' });
    } else {
      res.status(404).json({ error: 'Usuário não conectado' });
    }
  });
  
