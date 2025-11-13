# Servidor WebSocket para Comandos

Este servidor WebSocket permite la comunicación en tiempo real entre PHP (servidor de API) y JavaScript (cliente frontend).

## Instalación

1. Instala Node.js si no lo tienes instalado
2. Navega a la carpeta websocket:
   ```bash
   cd websocket
   ```

3. Instala las dependencias:
   ```bash
   npm install
   ```

## Uso

1. Inicia el servidor:
   ```bash
   npm start
   ```
   O directamente:
   ```bash
   node server.js
   ```

2. El servidor iniciará en:
   - HTTP: `http://localhost:8080` (para recibir comandos del PHP)
   - WebSocket: `ws://localhost:8081` (para clientes JavaScript)

## Configuración

Si necesitas cambiar los puertos, edita `server.js`:
- `PORT`: Puerto HTTP para recibir comandos del PHP (default: 8080)
- `WS_PORT`: Puerto WebSocket para clientes (default: 8081)

## Funcionamiento

1. **PHP envía comandos**: El PHP hace POST a `http://localhost:8080/send-command` con el comando JavaScript
2. **Servidor distribuye**: El servidor WebSocket envía el comando a todos los clientes conectados
3. **JavaScript ejecuta**: Los clientes reciben el comando y lo ejecutan automáticamente

## Ejecutar como servicio (Windows)

Para ejecutar el servidor como servicio en Windows, puedes usar `pm2`:

```bash
npm install -g pm2
pm2 start server.js --name websocket-server
pm2 save
pm2 startup
```

## Ejecutar como servicio (Linux)

```bash
npm install -g pm2
pm2 start server.js --name websocket-server
pm2 save
pm2 startup systemd
```

