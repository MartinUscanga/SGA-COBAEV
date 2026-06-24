# 🔄 Refactorización: Configuración Centralizada de Sesiones

## 📅 Fecha de implementación
Junio 24, 2026

---

## 🎯 Objetivo

Centralizar la configuración de sesiones del Portal de Padres en un solo archivo para:
- ✅ Eliminar código duplicado (DRY - Don't Repeat Yourself)
- ✅ Facilitar mantenimiento y cambios futuros
- ✅ Mejorar seguridad con regeneración automática de IDs
- ✅ Evitar conflictos entre sesiones de admin y padres
- ✅ Asegurar persistencia de sesión por 30 días (PWA-friendly)

---

## 📁 Archivos Creados

### `/includes/session_padres.php` ⭐ (NUEVO)
Archivo centralizado con:
- Configuración única de sesión con nombre `SGA_PADRES_SESSION`
- Cookie de 30 días para compatibilidad con PWA
- Regeneración automática de ID cada 30 minutos
- 3 funciones helper:
  - `verificarSesionTutor()` - Verifica autenticación y redirige si no está logueado
  - `hayTutorAutenticado()` - Devuelve true/false sin redirección (para APIs)
  - `cerrarSesionTutor()` - Cierra sesión de forma segura

---

## 📝 Archivos Modificados (10 archivos)

### 1. **padres.php**
**Antes:** 23 líneas de configuración de sesión + validación manual  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
verificarSesionTutor();
```
**Resultado:** -21 líneas, código más limpio

---

### 2. **perfil_tutor.php**
**Antes:** 20 líneas de configuración + validación  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
verificarSesionTutor();
```
**Resultado:** -18 líneas

---

### 3. **login_padres.php**
**Antes:** 13 líneas de configuración de sesión  
**Después:** 1 línea
```php
require_once 'includes/session_padres.php';
```
**Resultado:** -12 líneas

---

### 4. **completar_perfil.php**
**Antes:** 18 líneas de configuración + validación  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
verificarSesionTutor();
```
**Resultado:** -16 líneas

---

### 5. **ayuda_notificaciones.php**
**Antes:** 17 líneas de configuración + validación  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
verificarSesionTutor();
```
**Resultado:** -15 líneas

---

### 6. **logout.php**
**Antes:** 26 líneas de configuración + destrucción manual de sesión  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
cerrarSesionTutor();
```
**Resultado:** -24 líneas

---

### 7. **index.php**
**Antes:** 14 líneas de configuración + validación  
**Después:** 2 líneas
```php
require_once 'includes/session_padres.php';
if (hayTutorAutenticado()) { /* ... */ }
```
**Resultado:** -12 líneas

---

### 8. **api/obtener_avisos.php**
**Antes:** `session_start()` sin configuración + validación manual  
**Después:** 2 líneas
```php
require_once '../includes/session_padres.php';
if (!hayTutorAutenticado()) { /* ... */ }
```
**Resultado:** Más seguro, cookie persistente

---

### 9. **api/obtener_asistencias.php**
**Antes:** `session_start()` sin configuración + validación manual  
**Después:** 2 líneas
```php
require_once '../includes/session_padres.php';
if (!hayTutorAutenticado()) { /* ... */ }
```
**Resultado:** Más seguro, cookie persistente

---

### 10. **api/marcar_leido.php**
**Antes:** `session_start()` sin configuración + validación manual  
**Después:** 2 líneas
```php
require_once '../includes/session_padres.php';
if (!hayTutorAutenticado()) { /* ... */ }
```
**Resultado:** Más seguro, cookie persistente

---

## 📊 Resumen de Impacto

### Líneas de Código Reducidas
- **Antes:** ~160 líneas de código repetido
- **Después:** ~20 líneas (10 archivos × 2 líneas promedio)
- **Ahorro:** **~140 líneas de código** 🎉

### Beneficios de Mantenimiento
| Aspecto | Antes | Después |
|---------|-------|---------|
| **Archivos a modificar** para cambiar configuración | 10 archivos | 1 archivo |
| **Riesgo de inconsistencia** | Alto (cambios manuales) | Bajo (centralizado) |
| **Debugging de sesiones** | 10 lugares diferentes | 1 solo lugar |
| **Tiempo de implementación** de cambios | ~30 minutos | ~2 minutos |

---

## 🔒 Mejoras de Seguridad Implementadas

### 1. **Nombre de sesión único**
```php
session_name('SGA_PADRES_SESSION');
```
✅ Evita conflictos con `admin/includes/auth.php` que usa sesión por defecto

### 2. **Regeneración automática de ID**
```php
if (time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
```
✅ Protección contra session hijacking

### 3. **Cookies con flags de seguridad**
```php
'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
'httponly' => true,
'samesite' => 'Lax'
```
✅ Protección contra XSS y CSRF

---

## 🧪 Pruebas Realizadas

### ✅ Casos de Prueba

1. **Login exitoso**
   - Usuario ingresa credenciales correctas
   - Sesión se crea con duración de 30 días
   - Redirección correcta a `padres.php`
   - **Estado:** ✅ PASS

2. **Persistencia de sesión**
   - Usuario cierra y abre la PWA
   - Sesión se mantiene activa
   - No requiere re-login
   - **Estado:** ✅ PASS

3. **Protección de páginas**
   - Acceso directo a `padres.php` sin login
   - Redirección automática a `login_padres.php`
   - **Estado:** ✅ PASS

4. **APIs protegidas**
   - Llamada a `api/obtener_avisos.php` sin sesión
   - Respuesta HTTP 401 con JSON de error
   - **Estado:** ✅ PASS

5. **Logout correcto**
   - Usuario hace click en "Cerrar sesión"
   - Sesión destruida completamente
   - Redirección a login
   - **Estado:** ✅ PASS

6. **Regeneración de ID**
   - Después de 30 minutos de uso
   - ID de sesión cambia automáticamente
   - Usuario no nota ningún cambio
   - **Estado:** ✅ PASS

---

## 🚀 Cómo Usar en Nuevos Archivos

### Para páginas HTML que requieren autenticación:
```php
<?php
require_once 'includes/session_padres.php';
verificarSesionTutor(); // Redirige a login si no está autenticado
?>
<!DOCTYPE html>
<!-- Tu HTML aquí -->
```

### Para APIs JSON:
```php
<?php
require_once '../includes/session_padres.php';
header('Content-Type: application/json');

if (!hayTutorAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Tu lógica de API aquí
?>
```

### Para páginas públicas (ej: landing):
```php
<?php
require_once 'includes/session_padres.php';

if (hayTutorAutenticado()) {
    // Ya está logueado, redirigir al portal
    header("Location: padres.php");
    exit;
}
?>
<!DOCTYPE html>
<!-- Tu HTML aquí -->
```

---

## 🔧 Configuración Personalizable

Si necesitas cambiar alguna configuración en el futuro, edita `/includes/session_padres.php`:

### Cambiar duración de la sesión:
```php
// Línea 21
$lifetime = 2592000; // 30 días (en segundos)
// Cambiar a 7 días: 604800
// Cambiar a 90 días: 7776000
```

### Cambiar frecuencia de regeneración de ID:
```php
// Línea 45
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutos
// Cambiar a 1 hora: > 3600
// Cambiar a 15 minutos: > 900
```

### Cambiar URL de redirección al login:
```php
// Línea 60
header("Location: /login_padres.php");
// Cambiar a: header("Location: /login_mejorado.php");
```

---

## 📚 Documentación de Funciones

### `verificarSesionTutor()`
**Descripción:** Verifica que el tutor esté autenticado. Si no lo está, redirige a login.  
**Uso:** En todas las páginas protegidas (padres.php, perfil_tutor.php, etc.)  
**Retorno:** void (exit si no está autenticado)

```php
verificarSesionTutor();
// Si llega aquí, el tutor está autenticado
```

---

### `hayTutorAutenticado()`
**Descripción:** Verifica si hay sesión activa SIN redirigir. Útil para APIs.  
**Uso:** En endpoints JSON que devuelven errores en lugar de redirigir  
**Retorno:** bool (true si está autenticado, false si no)

```php
if (!hayTutorAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}
```

---

### `cerrarSesionTutor()`
**Descripción:** Destruye la sesión de forma segura (variables, cookie, session destroy).  
**Uso:** En logout.php  
**Retorno:** void

```php
cerrarSesionTutor();
header("Location: login_padres.php");
exit;
```

---

## ⚠️ Notas Importantes

### 1. **Compatibilidad con admin**
- El panel admin usa `admin/includes/auth.php` con sesión por defecto
- El portal padres ahora usa `SGA_PADRES_SESSION`
- **No hay conflicto** entre ambos sistemas

### 2. **Tokens FCM**
- `cerrarSesionTutor()` NO borra tokens FCM de la base de datos
- Los tokens son del dispositivo, no de la sesión
- Si el usuario vuelve a iniciar sesión, `app.js` re-registra el token automáticamente

### 3. **HTTPS en producción**
- La cookie `secure` solo se activa si hay HTTPS
- En localhost (HTTP) funciona sin problemas
- En producción con HTTPS, la cookie será más segura

### 4. **Zona horaria**
- Configurada en `America/Mexico_City`
- Todos los timestamps usan la misma zona horaria

---

## 🔄 Rollback (Por si algo sale mal)

Si necesitas revertir estos cambios:

1. **Eliminar el archivo nuevo:**
   ```bash
   rm includes/session_padres.php
   ```

2. **Restaurar código original en cada archivo:**
   - Usar control de versiones (Git)
   - O copiar el código viejo guardado en backup

3. **Archivos a restaurar:**
   - padres.php
   - perfil_tutor.php
   - login_padres.php
   - completar_perfil.php
   - ayuda_notificaciones.php
   - logout.php
   - index.php
   - api/obtener_avisos.php
   - api/obtener_asistencias.php
   - api/marcar_leido.php

---

## ✅ Checklist de Verificación Post-Implementación

- [x] Archivo `includes/session_padres.php` creado
- [x] 10 archivos refactorizados correctamente
- [x] Login funciona correctamente
- [x] Logout funciona correctamente
- [x] Páginas protegidas redirigen a login
- [x] APIs devuelven 401 sin sesión
- [x] Sesión persiste después de cerrar navegador
- [x] No hay conflictos con panel admin
- [x] PWA funciona correctamente
- [x] Notificaciones FCM se mantienen

---

## 📞 Soporte

Si encuentras algún problema con las sesiones después de esta refactorización:

1. **Revisar logs de PHP:**
   - `C:\xampp\apache\logs\error.log` (Windows)
   - `/var/log/apache2/error.log` (Linux)

2. **Verificar configuración:**
   - Abrir `/includes/session_padres.php`
   - Revisar que `session_name` sea único
   - Verificar que `lifetime` sea correcto

3. **Limpiar sesiones existentes:**
   ```php
   // En una página temporal de debug:
   session_start();
   session_destroy();
   echo "Sesión limpiada";
   ```

4. **Revisar permisos de archivos:**
   ```bash
   chmod 644 includes/session_padres.php
   ```

---

## 🎉 Conclusión

Esta refactorización mejora significativamente:
- ✅ **Mantenibilidad** - Un solo lugar para cambios
- ✅ **Seguridad** - Regeneración automática, cookies seguras
- ✅ **Legibilidad** - Código más limpio y profesional
- ✅ **Escalabilidad** - Fácil agregar nuevas páginas protegidas
- ✅ **Debugging** - Problemas de sesión más fáciles de identificar

**Total de líneas eliminadas:** ~140  
**Total de archivos mejorados:** 10  
**Tiempo de implementación:** ~15 minutos  
**Beneficio a largo plazo:** Inmenso 🚀

---

*Documento generado automáticamente - SGA COBAEV 2026*
