<?php
/**
 * PLANTILLA PARA CREAR NUEVAS RUTAS
 * 
 * Instrucciones:
 * 1. Copia este archivo con el nombre de tu endpoint (ej: productos.php)
 * 2. Cambia el nombre de la clase (ej: Productos)
 * 3. Configura el método getRoutes() con tus métodos permitidos
 * 4. Implementa los métodos de la clase
 * 5. ¡Listo! No necesitas modificar index.php
 */

require_once '../config/query.php';

class EjemploRuta extends Query
{
    /**
     * Configuración de rutas permitidas para este endpoint
     * Define qué métodos están permitidos y cómo se mapean
     * 
     * OBLIGATORIO: Toda clase de ruta debe implementar este método
     * 
     * IMPORTANTE: Si tienes múltiples métodos GET (ej: listar, obtener, buscar),
     * el httpMethodMap['GET'] solo puede mapear a UNO de ellos.
     * Los demás métodos GET deben especificarse explícitamente en la URL.
     */
    public static function getRoutes()
    {
        return [
            // Métodos permitidos desde URL (ej: /ejemplo/listar)
            // IMPORTANTE: Solo los métodos listados aquí podrán ser ejecutados
            'urlMethods' => [
                'listar',      // Lista todos (mapeado a GET /ejemplo)
                'obtener',     // Obtiene uno (debe usar: GET /ejemplo/obtener?id=5)
                'buscar',      // Busca (debe usar: GET /ejemplo/buscar?q=texto)
                'crear',       // Crea nuevo (mapeado a POST /ejemplo)
                'actualizar',  // Actualiza (mapeado a PUT /ejemplo)
                'eliminar'     // Elimina (mapeado a DELETE /ejemplo)
            ],
            
            // Mapeo de métodos HTTP a métodos de clase
            // Solo se aplica si NO hay método en la URL
            // ⚠️ NOTA: Si hay múltiples métodos GET, solo UNO puede estar aquí
            'httpMethodMap' => [
                'GET' => 'listar',      // GET /ejemplo -> listar()
                                       // Para 'obtener' y 'buscar' usa: GET /ejemplo/obtener o GET /ejemplo/buscar
                'POST' => 'crear',       // POST /ejemplo -> crear()
                'PUT' => 'actualizar',   // PUT /ejemplo -> actualizar()
                'PATCH' => 'actualizar', // PATCH /ejemplo -> actualizar()
                'DELETE' => 'eliminar'   // DELETE /ejemplo -> eliminar()
            ],
            
            // Método por defecto si no se especifica ninguno
            'defaultMethod' => 'listar',
            
            // Métodos que requieren autenticación adicional (opcional)
            // Puedes usar esto para validaciones adicionales en el futuro
            'protectedMethods' => ['eliminar', 'actualizar']
        ];
    }

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
    }

    /**
     * EJEMPLO: Método para listar elementos
     * Acceso: GET /ejemplo o GET /ejemplo/listar
     */
    public function listar()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // Tu lógica aquí
        $datos = []; // Obtener datos de la base de datos
        
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        die();
    }

    /**
     * EJEMPLO: Método para obtener un elemento por ID
     * 
     * ⚠️ IMPORTANTE: Como hay múltiples métodos GET, este NO puede mapearse
     * directamente a GET /ejemplo. Debes especificarlo explícitamente:
     * 
     * Acceso:
     * - GET /ejemplo/obtener?id=5
     * - GET /ejemplo?metodo=obtener&id=5
     * 
     * NO funciona: GET /ejemplo (ese ejecuta 'listar' según httpMethodMap)
     */
    public function obtener()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Tu lógica aquí
        $dato = null; // Obtener dato de la base de datos
        
        if ($dato) {
            echo json_encode($dato, JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No encontrado'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    /**
     * EJEMPLO: Método para crear un elemento
     * Acceso: POST /ejemplo o POST /ejemplo/crear
     */
    public function crear()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $nombre = $_POST['nombre'] ?? $_GET['nombre'] ?? null;
        
        if (!$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'Nombre es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Tu lógica aquí
        $resultado = true; // Insertar en base de datos
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Creado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    /**
     * EJEMPLO: Método para actualizar un elemento
     * Acceso: PUT /ejemplo o PUT /ejemplo/actualizar
     */
    public function actualizar()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        $nombre = $_POST['nombre'] ?? $_GET['nombre'] ?? null;
        
        if (!$id || !$nombre) {
            http_response_code(400);
            echo json_encode(['error' => 'ID y nombre son requeridos'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Tu lógica aquí
        $resultado = true; // Actualizar en base de datos
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Actualizado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al actualizar'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    /**
     * EJEMPLO: Método para eliminar un elemento
     * Acceso: DELETE /ejemplo o DELETE /ejemplo/eliminar o GET /ejemplo/eliminar?id=5
     */
    public function eliminar()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID es requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Tu lógica aquí
        $resultado = true; // Eliminar de base de datos
        
        if ($resultado) {
            echo json_encode(['success' => true, 'message' => 'Eliminado correctamente'], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Error al eliminar'], JSON_UNESCAPED_UNICODE);
        }
        die();
    }

    /**
     * EJEMPLO: Método personalizado para búsqueda
     * 
     * ⚠️ IMPORTANTE: Como hay múltiples métodos GET, este NO puede mapearse
     * directamente a GET /ejemplo. Debes especificarlo explícitamente:
     * 
     * Acceso:
     * - GET /ejemplo/buscar?q=texto
     * - GET /ejemplo?metodo=buscar&q=texto
     * 
     * NO funciona: GET /ejemplo (ese ejecuta 'listar' según httpMethodMap)
     */
    public function buscar()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $q = $_GET['q'] ?? $_POST['q'] ?? null;
        
        if (!$q) {
            http_response_code(400);
            echo json_encode(['error' => 'Término de búsqueda requerido'], JSON_UNESCAPED_UNICODE);
            die();
        }
        
        // Tu lógica aquí
        $resultados = []; // Buscar en base de datos
        
        echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
        die();
    }
}

