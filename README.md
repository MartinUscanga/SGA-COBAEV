# 🎓 Sistema de Gestión de Asistencias - COBAEV

Portal web para padres de familia con notificaciones push en tiempo real de entradas y salidas escolares.

---

## 🚀 Características

- ✅ **Notificaciones Push** vía Firebase Cloud Messaging (FCM)
- ✅ **Progressive Web App (PWA)** - Instalable en móviles
- ✅ **Tiempo Real** - Actualización automática de asistencias
- ✅ **Historial Completo** - Consulta de registros pasados
- ✅ **Seguridad Reforzada** - Protección contra fuerza bruta
- ✅ **Diseño Responsive** - Compatible con todos los dispositivos

---

## 📋 Requisitos Previos

### Software necesario:
- **XAMPP** (Apache + MySQL + PHP 7.4+)
- **Composer** (gestor de dependencias PHP)
- **Cuenta de Firebase** (para notificaciones push)
- Navegador moderno (Chrome, Firefox, Edge, Safari)

---

## 🛠️ Instalación

### 1. Clonar o descargar el proyecto
```bash
cd C:\xampp\htdocs
# Copiar todos los archivos del proyecto a una carpeta, ejemplo: cobaev
```

### 2. Instalar dependencias de Composer
```bash
cd C:\xampp\htdocs\cobaev
composer install
```

### 3. Configurar la base de datos

**a) Abrir phpMyAdmin:**
- URL: `http://localhost/phpmyadmin`
- Usuario: `root`
- Contraseña: (vacía)

**b) Importar la base de datos:**
```sql
-- Opción 1: Copiar y pegar el contenido de database_structure.sql
-- Opción 2: Usar "Importar" en phpMyAdmin
```

**c) Verificar que se crearon las tablas:**
- `alumnos`
- `tutores`
- `asistencias`
- `tokens_fcm`
- `logs_acceso`

### 4. Configurar Firebase

**a) Crear proyecto en Firebase:**
1. Ir a [Firebase Console](https://console.firebase.google.com/)
2. Crear nuevo proyecto: "SGA COBAEV"
3. Activar **Cloud Messaging**

**b) Obtener credenciales:**
1. Ir a **Configuración del proyecto** ⚙️
2. Pestaña **Cuentas de servicio**
3. Click en **Generar nueva clave privada**
4. Guardar el archivo JSON descargado

**c) Configurar credenciales:**
```bash
# Copiar el archivo descargado
cp ~/Downloads/sga-cobaev-xxxxx.json config/service-account.json
```

**d) Obtener VAPID Key:**
1. En Firebase Console: **Cloud Messaging**
2. Pestaña **Web Push certificates**
3. Generar par de claves
4. Copiar la **clave pública (VAPID)**

**e) Actualizar VAPID Key en `app.js`:**
```javascript
const VAPID_KEY = 'TU_VAPID_KEY_AQUI';
```

### 5. Configurar permisos (Linux/Mac)
```bash
chmod 755 -R /var/www/html/cobaev
chmod 644 config/service-account.json
```

### 6. Iniciar Apache y MySQL
- Abrir XAMPP Control Panel
- Iniciar **Apache**
- Iniciar **MySQL**

---

## 🧪 Pruebas

### 1. Acceder al sistema
```
http://localhost/cobaev/
```

### 2. Credenciales de prueba

| Matrícula | Contraseña | Alumno |
|-----------|------------|---------|
| B2024001  | padre123   | Juan Pérez González |
| B2024002  | madre123   | María López Martínez |
| B2024003  | tutor123   | Carlos Ramírez Hernández |

### 3. Probar notificaciones

