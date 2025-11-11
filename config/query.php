<?php

require_once __DIR__ . '/../core/Conexion.php';

class Query extends Conexion
{
    private $pdo, $con, $sql, $datos;
    public function __construct()
    {
        $this->pdo = new Conexion();
        $this->con = $this->pdo->conect();
    }
    public function select(string $sql, array $params = [])
    {
        try {
            $this->sql = $sql;
            $stmt = $this->con->prepare($this->sql);
            $stmt->execute($params); // Pasa los parámetros a execute
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return $data;
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }



    public function selectAll(string $sql, array $params = [])
    {
        try {
            $this->sql = $sql;
            $stmt = $this->con->prepare($this->sql);
            $stmt->execute($params); // Pasa los parámetros a execute
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }

    public function save(string $sql, array $datos)
    {
        $this->sql = $sql;
        $this->datos = $datos;
        $insert = $this->con->prepare($this->sql);
        $data = $insert->execute($this->datos);
        if ($data) {
            $res = 1;
        } else {
            $res = 0;
        }
        return $res;
    }
    public function insertar(string $sql, array $datos)
    {
        $this->sql = $sql;
        $this->datos = $datos;
        $insert = $this->con->prepare($this->sql);
        $data = $insert->execute($this->datos);
        if ($data) {
            $res = $this->con->lastInsertId();
        } else {
            $res = 0;
        }
        return $res;
    }
}
