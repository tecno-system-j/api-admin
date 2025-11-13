<?php
// public/index.php

require_once '../config/database.php';

// Obtener encabezados para determinar si es una petición API
$headers = getallheaders();
$isApiRequest = isset($headers['X-API-KEY']);

// Iniciar servidor WebSocket automáticamente si no está corriendo
function iniciarWebSocketServer() {
    $websocketDir = __DIR__ . '/../websocket';
    $serverFile = $websocketDir . '/server.js';
    $nodeModulesDir = $websocketDir . '/node_modules';
    $packageJson = $websocketDir . '/package.json';
    
    // Verificar si existe el directorio websocket
    if (!is_dir($websocketDir) || !file_exists($serverFile)) {
        return false; // No hay servidor WebSocket configurado
    }
    
    // Verificar si el servidor ya está corriendo
    $testUrl = 'http://192.168.128.15:8081/send-command';
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Si el servidor responde (cualquier código HTTP), está corriendo
    if ($httpCode > 0) {
        return true; // Servidor ya está corriendo
    }
    
    // El servidor no está corriendo, iniciarlo
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // Verificar si node_modules existe, si no, ejecutar npm install
    if (!is_dir($nodeModulesDir) && file_exists($packageJson)) {
        $npmCommand = $isWindows ? 'npm.cmd' : 'npm';
        
        if ($isWindows) {
            // Windows: ejecutar npm install en segundo plano
            $command = 'cd /d ' . escapeshellarg($websocketDir) . ' && start /B /MIN ' . $npmCommand . ' install > NUL 2>&1';
            pclose(popen($command, 'r'));
            // Esperar un poco más para que npm install comience
            sleep(2);
        } else {
            // Linux/Unix: ejecutar npm install
            $command = 'cd ' . escapeshellarg($websocketDir) . ' && ' . $npmCommand . ' install > /dev/null 2>&1 &';
            exec($command);
            sleep(2);
        }
        
        // Esperar a que node_modules se cree (máximo 10 segundos)
        // Si no se completa, el servidor se iniciará en el siguiente intento
        $maxWait = 10;
        $waited = 0;
        while (!is_dir($nodeModulesDir) && $waited < $maxWait) {
            usleep(500000); // 0.5 segundos
            $waited += 0.5;
        }
    }
    
    // Verificar que node_modules exista antes de iniciar el servidor
    if (!is_dir($nodeModulesDir)) {
        return false; // Las dependencias aún no están instaladas
    }
    
    // Iniciar el servidor en segundo plano
    $nodeCommand = $isWindows ? 'node.exe' : 'node';
    
    if ($isWindows) {
        // Windows: usar start /B para ejecutar en segundo plano
        // Cambiar al directorio websocket y ejecutar node server.js
        $command = 'cd /d ' . escapeshellarg($websocketDir) . ' && start /B ' . $nodeCommand . ' ' . escapeshellarg($serverFile) . ' > NUL 2>&1';
        pclose(popen($command, 'r'));
    } else {
        // Linux/Unix: usar nohup y &
        $command = 'cd ' . escapeshellarg($websocketDir) . ' && nohup ' . $nodeCommand . ' ' . escapeshellarg($serverFile) . ' > /dev/null 2>&1 &';
        exec($command);
    }
    
    // Esperar un momento para que el servidor inicie
    usleep(500000); // 0.5 segundos
    
    return true;
}

// Solo intentar iniciar el servidor si no es una petición API
// (para evitar iniciar el servidor en cada llamada API)
if (!$isApiRequest) {
    // Iniciar sesión solo para peticiones no-API (donde se necesita)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Intentar iniciar el servidor WebSocket (solo una vez por sesión)
    if (!isset($_SESSION['websocket_started'])) {
        iniciarWebSocketServer();
        $_SESSION['websocket_started'] = true;
    }
}

