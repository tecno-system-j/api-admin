// Servidor WebSocket simple para comunicación entre PHP y JavaScript
const WebSocket = require('ws');
const http = require('http');

const PORT = 8081;
const WS_PORT = 8082;

// Servidor HTTP para recibir comandos del PHP
const httpServer = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/send-command') {
    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
    });
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        // Enviar comando a todos los clientes WebSocket conectados
        if (wss.clients.size > 0) {
          wss.clients.forEach(client => {
            if (client.readyState === WebSocket.OPEN) {
              client.send(JSON.stringify({
                type: 'command',
                command: data.command,
                timestamp: Date.now()
              }));
            }
          });
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, clients: wss.clients.size }));
      } catch (e) {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON' }));
      }
    });
  } else {
    res.writeHead(404);
    res.end('Not found');
  }
});

httpServer.listen(PORT, () => {
  console.log(`HTTP Server escuchando en puerto ${PORT} para recibir comandos del PHP`);
});

// Servidor WebSocket para clientes JavaScript
const wss = new WebSocket.Server({ port: WS_PORT }, () => {
  console.log(`WebSocket Server escuchando en puerto ${WS_PORT} para clientes`);
});

wss.on('connection', (ws) => {
  console.log('Cliente WebSocket conectado');
  
  ws.on('message', (message) => {
    try {
      const data = JSON.parse(message);
      if (data.type === 'ping') {
        ws.send(JSON.stringify({ type: 'pong' }));
      }
    } catch (e) {
      console.error('Error procesando mensaje:', e);
    }
  });
  
  ws.on('close', () => {
    console.log('Cliente WebSocket desconectado');
  });
  
  // Enviar mensaje de bienvenida
  ws.send(JSON.stringify({
    type: 'connected',
    message: 'Conectado al servidor de comandos'
  }));
});

console.log('Servidor WebSocket iniciado');
console.log(`- HTTP endpoint: http://apiadmin.alwaysdata.net:${PORT}/send-command`);
console.log(`- WebSocket endpoint: ws://apiadmin.alwaysdata.net:${WS_PORT}`);

