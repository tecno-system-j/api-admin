<?php
require_once '../config/query.php';
class Usuarios extends Query
{
    /**
     * Configuración de rutas permitidas para este endpoint
     * Define qué métodos están permitidos y cómo se mapean
     */
    public static function getRoutes()
    {
        return [
            // Métodos permitidos desde URL (ej: /usuarios/delete)
            'urlMethods' => [
                'getUsuarios',
                'getUsuario',
                'delete',
                'registrar',
                'modificar',
                'getVerificar',
                'activate'
            ],
            
            // Mapeo de métodos HTTP a métodos de clase
            // Solo se aplica si no hay método en la URL
            'httpMethodMap' => [
                'GET' => 'getUsuarios',
                'POST' => 'registrar',
                'PUT' => 'modificar',
                'PATCH' => 'modificar',
                'DELETE' => 'delete',
                'GET' => 'activate'
            ],
            
            // Método por defecto si no se especifica ninguno
            'defaultMethod' => 'getUsuarios',
            
            // Métodos que requieren autenticación adicional (opcional)
            'protectedMethods' => []
        ];
    }

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
    }

    public function getUsuarios()
    {
        header('Content-Type: application/json; charset=utf-8');
    
        $sql = "SELECT id, nombre, apellido, correo, telefono, direccion, clave, rol, perfil, fecha FROM usuarios WHERE estado = ?";
        $datos = array(1);
    
        $usuarios = $this->selectAll($sql, $datos);

        for ($i = 0; $i < count($usuarios); $i++) {
            if ($usuarios[$i]['id'] == 1) {
                $usuarios[$i]['acciones'] = 'Este Usuario no se puede modificar';
            }else{    
                $usuarios[$i]['acciones'] = '<div>
                <a href="#" class="btn btn-info btn-sm" onclick="editar(' . $usuarios[$i]['id'] . ')">
                    <i class="material-symbols-outlined">edit</i>
                </a>
                <a href="#" class="btn btn-danger btn-sm" onclick="eliminar(' . $usuarios[$i]['id'] . ')">
                    <i class="material-symbols-outlined">delete</i>
                </a>
                </div>';
    
            }
            $usuarios[$i]['nombres'] = $usuarios[$i]['nombre'] . ' ' . $usuarios[$i]['apellido'];
        }
    
        echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
        die();
    }
    


    public function delete($id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $id ?? $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $datos = array(0, $id);
        $resultado = $this->save($sql, $datos);
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar el usuario'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    public function activate($id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $id ?? $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $sql = "UPDATE usuarios SET estado = ? WHERE id = ?";
        $datos = array(1, $id);
        $resultado = $this->save($sql, $datos);
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Usuario activado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al activar el usuario'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    
    /**
     * Método privado para verificar existencia (uso interno)
     */
    private function verificarExistencia($item, $nombre, $id = 0)
    {
        // Prevenir inyección SQL validando el item
        $itemPermitidos = ['correo', 'telefono', 'nombre'];
        if (!in_array($item, $itemPermitidos)) {
            return false;
        }
        
        if ($id > 0) {
            $sql = "SELECT id FROM usuarios WHERE $item = ? AND id != ? AND estado = 1";
            $datos = array($nombre, $id);
            $resultado = $this->select($sql, $datos);
        } else {
            $sql = "SELECT id FROM usuarios WHERE $item = ? AND estado = 1";
            $datos = array($nombre);
            $resultado = $this->select($sql, $datos);
        }
        
        return !empty($resultado);
    }

    public function getVerificar($item = null, $nombre = null, $id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $item = $item ?? $_GET['item'] ?? $_POST['item'] ?? null;
        $nombre = $nombre ?? $_GET['nombre'] ?? $_POST['nombre'] ?? null;
        $id = $id ?? $_GET['id'] ?? $_POST['id'] ?? 0;
        
        if (!$item || !$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'Item y nombre son requeridos'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Prevenir inyección SQL validando el item
        $itemPermitidos = ['correo', 'telefono', 'nombre'];
        if (!in_array($item, $itemPermitidos)) {
            http_response_code(400);
            echo json_encode(['error' => 'Item no válido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $existe = $this->verificarExistencia($item, $nombre, $id);
        echo json_encode(['existe' => $existe], JSON_UNESCAPED_UNICODE);
        die();
    }





    public function registrar($nombre = null, $apellido = null, $correo = null, $telefono = null, $direccion = null, $clave = null, $rol = null, $licencia = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $nombre = $nombre ?? $_POST['nombre'] ?? $_GET['nombre'] ?? null;
        $apellido = $apellido ?? $_POST['apellido'] ?? $_GET['apellido'] ?? null;
        $correo = $correo ?? $_POST['correo'] ?? $_GET['correo'] ?? null;
        $telefono = $telefono ?? $_POST['telefono'] ?? $_GET['telefono'] ?? null;
        $direccion = $direccion ?? $_POST['direccion'] ?? $_GET['direccion'] ?? null;
        $clave = $clave ?? $_POST['clave'] ?? $_GET['clave'] ?? null;
        $rol = $rol ?? $_POST['rol'] ?? $_GET['rol'] ?? null;
        $licencia = $licencia ?? $_POST['licencia'] ?? $_GET['licencia'] ?? null;
        
        // Validar campos requeridos
        if (!$nombre || !$apellido || !$correo || !$clave || !$rol) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos: nombre, apellido, correo, clave, rol'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Verificar si el correo ya existe
        if ($this->verificarExistencia('correo', $correo, 0)) {
            http_response_code(400);
            echo json_encode(['error' => 'El correo ya está registrado'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $sql = "INSERT INTO usuarios (nombre, apellido, correo, telefono, direccion, clave, rol, licencia) VALUES (?,?,?,?,?,?,?,?)";
        $datos = array($nombre, $apellido, $correo, $telefono, $direccion, $clave, $rol, $licencia);
        $resultado = $this->insertar($sql, $datos);
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Usuario registrado correctamente', 'id' => $resultado], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al registrar el usuario'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    public function getUsuario($id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $id ?? $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $sql = "SELECT id, nombre, apellido, correo, telefono, direccion, clave, rol, perfil, fecha FROM usuarios WHERE id = ?";
        $datos = array($id);
        $usuario = $this->select($sql, $datos);
        
        if ($usuario) {
            // No mostrar la clave en la respuesta
            unset($usuario['clave']);
            echo json_encode($usuario, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    public function modificar($nombre = null, $apellido = null, $correo = null, $telefono = null, $direccion = null, $rol = null, $id = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $nombre = $nombre ?? $_POST['nombre'] ?? $_GET['nombre'] ?? null;
        $apellido = $apellido ?? $_POST['apellido'] ?? $_GET['apellido'] ?? null;
        $correo = $correo ?? $_POST['correo'] ?? $_GET['correo'] ?? null;
        $telefono = $telefono ?? $_POST['telefono'] ?? $_GET['telefono'] ?? null;
        $direccion = $direccion ?? $_POST['direccion'] ?? $_GET['direccion'] ?? null;
        $rol = $rol ?? $_POST['rol'] ?? $_GET['rol'] ?? null;
        $id = $id ?? $_POST['id'] ?? $_GET['id'] ?? null;
        
        // Validar campos requeridos
        if (!$id || !$nombre || !$apellido || !$correo || !$rol) {
            http_response_code(400);
            echo json_encode(['error' => 'Campos requeridos: id, nombre, apellido, correo, rol'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Verificar si el correo ya existe en otro usuario
        if ($this->verificarExistencia('correo', $correo, $id)) {
            http_response_code(400);
            echo json_encode(['error' => 'El correo ya está registrado en otro usuario'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        $sql = "UPDATE usuarios SET nombre=?, apellido=?, correo=?, telefono=?, direccion=?, rol=? WHERE id=?";
        $datos = array($nombre, $apellido, $correo, $telefono, $direccion, $rol, $id);
        $resultado = $this->save($sql, $datos);
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Usuario modificado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al modificar el usuario'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }
}
