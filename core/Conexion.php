<?php
// core/Conexion.php

class Conexion
{
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $db = "gestion_administracion";
    private $charset = "utf8";
    
    public function conect()
    {
        try {
            $pdo = new PDO("mysql:host=".$this->host.";dbname=".$this->db.";charset=".$this->charset,
                           $this->user, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
}
