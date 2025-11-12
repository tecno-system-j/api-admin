# Sistema de Rutas - API Administración

## 📋 Descripción

Sistema de enrutamiento dinámico que permite definir rutas por clase sin modificar el archivo `index.php`. Cada clase de ruta define sus propios métodos permitidos y cómo se mapean.

## 🚀 Cómo Crear una Nueva Ruta

### Paso 1: Crear el archivo de ruta

Crea un nuevo archivo en la carpeta `routes/` con el nombre de tu endpoint (ej: `productos.php`)

### Paso 2: Implementar la clase

```php
<?php
require_once '../config/query.php';

class Productos extends Query
{
    /**
     * Configuración de rutas permitidas
     * OBLIGATORIO: Debe implementar este método estático
     */
    public static function getRoutes()
    {
        return [
            // Métodos permitidos desde URL
            'urlMethods' => [
                'listar',
                'obtener',
                'crear',
                'actualizar',
                'eliminar'
            ],
            
            // Mapeo de métodos HTTP a métodos de clase
            'httpMethodMap' => [
                'GET' => 'listar',
                'POST' => 'crear',
                'PUT' => 'actualizar',
                'DELETE' => 'eliminar'
            ],
            
            // Método por defecto
            'defaultMethod' => 'listar',
            
            // Métodos protegidos (opcional)
            'protectedMethods' => ['eliminar']
        ];
    }

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
    }

    // Implementar los métodos aquí...
}
```

### Paso 3: Implementar los métodos

Cada método debe:
- Establecer headers JSON
- Validar parámetros
- Retornar respuestas JSON
- Usar `die()` al final

## 📝 Configuración de Rutas

### `urlMethods` (OBLIGATORIO)

Array de métodos que están permitidos desde la URL. **Solo los métodos listados aquí podrán ser ejecutados**, proporcionando seguridad adicional.

```php
'urlMethods' => [
    'listar',
    'obtener',
    'crear',
    'eliminar'
]
```

### `httpMethodMap` (OPCIONAL)

Mapeo de métodos HTTP estándar a métodos de clase. Solo se aplica si **NO** hay método en la URL.

**⚠️ IMPORTANTE**: Si tienes múltiples métodos que usan el mismo método HTTP (ej: varios métodos GET como `getUsuarios` y `getUsuario`), el `httpMethodMap` solo puede mapear a **UNO** de ellos. Los demás métodos deben especificarse explícitamente en la URL o parámetro.

```php
'httpMethodMap' => [
    'GET' => 'listar',      // GET /productos -> listar()
    'POST' => 'crear',       // POST /productos -> crear()
    'PUT' => 'actualizar',   // PUT /productos -> actualizar()
    'DELETE' => 'eliminar'   // DELETE /productos -> eliminar()
]
```

**Ejemplo con múltiples métodos GET**:
```php
'urlMethods' => [
    'getUsuarios',      // Lista todos
    'getUsuario',       // Obtiene uno por ID
    'getVerificar'      // Verifica existencia
],
'httpMethodMap' => [
    'GET' => 'getUsuarios',  // Solo este se ejecuta con GET /usuarios
    'POST' => 'registrar'
]
// Para los otros métodos GET, debes especificarlos:
// GET /usuarios/getUsuario?id=5
// GET /usuarios/getVerificar?item=correo&nombre=test@test.com
```

### `defaultMethod` (OPCIONAL)

Método que se ejecutará si no se especifica ninguno.

```php
'defaultMethod' => 'listar'
```

### `protectedMethods` (OPCIONAL)

Lista de métodos que requieren autenticación adicional (para uso futuro).

```php
'protectedMethods' => ['eliminar', 'actualizar']
```

## 🎯 Formas de Acceder a los Métodos

### 1. Desde URL (Máxima Prioridad)
```
GET /api_administracion/productos/listar
GET /api_administracion/productos/eliminar?id=5
GET /api_administracion/productos/obtener?id=3
```

### 2. Parámetro GET 'metodo'
```
GET /api_administracion/productos?metodo=listar
GET /api_administracion/productos?metodo=obtener&id=5
```

### 3. Método HTTP (si está configurado)
```
GET    /api_administracion/productos  -> listar()
POST   /api_administracion/productos  -> crear()
PUT    /api_administracion/productos  -> actualizar()
DELETE /api_administracion/productos  -> eliminar()
```

### 4. Método por defecto
```
GET /api_administracion/productos  -> defaultMethod
```

## 🔒 Seguridad

- **Solo los métodos listados en `urlMethods` pueden ser ejecutados**
- Si intentas acceder a un método no permitido, recibirás un error 403
- El sistema valida que el método exista en la clase antes de ejecutarlo
- Todos los métodos deben retornar JSON

## ❓ Preguntas Frecuentes

### ¿Qué pasa si tengo múltiples métodos que usan GET?

Si tienes múltiples métodos que usan GET (ej: `getUsuarios`, `getUsuario`, `getVerificar`):

1. **El `httpMethodMap['GET']` solo puede mapear a UNO** de ellos (generalmente el método principal)
2. **Los demás métodos GET deben especificarse explícitamente** en la URL o parámetro

**Ejemplo**:
```php
'urlMethods' => [
    'getUsuarios',      // Mapeado a GET /usuarios
    'getUsuario',       // Debe usar: GET /usuarios/getUsuario?id=5
    'getVerificar'      // Debe usar: GET /usuarios/getVerificar?item=correo&nombre=test
],
'httpMethodMap' => [
    'GET' => 'getUsuarios'  // Solo este se ejecuta con GET /usuarios
]
```

**Formas de acceder a los métodos GET adicionales**:
- `GET /usuarios/getUsuario?id=5`
- `GET /usuarios?metodo=getUsuario&id=5`
- `GET /usuarios/getVerificar?item=correo&nombre=test@test.com`

## 📌 Prioridad de Resolución

1. **Método desde URL** (ej: `/productos/listar`)
2. **Parámetro GET 'metodo'** (ej: `?metodo=listar`)
3. **Mapeo por método HTTP** (ej: `GET -> listar`)
4. **Método por defecto** (ej: `defaultMethod`)

## ⚠️ Errores Comunes

### Error: "La clase X debe implementar el método estático getRoutes()"
- **Solución**: Asegúrate de implementar el método `getRoutes()` en tu clase

### Error: "Método no permitido: X"
- **Solución**: Agrega el método a la lista `urlMethods` en `getRoutes()`

### Error: "Método no encontrado: X"
- **Solución**: Verifica que el método exista en la clase y esté en `urlMethods`

## 📚 Ejemplo Completo

Ver el archivo `_ejemplo_ruta.php` para un ejemplo completo de implementación.

## ✅ Ventajas del Sistema

1. **No necesitas modificar `index.php`** al agregar nuevas rutas
2. **Seguridad**: Solo los métodos permitidos pueden ejecutarse
3. **Flexibilidad**: Múltiples formas de acceder a los métodos
4. **Validación automática**: El sistema valida métodos antes de ejecutarlos
5. **Documentación clara**: Cada clase define sus propias rutas

