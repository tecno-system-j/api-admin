<?php
require_once '../config/query.php';
class Usuarios extends Query
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
    }

    public function getUsuarios()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $sql = "SELECT id, nombre, apellido, correo, telefono, direccion, clave, rol, perfil, fecha 
                FROM usuarios 
                WHERE estado = 1";
    
        $usuarios = $this->selectAll($sql);
    
        echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
        die();
    }
    


    public function delete($id)
    {
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $datos = array(0, $id);
        return $this->save($sql, $datos);
    }
    public function getVerificar($item, $nombre, $id)
    {
        if ($id > 0) {
            $sql = "SELECT id FROM usuarios WHERE $item = '$nombre' AND id != $id AND estado = 1 ";
        } else {
            $sql = "SELECT id FROM usuarios WHERE $item = '$nombre' AND estado = 1 ";
        }


        return $this->select($sql);
    }





    public function registrar($nombre, $apellido, $correo, $telefono, $direccion, $clave, $rol, $licencia)
    {
        $sql = "INSERT INTO usuarios (nombre, apellido, correo, telefono, direccion, clave, rol, licencia) VALUES (?,?,?,?,?,?,?,?)";
        $datos = array($nombre, $apellido, $correo, $telefono, $direccion, $clave, $rol, $licencia);
        return $this->insertar($sql, $datos);
    }

    public function getUsuario($id)
    {
        $sql = "SELECT id, nombre, apellido, correo, telefono, direccion, clave, rol, perfil, fecha FROM usuarios WHERE id = $id";
        return $this->select($sql);
    }

    public function modificar($nombre, $apellido, $correo, $telefono, $direccion, $rol, $id)
    {
        $sql = "UPDATE usuarios SET nombre=?, apellido=?, correo=?, telefono=?, direccion=?, rol=? WHERE id=?";
        $datos = array($nombre, $apellido, $correo, $telefono, $direccion, $rol, $id);
        return $this->save($sql, $datos);
    }
}
