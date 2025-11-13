<?php
// config/database.php
require_once __DIR__ . '/../core/Conexion.php';
require_once __DIR__ . '/query.php';
// Obtiene las claves API de la base de datos utilizando una conexión segura PDO
$host = 'localhost'; // Cambia si no es localhost
$user = 'root';
$pass = '';
$dbname = 'licencias';
$charset = 'utf8mb4';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $stmt = $pdo->prepare("SELECT numero FROM licencias WHERE activa = 'active'");
        $stmt->execute();
        $apiKeys = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $apiKeys[] = $row['numero'];
        }
    } catch (Exception $e) {
        $apiKeys = [];
    }
    $CONFIG = [
        'api_key' => $apiKeys
    ];

?>