**Opción A: Desde la consola de navegador (F12)**
```javascript
// Simular registro de asistencia
fetch('enviar_notificacion.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        matricula: 'B2024001',
        tipo: 'Entrada'
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

**Opción B: Insertar asistencia manualmente en BD**
```sql
INSERT INTO asistencias (matricula_alumno, fecha, hora, tipo, metodo_registro)
VALUES ('B2024001', CURDATE(), CURTIME(), 'Entrada', 'QR');
```

Luego llamar a `enviar_notificacion.php` con la matrícula.

---

## 📁 Estructura del Proyecto

```
cobaev/
├── config/
│   └── service-account.json        # Credenciales Firebase (NO subir a Git)
├── vendor/                          # Dependencias Composer
├── app.js                           # JavaScript principal (FCM)
├── sw.js                            # Service Worker
├── conexion.php                     # Conexión a BD
├── login_padres.php                 # Login de tutores
├── login_padres_mejorado.php        # Login con seguridad reforzada
├── padres.php                       # Dashboard de asistencias
├── logout.php                       # Cierre de sesión
├── guardar_token.php                # Endpoint para guardar tokens FCM
├── enviar_notificacion.php          # Envío de notificaciones push
├── manifest.json                    # Configuración PWA
├── database_structure.sql           # Estructura de BD
├── composer.json                    # Dependencias PHP
├── index.html                       # Página de inicio
├── logo.png                         # Logo COBAEV
└── README.md                        # Este archivo
```

---

## 🔧 Configuración Avanzada

### Cambiar zona horaria
En `conexion.php`:
```php
date_default_timezone_set('America/Mexico_City');
```

### Habilitar modo de producción
1. En `conexion.php`, comentar la línea que muestra errores
2. En `login_padres_mejorado.php`, ajustar `ini_set('display_errors', 0)`

### Hashear contraseñas existentes
```sql
-- Ejemplo para hashear "padre123"
UPDATE tutores 
SET password_tutor = '$2y$10$...' 
WHERE matricula_alumno = 'B2024001';
```

Para generar el hash:
```php
echo password_hash('padre123', PASSWORD_DEFAULT);
```

---

## 🚨 Solución de Problemas

### Error: "No se puede conectar a la base de datos"
- Verificar que MySQL esté iniciado en XAMPP
- Revisar credenciales en `conexion.php`
- Verificar que la BD `sga_cobaev` exista

### Error: "Class 'Google\Auth\...' not found"
```bash
composer install
```

### No llegan notificaciones
1. Verificar que `service-account.json` esté configurado
2. Revisar la consola de Firebase > Cloud Messaging
3. Verificar que el navegador permita notificaciones
4. Revisar logs en consola del navegador (F12)

### Service Worker no se registra
- Verificar que el sitio use HTTPS o localhost
- Limpiar caché del navegador
- Revisar consola del navegador (F12)

---

## 📱 Instalar como PWA

### Android (Chrome):
1. Abrir `http://localhost/cobaev/`
2. Menú ⋮ → "Instalar aplicación"
3. Aceptar

### iOS (Safari):
1. Abrir `http://localhost/cobaev/`
2. Botón Compartir 🔼
3. "Agregar a pantalla de inicio"

### Desktop:
1. Icono ➕ en la barra de direcciones
2. "Instalar SGA COBAEV"

---

## 🔒 Seguridad

### Producción:
- ✅ Usar HTTPS obligatoriamente
- ✅ Cambiar contraseñas de prueba
- ✅ Hashear todas las contraseñas
- ✅ Implementar tokens CSRF
- ✅ Configurar CORS correctamente
- ✅ No subir `service-account.json` a Git
- ✅ Limitar acceso a archivos sensibles

### .gitignore recomendado:
```
vendor/
config/service-account.json
.env
*.log
```

---

## 📞 Soporte

**Desarrollador:** Sistema COBAEV  
**Institución:** Colegio de Bachilleres del Estado de Veracruz  
**Año:** 2024

---

## 📝 Licencia

Uso exclusivo para COBAEV. Todos los derechos reservados.

---

## 🎯 Próximas Mejoras

- [ ] Panel administrativo
- [ ] Reportes exportables (PDF/Excel)
- [ ] Gráficas de asistencias
- [ ] Notificaciones por email
- [ ] Integración con WhatsApp
- [ ] Sistema de justificantes
- [ ] Multi-idioma (español/inglés)

---

¿Necesitas ayuda? Revisa la sección de **Solución de Problemas** o contacta al administrador del sistema.
