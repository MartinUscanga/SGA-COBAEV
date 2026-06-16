# 🔔 DIAGNÓSTICO: Notificaciones que no se muestran en segundo plano

## 🎯 Problema Reportado
"Las notificaciones a veces no se muestran en segundo plano"

---

## 🔍 Causas Comunes

### 1. **Tag duplicado en notificaciones** ⚠️
**Síntoma:** Solo se muestra la primera notificación, las siguientes se "actualizan" silenciosamente.

**Causa:**
```javascript
// ❌ MAL: Mismo tag para todas las notificaciones
tag: 'cobaev-acceso'
```

**Solución:**
```javascript
// ✅ BIEN: Tag único para cada notificación
tag: 'cobaev-' + Date.now()
```

---

### 2. **Navegador en modo ahorro de batería** 🔋
**Síntoma:** Las notificaciones solo aparecen cuando el navegador está activo.

**Solución:**
- En Android: Desactivar "Ahorro de batería" para el navegador
- En Chrome: `chrome://flags` → Buscar "Background Sync" → Habilitar
- Agregar el sitio a "Permitir siempre"

---

### 3. **Service Worker no captura el evento** 🚫
**Síntoma:** El mensaje llega pero no se muestra notificación.

**Diagnóstico:**
```javascript
// Abrir consola (F12) y verificar:
navigator.serviceWorker.getRegistration().then(reg => {
    console.log('SW State:', reg.active.state);
    console.log('SW Scope:', reg.scope);
});
```

**Solución:**
- Verificar que `sw.js` esté registrado correctamente
- Forzar actualización: Aplicación → Service Workers → "Update"
- Agregar evento `push` como fallback (ya implementado)

---

### 4. **Payload mal formado desde el servidor** 📦
**Síntoma:** Error 400/500 en Firebase, o mensaje llega pero sin notificación.

**Verificar en `enviar_notificacion.php`:**
```php
// ✅ El payload debe tener esta estructura EXACTA:
$mensaje = [
    'message' => [
        'token' => $token_padre,
        'notification' => [  // ← IMPORTANTE: Objeto "notification"
            'title' => 'Título',
            'body' => 'Cuerpo del mensaje'
        ],
        'webpush' => [  // ← Configuración específica para web
            'notification' => [
                'icon' => '/logo.png',
                'requireInteraction' => true
            ]
        ]
    ]
];
```

---

### 5. **Permisos revocados o temporales** 🔒
**Síntoma:** Funcionaba antes pero ahora no.

**Diagnóstico:**
```javascript
console.log('Permiso actual:', Notification.permission);
// Debe ser: "granted"
```

**Solución:**
- Recargar página y volver a aceptar permisos
- En Chrome: `chrome://settings/content/notifications`
- Eliminar y volver a agregar el sitio

---

### 6. **Conflicto entre notificaciones de primer/segundo plano** ⚔️
**Síntoma:** En primer plano funciona, en segundo plano no.

**Causa:**
```javascript
// ❌ MAL: app.js intercepta TODOS los mensajes
onMessage(messaging, (payload) => {
    new Notification(title, options);  // Esto bloquea al SW
});
```

**Solución:**
```javascript
// ✅ BIEN: Solo mostrar si la página está visible
onMessage(messaging, (payload) => {
    if (document.visibilityState === 'visible') {
        new Notification(title, options);
    }
    // Si está oculta, el SW lo maneja automáticamente
});
```

---

## 🧪 HERRAMIENTA DE DIAGNÓSTICO

### Archivo creado: `test_notificaciones.html`

**Uso:**
1. Abrir: `http://localhost/cobaev/test_notificaciones.html`
2. Ejecutar pruebas en orden:
   - ✅ Verificar Service Worker
   - ✅ Solicitar permisos
   - ✅ Probar notificación local
   - ✅ Probar via Service Worker
   - ✅ Enviar notificación FCM real

