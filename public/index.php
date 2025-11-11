<?php
// public/index.php

require_once '../config/database.php';

// Obtener encabezados
$headers = getallheaders();
$isApiRequest = isset($headers['X-API-KEY']);

// Si tiene encabezado X-API-KEY, procesar como API
if ($isApiRequest) {
    header('Content-Type: application/json');

    if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== $CONFIG['api_key']) {
        http_response_code(401);
        echo json_encode(['error' => 'Clave API no válida']);
        exit;
    }

    // Detectar endpoint
    $requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $basePath = basename(dirname(__DIR__));
    $endpoint = str_replace($basePath . '/', '', $requestUri);
    $endpoint = explode('/', $endpoint)[0];

    $routeFile = __DIR__ . '/../routes/' . $endpoint . '.php';

    if (file_exists($routeFile)) {
        require $routeFile;
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
<title>API Administración</title>
<style>
    body {
        background: #f4f6f8;
        font-family: "Segoe UI", Arial, sans-serif;
        color: #333;
        text-align: center;
        margin: 0;
        padding: 0;
    }
    header {
        background: #0078d7;
        color: white;
        padding: 30px 0;
        font-size: 1.5rem;
        font-weight: bold;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .container {
        margin-top: 60px;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        width: 80%;
        margin: 40px auto;
    }
    .card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: 0.2s;
    }
    .card:hover {
        transform: translateY(-5px);
    }
    a {
        text-decoration: none;
        color: #0078d7;
        font-weight: bold;
    }
    footer {
        margin-top: 50px;
        font-size: 0.9rem;
        color: #777;
    }
</style>
</head>
<body>
<header>🚀 API Administración</header>

<div class="container">
    <h2>Panel de acceso rápido</h2>
    <p>Explora los módulos disponibles del sistema.</p>
    <div class="grid">
        <div class="card"><a href="../config/">Config/</a></div>
        <div class="card"><a href="../core/">Core/</a></div>
        <div class="card"><a href="../routes/">Routes/</a></div>
        <div class="card"><a href="../public/">Public/</a></div>
    </div>
</div>

<footer>
    Apache + PHP <?= phpversion() ?> | Servidor en <?= $_SERVER['SERVER_NAME'] ?>
</footer>
</body>
</html>
