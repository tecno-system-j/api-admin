// Adaptado para hosting compartido (por ejemplo, AlwaysData u otro) como único script HTTP/WebSocket
// En hosting compartido usualmente NO se puede elegir el puerto, se usa process.env.PORT

const WebSocket = require('ws');
const http = require('http');

// Utilizar el puerto proporcionado por el entorno o por AlwaysData (variable de entorno PORT)
const PORT = process.env.PORT || 8081;

// Guardar referencias a los clientes WebSocket conectados
const wsClients = new Set();

// Un solo servidor HTTP que maneja tanto conexiones HTTP normales como el upgrade a WebSocket
const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/send-command') {
    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
    });
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        // Enviar comando a todos los clientes WebSocket conectados
        if (wsClients.size > 0) {
          wsClients.forEach(client => {
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
        res.end(JSON.stringify({ success: true, clients: wsClients.size }));
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

const wss = new WebSocket.Server({ noServer: true });

wss.on('connection', (ws) => {
  wsClients.add(ws);
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
    wsClients.delete(ws);
    console.log('Cliente WebSocket desconectado');
  });
  
  // Enviar mensaje de bienvenida
  ws.send(JSON.stringify({
    type: 'connected',
    message: 'Conectado al servidor de comandos'
  }));
});

// Manejo del upgrade a WebSocket solicitado por los clientes JS
server.on('upgrade', (request, socket, head) => {
  wss.handleUpgrade(request, socket, head, ws => {
    wss.emit('connection', ws, request);
  });
});

server.listen(PORT, () => {
  console.log('Servidor HTTP/WebSocket escuchando en puerto', PORT);
  console.log(`- HTTP endpoint: https://<tu-dominio-o-hosted-app>[:${PORT}]/send-command`);
  console.log(`- WebSocket endpoint: wss://<tu-dominio-o-hosted-app>[:${PORT}]`);
});