**Interpretar resultados:**
- **Test Local ✅** + **Test SW ❌** → Problema con el Service Worker
- **Test SW ✅** + **Test FCM ❌** → Problema con Firebase/Backend
- **Todos ✅** → El problema es intermitente (ver causa #2 o #6)

---

## 🔧 SOLUCIONES IMPLEMENTADAS

### 1. **Tag único en cada notificación**
```javascript
// sw.js - Línea 29
tag: 'cobaev-' + Date.now()
```

### 2. **Evento push como fallback**
```javascript
// sw.js - Línea 50
self.addEventListener('push', (event) => {
    // Captura mensajes que no pasen por messaging.onBackgroundMessage
});
```

### 3. **Logs detallados**
```javascript
// sw.js - Múltiples console.log para debugging
console.log('[Service Worker] 📬 Mensaje recibido:', payload);
console.log('[Service Worker] 🔍 Payload completo:', JSON.stringify(payload));
```

### 4. **Vibración más notoria**
```javascript
// sw.js
vibrate: [300, 100, 300, 100, 300]  // Vibración más larga
```

### 5. **RequireInteraction habilitado**
```javascript
// sw.js
requireInteraction: true  // La notificación NO se auto-cierra
```

---

## 📊 CHECKLIST DE VERIFICACIÓN

### Backend (PHP):
- [ ] `composer install` ejecutado correctamente
- [ ] `config/service-account.json` existe y es válido
- [ ] Tabla `tokens_fcm` tiene tokens activos
- [ ] Payload incluye `notification` y `webpush`

### Frontend (JavaScript):
- [ ] `sw.js` registrado correctamente
- [ ] Permisos de notificaciones = "granted"
- [ ] VAPID Key configurada en `app.js`
- [ ] Token FCM guardado en base de datos

### Navegador:
- [ ] Chrome/Firefox/Edge actualizado
- [ ] Modo ahorro de batería DESACTIVADO
- [ ] Notificaciones permitidas en configuración
- [ ] Service Worker en estado "activated"

---

## 🚀 PRUEBA RÁPIDA

### 1. Abrir consola (F12) en `padres.php`
```javascript
// Verificar estado actual
console.log('SW:', navigator.serviceWorker.controller);
console.log('Permission:', Notification.permission);
console.log('Matrícula:', MATRICULA_USUARIO);
```

### 2. Forzar notificación desde consola
```javascript
navigator.serviceWorker.ready.then(reg => {
    reg.showNotification('Test Manual', {
        body: 'Si ves esto, el SW funciona',
        icon: '/logo.png',
        tag: 'test-' + Date.now(),
        requireInteraction: true
    });
});
```

### 3. Simular notificación FCM real
```javascript
fetch('enviar_notificacion.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        matricula: 'B2024001',
        tipo: 'Entrada'
    })
})
.then(r => r.json())
.then(data => console.log('Resultado:', data));
```

---

## ⚙️ CONFIGURACIÓN AVANZADA

### Firebase Console:
1. Ir a **Cloud Messaging**
2. Verificar que el proyecto esté activo
3. Revisar estadísticas de envío
4. Comprobar que no haya errores de cuota

### Chrome DevTools:
1. Application → Service Workers
2. Verificar que esté "activated and running"
3. Click en "Push" para simular mensaje
4. Revisar logs en Console

---

## 🔄 REINICIO COMPLETO (Si nada funciona)

```javascript
// 1. Desregistrar Service Worker
navigator.serviceWorker.getRegistrations().then(regs => {
    regs.forEach(reg => reg.unregister());
});

// 2. Limpiar cache
caches.keys().then(names => {
    names.forEach(name => caches.delete(name));
});

// 3. Recargar página (Ctrl + Shift + R)

// 4. Volver a pedir permisos
Notification.requestPermission();

// 5. Registrar SW de nuevo (app.js lo hace automáticamente)
```

---

## 📞 SOPORTE

Si después de aplicar estas soluciones el problema persiste:

1. **Revisar logs del servidor:**
   - Apache: `C:\xampp\apache\logs\error.log`
   - PHP: Habilitar `error_log` en `enviar_notificacion.php`

2. **Verificar Firebase:**
   - Firebase Console → Cloud Messaging → Logs
   - Verificar que el token no esté expirado

3. **Probar en otro navegador:**
   - Chrome → Mejor soporte
   - Firefox → Buen soporte
   - Safari → Soporte limitado (iOS 16.4+)

---

## ✅ PRÓXIMOS PASOS

1. Abrir `test_notificaciones.html`
2. Ejecutar todas las pruebas
3. Revisar el log de eventos
4. Identificar qué prueba falla
5. Aplicar la solución específica

¿Necesitas ayuda con alguna prueba específica? 🦉