// Si tiene encabezado X-API-KEY, procesar como API
if ($isApiRequest) {
    header('Content-Type: application/json');

    if (!isset($headers['X-API-KEY']) || !in_array($headers['X-API-KEY'], $CONFIG['api_key'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Clave API no válida']);
        exit;
    }

    // Detectar endpoint y método desde la URL
    $requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $basePath = basename(dirname(__DIR__));
    $path = str_replace($basePath . '/', '', $requestUri);
    $pathParts = explode('/', $path);
    
    $endpoint = $pathParts[0] ?? '';
    $methodFromUrl = $pathParts[1] ?? null;
    
    // Obtener método HTTP
    $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $routeFile = __DIR__ . '/../routes/' . $endpoint . '.php';

    if (file_exists($routeFile)) {
        require $routeFile;
        
        // Instanciar la clase y obtener configuración de rutas
        $className = ucfirst($endpoint);
        if (class_exists($className)) {
            // Verificar si la clase tiene configuración de rutas
            if (!method_exists($className, 'getRoutes')) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'La clase ' . $className . ' debe implementar el método estático getRoutes()'
                ]);
                exit;
            }
            
            // Obtener configuración de rutas desde la clase
            $routesConfig = $className::getRoutes();
            
            // Validar que la configuración tenga la estructura correcta
            if (!is_array($routesConfig) || !isset($routesConfig['urlMethods'])) {
                http_response_code(500);
                echo json_encode([
                    'error' => 'Configuración de rutas inválida en ' . $className
                ]);
                exit;
            }
            
            $class = new $className();
            
            // Determinar qué método ejecutar según prioridad
            $method = null;
            
            // Prioridad 1: Método desde URL (ej: /usuarios/delete)
            if ($methodFromUrl) {
                // Validar que el método esté permitido en urlMethods
                if (in_array($methodFromUrl, $routesConfig['urlMethods'])) {
                    $method = $methodFromUrl;
                } else {
                    http_response_code(403);
                    echo json_encode([
                        'error' => 'Método no permitido: ' . $methodFromUrl,
                        'metodosPermitidos' => $routesConfig['urlMethods']
                    ]);
                    exit;
                }
            }
            // Prioridad 2: Parámetro GET 'metodo' (ej: ?metodo=getUsuario)
            elseif (isset($_GET['metodo'])) {
                $requestedMethod = $_GET['metodo'];
                // Validar que el método esté permitido
                if (in_array($requestedMethod, $routesConfig['urlMethods'])) {
                    $method = $requestedMethod;
                } else {
                    http_response_code(403);
                    echo json_encode([
                        'error' => 'Método no permitido: ' . $requestedMethod,
                        'metodosPermitidos' => $routesConfig['urlMethods']
                    ]);
                    exit;
                }
            }
            // Prioridad 3: Mapeo por método HTTP
            // NOTA: Si hay múltiples métodos que usan el mismo HTTP (ej: varios GET),
            // el httpMethodMap solo puede mapear a UNO. Los demás deben especificarse en URL.
            elseif (isset($routesConfig['httpMethodMap'][$httpMethod])) {
                $mappedMethod = $routesConfig['httpMethodMap'][$httpMethod];
                
                // Validar que el método mapeado esté permitido
                if (!in_array($mappedMethod, $routesConfig['urlMethods'])) {
                    http_response_code(403);
                    echo json_encode([
                        'error' => 'Método HTTP ' . $httpMethod . ' no está configurado correctamente',
                        'metodosPermitidos' => $routesConfig['urlMethods']
                    ]);
                    exit;
                }
                
                $method = $mappedMethod;
            }
            // Prioridad 4: Método por defecto
            elseif (isset($routesConfig['defaultMethod'])) {
                $method = $routesConfig['defaultMethod'];
            }
            
            // Si no se pudo determinar el método
            if (!$method) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'No se pudo determinar el método a ejecutar',
                    'metodosPermitidos' => $routesConfig['urlMethods'],
                    'sugerencia' => 'Especifica un método en la URL (ej: /' . $endpoint . '/metodo) o usa el parámetro ?metodo=nombre',
                    'ejemplos' => [
                        'URL' => '/' . $endpoint . '/[metodo]',
                        'Parametro' => '?metodo=[metodo]',
                        'HTTP' => 'Si está configurado en httpMethodMap, usa el método HTTP correspondiente'
                    ]
                ]);
                exit;
            }
            
            // Verificar que el método exista en la clase
            if (!method_exists($class, $method)) {
                http_response_code(404);
                echo json_encode([
                    'error' => 'Método no encontrado: ' . $method,
                    'metodosDisponibles' => array_filter(
                        get_class_methods($class),
                        function($m) use ($routesConfig) {
                            return in_array($m, $routesConfig['urlMethods']);
                        }
                    )
                ]);
                exit;
            }
            
            // Ejecutar el método
            $class->$method();
            
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Clase no encontrada: ' . $className]);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado']);
    }

    exit;
}

// Si no hay header X-API-KEY, mostrar interfaz bonita (modo visual)
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>API Administración - Panel de Control</title>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: #333;
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }
    
    body::before {
        content: '';
        position: fixed;
        width: 100%;
        height: 100%;
        background: 
            radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        animation: float 15s ease-in-out infinite;
        pointer-events: none;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
    
    header {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 40px 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 10;
    }
    
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .header-left {
        flex: 1;
    }
    
    .header-title {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .rocket {
        font-size: 3rem;
        display: inline-block;
        animation: rocket 2s ease-in-out infinite;
    }
    
    @keyframes rocket {
        0%, 100% { transform: translateY(0px) rotate(-45deg); }
        50% { transform: translateY(-10px) rotate(-45deg); }
    }
    
    .header-subtitle {
        color: #666;
        font-size: 1.1rem;
        font-weight: 400;
    }
    
    .header-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
    
    .btn-secondary {
        background: white;
        color: #667eea;
        border: 2px solid #667eea;
    }
    
    .btn-secondary:hover {
        background: #667eea;
        color: white;
    }
    
    .container {
        max-width: 1400px;
        margin: 60px auto;
        padding: 0 20px;
        position: relative;
        z-index: 1;
    }
    
    .intro {
        text-align: center;
        margin-bottom: 50px;
        color: white;
    }
    
    .intro h2 {
        font-size: 2rem;
        margin-bottom: 15px;
        font-weight: 700;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .intro p {
        font-size: 1.1rem;
        opacity: 0.95;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }
    
    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 60px;
    }
    
    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.3s ease;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .stat-change {
        font-size: 0.85rem;
        margin-top: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }
    
    .stat-change.positive {
        color: #10b981;
    }
    
    .stat-change.negative {
        color: #ef4444;
    }
    
    .section-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 30px;
        text-align: center;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }
    
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-bottom: 60px;
    }
    
    .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 35px;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    
    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    
    .card:hover::before {
        transform: scaleX(1);
    }
    
    .card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }
    
    .card-icon {
        font-size: 3rem;
        margin-bottom: 20px;
        display: block;
        transition: all 0.3s ease;
    }
    
    .card:hover .card-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 10px;
    }
    
    .card-description {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    
    .card-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #667eea;
        font-weight: 600;
        font-size: 1rem;
        transition: gap 0.3s ease;
    }
    
    .card:hover .card-link {
        gap: 12px;
    }
    
    .arrow {
        transition: transform 0.3s ease;
    }
    
    .card:hover .arrow {
        transform: translateX(5px);
    }
    
    .activity-section {
        margin-bottom: 60px;
    }
    
    .activity-log {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    }
    
    .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .activity-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
    }
    
    .activity-filter {
        display: flex;
        gap: 10px;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #666;
    }
    
    .filter-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .activity-item {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        background: #f9fafb;
        transition: all 0.3s ease;
    }
    
    .activity-item:hover {
        background: #f3f4f6;
        transform: translateX(5px);
    }
    
    .activity-icon {
        font-size: 2rem;
        min-width: 50px;
        text-align: center;
    }
    
    .activity-content {
        flex: 1;
    }
    
    .activity-text {
        color: #333;
        font-weight: 500;
        margin-bottom: 5px;
    }
    
    .activity-time {
        color: #999;
        font-size: 0.85rem;
    }
    
    .system-health {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        margin-bottom: 60px;
    }
    
    .health-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 25px;
    }
    
    .health-item {
        margin-bottom: 20px;
    }
    
    .health-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        color: #666;
        font-size: 0.9rem;
    }
    
    .health-bar {
        height: 10px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    
    .health-progress {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        transition: width 1s ease;
        position: relative;
        overflow: hidden;
    }
    
    .health-progress::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        animation: shimmer 2s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    footer {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 30px 20px;
        box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.1);
        position: relative;
        z-index: 10;
    }
    
    .footer-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        color: #666;
    }
    
    .footer-info {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
    }
    
    .footer-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
    }
    
    .footer-item strong {
        color: #667eea;
        font-weight: 600;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #10b981;
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-dot {
        width: 8px;
        height: 8px;
        background: white;
        border-radius: 50%;
        animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .quick-actions {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        margin-bottom: 60px;
    }
    
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    
    .quick-action-btn {
        padding: 20px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .quick-action-btn:hover {
        border-color: #667eea;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
    }
    
    .quick-action-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    
    .quick-action-label {
        font-size: 0.9rem;
        color: #666;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
        }
        
        .header-title {
            font-size: 2rem;
        }
        
        .intro h2 {
            font-size: 1.5rem;
        }
        
        .grid {
            grid-template-columns: 1fr;
        }
        
        .footer-content {
            flex-direction: column;
            text-align: center;
        }
        
        .footer-info {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="header-left">
            <h1 class="header-title">
                <span class="rocket">🚀</span>
                API Administración
            </h1>
            <p class="header-subtitle">Sistema de gestión y control centralizado</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="refreshDashboard()">
                <span>🔄</span> Actualizar
            </button>
            <button class="btn btn-primary" onclick="showNotifications()">
                <span>🔔</span> Notificaciones <span id="notif-count" style="background: white; color: #667eea; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">3</span>
            </button>
        </div>
    </div>
</header>

<div class="container">
    <div class="intro">
        <h2>Panel de Control</h2>
        <p>Monitorea el rendimiento y accede a todos los módulos del sistema</p>
    </div>
    
    <div class="stats">
        <div class="stat-card" onclick="showModuleDetails('modules')">
            <div class="stat-icon">📦</div>
            <div class="stat-value" id="module-count">4</div>
            <div class="stat-label">Módulos Activos</div>
            <div class="stat-change positive">
                <span>↑</span> 100% operativos
            </div>
        </div>
        <div class="stat-card" onclick="showModuleDetails('php')">
            <div class="stat-icon">🐘</div>
            <div class="stat-value" id="php-version">...</div>
            <div class="stat-label">PHP Version</div>
            <div class="stat-change positive">
                <span>✓</span> Actualizado
            </div>
        </div>
        <div class="stat-card" onclick="showModuleDetails('requests')">
            <div class="stat-icon">📊</div>
            <div class="stat-value" id="request-count">0</div>
            <div class="stat-label">Solicitudes/Hora</div>
            <div class="stat-change positive">
                <span>↑</span> <span id="request-change">+12%</span>
            </div>
        </div>
        <div class="stat-card" onclick="showModuleDetails('uptime')">
            <div class="stat-icon">⏱️</div>
            <div class="stat-value" id="uptime">99.9%</div>
            <div class="stat-label">Uptime</div>
            <div class="stat-change positive">
                <span>✓</span> Estable
            </div>
        </div>
        <div class="stat-card" onclick="showModuleDetails('memory')">
            <div class="stat-icon">💾</div>
            <div class="stat-value" id="memory-usage">0</div>
            <div class="stat-label">Memoria (MB)</div>
            <div class="stat-change positive">
                <span>↓</span> <span id="memory-change">Normal</span>
            </div>
        </div>
        <div class="stat-card" onclick="showModuleDetails('response')">
            <div class="stat-icon">⚡</div>
            <div class="stat-value" id="response-time">0ms</div>
            <div class="stat-label">Tiempo Respuesta</div>
            <div class="stat-change positive">
                <span>↓</span> Óptimo
            </div>
        </div>
    </div>
    
    <div class="system-health">
        <h3 class="health-title">🏥 Estado del Sistema</h3>
        <div class="health-item">
            <div class="health-label">
                <span>CPU</span>
                <span id="cpu-percent">0%</span>
            </div>
            <div class="health-bar">
                <div class="health-progress" id="cpu-bar" style="width: 0%"></div>
            </div>
        </div>
        <div class="health-item">
            <div class="health-label">
                <span>Memoria RAM</span>
                <span id="ram-percent">0%</span>
            </div>
            <div class="health-bar">
                <div class="health-progress" id="ram-bar" style="width: 0%"></div>
            </div>
        </div>
        <div class="health-item">
            <div class="health-label">
                <span>Disco</span>
                <span id="disk-percent">0%</span>
            </div>
            <div class="health-bar">
                <div class="health-progress" id="disk-bar" style="width: 0%"></div>
            </div>
        </div>
        <div class="health-item">
            <div class="health-label">
                <span>Conexiones Activas</span>
                <span id="connections-percent">0%</span>
            </div>
            <div class="health-bar">
                <div class="health-progress" id="connections-bar" style="width: 0%"></div>
            </div>
        </div>
    </div>
    
    <h3 class="section-title">⚡ Acciones Rápidas</h3>
    <div class="quick-actions">
        <div class="quick-actions-grid">
            <div class="quick-action-btn" onclick="clearCache()">
                <div class="quick-action-icon">🧹</div>
                <div class="quick-action-label">Limpiar Cache</div>
            </div>
            <div class="quick-action-btn" onclick="viewLogs()">
                <div class="quick-action-icon">📋</div>
                <div class="quick-action-label">Ver Logs</div>
            </div>
            <div class="quick-action-btn" onclick="runBackup()">
                <div class="quick-action-icon">💾</div>
                <div class="quick-action-label">Backup</div>
            </div>
            <div class="quick-action-btn" onclick="checkUpdates()">
                <div class="quick-action-icon">🔄</div>
                <div class="quick-action-label">Actualizaciones</div>
            </div>
            <div class="quick-action-btn" onclick="viewDatabase()">
                <div class="quick-action-icon">🗄️</div>
                <div class="quick-action-label">Base de Datos</div>
            </div>
            <div class="quick-action-btn" onclick="viewSettings()">
                <div class="quick-action-icon">⚙️</div>
                <div class="quick-action-label">Configuración</div>
            </div>
        </div>
    </div>
    
    <h3 class="section-title">📂 Módulos del Sistema</h3>
    <div class="grid">
        <div class="card" onclick="window.location.href='../config/'">
            <span class="card-icon">⚙️</span>
            <h3 class="card-title">Config</h3>
            <p class="card-description">Configuración del sistema, variables de entorno y parámetros generales</p>
            <a href="../config/" class="card-link">
                Acceder <span class="arrow">→</span>
            </a>
        </div>
        
        <div class="card" onclick="window.location.href='../core/'">
            <span class="card-icon">🔧</span>
            <h3 class="card-title">Core</h3>
            <p class="card-description">Núcleo del sistema, funciones principales y lógica de negocio</p>
            <a href="../core/" class="card-link">
                Acceder <span class="arrow">→</span>
            </a>
        </div>
        
        <div class="card" onclick="window.location.href='../routes/'">
            <span class="card-icon">🛣️</span>
            <h3 class="card-title">Routes</h3>
            <p class="card-description">Gestión de rutas, endpoints y controladores de la API</p>
            <a href="../routes/" class="card-link">
                Acceder <span class="arrow">→</span>
            </a>
        </div>
        
        <div class="card" onclick="window.location.href='../public/'">
            <span class="card-icon">🌐</span>
            <h3 class="card-title">Public</h3>
            <p class="card-description">Recursos públicos, assets y archivos estáticos del sistema</p>
            <a href="../public/" class="card-link">
                Acceder <span class="arrow">→</span>
            </a>
        </div>
    </div>
    
    <div class="activity-section">
        <div class="activity-log">
            <div class="activity-header">
                <h3 class="activity-title">📜 Actividad Reciente</h3>
                <div class="activity-filter">
                    <button class="filter-btn active" onclick="filterActivity('all')">Todas</button>
                    <button class="filter-btn" onclick="filterActivity('success')">Éxito</button>
                    <button class="filter-btn" onclick="filterActivity('warning')">Alertas</button>
                </div>
            </div>
            <div id="activity-list">
                <!-- Se llenará dinámicamente -->
            </div>
        </div>
    </div>
</div>

<footer>
    <div class="footer-content">
        <div class="footer-info">
            <div class="footer-item">
                <span>🖥️</span>
                <span><strong>Servidor:</strong> <span id="server-name">...</span></span>
            </div>
            <div class="footer-item">
                <span>🐘</span>
                <span><strong>Apache + PHP</strong> <span id="php-ver">...</span></span>
            </div>
            <div class="footer-item">
                <span>📅</span>
                <span><strong>Última actualización:</strong> <span id="last-update">...</span></span>
            </div>
        </div>
        <div class="status-badge">
            <span class="status-dot"></span>
            Sistema Activo
        </div>
    </div>
</footer>

<script>
    // Datos del sistema (en producción vendrían del servidor)
    let systemData = {
        phpVersion: '8.2.0',
        serverName: window.location.hostname,
        requestsPerHour: 0,
        memoryUsage: 0,
        responseTime: 0,
        cpu: 0,
        ram: 0,
        disk: 0,
        connections: 0
    };
    
    // Actividades del sistema
    const activities = [
        { icon: '✅', text: 'Sistema iniciado correctamente', time: 'Hace 2 horas', type: 'success' },
        { icon: '📝', text: 'Configuración actualizada en módulo Config', time: 'Hace 3 horas', type: 'success' },
        { icon: '🔄', text: 'Cache limpiado automáticamente', time: 'Hace 5 horas', type: 'success' },
        { icon: '⚠️', text: 'Advertencia: Uso de memoria elevado', time: 'Hace 6 horas', type: 'warning' },
        { icon: '💾', text: 'Backup completado exitosamente', time: 'Hace 12 horas', type: 'success' },
        { icon: '🔐', text: 'Nuevo usuario autenticado', time: 'Hace 1 día', type: 'success' }
    ];
    
    // Inicializar dashboard
    function initDashboard() {
        // Simular datos del servidor
        systemData.requestsPerHour = Math.floor(Math.random() * 500) + 100;
        systemData.memoryUsage = Math.floor(Math.random() * 200) + 50;
        systemData.responseTime = Math.floor(Math.random() * 100) + 20;
        systemData.cpu = Math.floor(Math.random() * 60) + 20;
        systemData.ram = Math.floor(Math.random() * 70) + 30;
        systemData.disk = Math.floor(Math.random() * 50) + 25;
        systemData.connections = Math.floor(Math.random() * 40) + 10;
        
        updateMetrics();
        loadActivities();
        updateSystemHealth();
        
        // Actualizar cada 5 segundos
        setInterval(() => {
            updateMetrics();
            updateSystemHealth();
        }, 5000);
    }
    
    // Actualizar métricas
    function updateMetrics() {
        // Simular cambios en las métricas
        systemData.requestsPerHour += Math.floor(Math.random() * 20) - 10;
        systemData.requestsPerHour = Math.max(50, systemData.requestsPerHour);
        
        systemData.memoryUsage += Math.floor(Math.random() * 10) - 5;
        systemData.memoryUsage = Math.max(50, Math.min(300, systemData.memoryUsage));
        
        systemData.responseTime += Math.floor(Math.random() * 10) - 5;
        systemData.responseTime = Math.max(15, Math.min(150, systemData.responseTime));
        
        // Actualizar UI
        document.getElementById('php-version').textContent = systemData.phpVersion;
        document.getElementById('php-ver').textContent = systemData.phpVersion;
        document.getElementById('server-name').textContent = systemData.serverName;
        document.getElementById('request-count').textContent = systemData.requestsPerHour.toLocaleString();
        document.getElementById('memory-usage').textContent = systemData.memoryUsage;
        document.getElementById('response-time').textContent = systemData.responseTime + 'ms';
        
        // Actualizar última actualización
        const now = new Date();
        document.getElementById('last-update').textContent = now.toLocaleTimeString('es-ES');
    }
    
    // Actualizar estado del sistema
    function updateSystemHealth() {
        // Simular cambios en recursos del sistema
        systemData.cpu += Math.floor(Math.random() * 10) - 5;
        systemData.cpu = Math.max(10, Math.min(90, systemData.cpu));
        
        systemData.ram += Math.floor(Math.random() * 10) - 5;
        systemData.ram = Math.max(20, Math.min(85, systemData.ram));
        
        systemData.disk += Math.floor(Math.random() * 2) - 1;
        systemData.disk = Math.max(20, Math.min(80, systemData.disk));
        
        systemData.connections += Math.floor(Math.random() * 6) - 3;
        systemData.connections = Math.max(5, Math.min(60, systemData.connections));
        
        // Actualizar barras de progreso
        document.getElementById('cpu-percent').textContent = systemData.cpu + '%';
        document.getElementById('cpu-bar').style.width = systemData.cpu + '%';
        
        document.getElementById('ram-percent').textContent = systemData.ram + '%';
        document.getElementById('ram-bar').style.width = systemData.ram + '%';
        
        document.getElementById('disk-percent').textContent = systemData.disk + '%';
        document.getElementById('disk-bar').style.width = systemData.disk + '%';
        
        document.getElementById('connections-percent').textContent = systemData.connections + '%';
        document.getElementById('connections-bar').style.width = systemData.connections + '%';
    }
    
    // Cargar actividades
    function loadActivities() {
        const activityList = document.getElementById('activity-list');
        activityList.innerHTML = '';
        
        activities.forEach(activity => {
            const item = document.createElement('div');
            item.className = 'activity-item';
            item.setAttribute('data-type', activity.type);
            item.innerHTML = `
                <div class="activity-icon">${activity.icon}</div>
                <div class="activity-content">
                    <div class="activity-text">${activity.text}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            `;
            activityList.appendChild(item);
        });
    }
    
    // Filtrar actividades
    function filterActivity(type) {
        const items = document.querySelectorAll('.activity-item');
        const buttons = document.querySelectorAll('.filter-btn');
        
        buttons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        items.forEach(item => {
            if (type === 'all') {
                item.style.display = 'flex';
            } else {
                item.style.display = item.getAttribute('data-type') === type ? 'flex' : 'none';
            }
        });
    }
    
    // Acciones rápidas
    function clearCache() {
        if (confirm('¿Deseas limpiar el caché del sistema?')) {
            // Simular limpieza
            showNotification('✅ Cache limpiado exitosamente', 'success');
            addActivity('🧹', 'Cache limpiado manualmente', 'Ahora mismo', 'success');
        }
    }
    
    function viewLogs() {
        showNotification('📋 Abriendo visor de logs...', 'info');
        setTimeout(() => {
            alert('Funcionalidad de logs: Aquí se mostrarían los logs del sistema');
        }, 500);
    }
    
    function runBackup() {
        if (confirm('¿Iniciar backup del sistema?')) {
            showNotification('💾 Backup iniciado...', 'info');
            setTimeout(() => {
                showNotification('✅ Backup completado', 'success');
                addActivity('💾', 'Backup manual completado', 'Ahora mismo', 'success');
            }, 2000);
        }
    }
    
    function checkUpdates() {
        showNotification('🔄 Verificando actualizaciones...', 'info');
        setTimeout(() => {
            showNotification('✅ Sistema actualizado', 'success');
        }, 1500);
    }
    
    function viewDatabase() {
        showNotification('🗄️ Conectando a base de datos...', 'info');
        setTimeout(() => {
            alert('Gestor de base de datos: Aquí se mostraría el panel de administración');
        }, 500);
    }
    
    function viewSettings() {
        window.location.href = '../config/';
    }
    
    // Mostrar notificaciones
    function showNotifications() {
        alert('Tienes 3 notificaciones:\n\n1. ✅ Sistema actualizado\n2. ⚠️ Revisar uso de memoria\n3. 📊 Reporte mensual disponible');
    }
    
    // Agregar actividad
    function addActivity(icon, text, time, type) {
        activities.unshift({ icon, text, time, type });
        if (activities.length > 10) activities.pop();
        loadActivities();
    }
    
    // Mostrar notificación temporal
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : '#667eea'};
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideIn 0.3s ease;
            font-weight: 600;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    // Refrescar dashboard
    function refreshDashboard() {
        showNotification('🔄 Actualizando datos...', 'info');
        updateMetrics();
        updateSystemHealth();
        setTimeout(() => {
            showNotification('✅ Dashboard actualizado', 'success');
        }, 1000);
    }
    
    // Mostrar detalles de módulo
    function showModuleDetails(module) {
        const details = {
            modules: 'Todos los módulos están operativos y funcionando correctamente',
            php: `PHP ${systemData.phpVersion} - Apache/2.4 - Última actualización hace 15 días`,
            requests: `${systemData.requestsPerHour} solicitudes procesadas en la última hora`,
            uptime: 'Sistema operativo sin interrupciones durante 30 días consecutivos',
            memory: `Uso actual: ${systemData.memoryUsage}MB - Límite: 512MB`,
            response: `Tiempo promedio de respuesta: ${systemData.responseTime}ms - Óptimo`
        };
        
        alert(details[module] || 'Información no disponible');
    }
    
    // Prevenir que el clic en el link dispare el onclick del card
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.card-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });
    });
    
    // Agregar animaciones CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Inicializar al cargar la página
    initDashboard();
</script>
</body>
</html>
