<?php
/**
 * Sistema de Autenticación para Tutores - COBAEV
 * Versión mejorada con seguridad reforzada
 */

// Configuración de seguridad
ini_set('display_errors', 0); // Ocultar errores en producción
error_reporting(E_ALL);

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Iniciar sesión de forma segura
session_start();

// Regenerar ID de sesión para prevenir session fixation
if (!isset($_SESSION['iniciada'])) {
    session_regenerate_id(true);
    $_SESSION['iniciada'] = true;
}

// Variables iniciales
$error_message = "";
$intentos_fallidos = $_SESSION['intentos_fallidos'] ?? 0;
$tiempo_bloqueo = $_SESSION['tiempo_bloqueo'] ?? 0;

// Verificar si el usuario está bloqueado temporalmente
if ($tiempo_bloqueo > time()) {
    $segundos_restantes = $tiempo_bloqueo - time();
    $error_message = "Demasiados intentos fallidos. Intente nuevamente en " . ceil($segundos_restantes / 60) . " minutos.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $tiempo_bloqueo <= time()) {
    
    // Incluir conexión a BD
    require_once 'conexion.php';

    // Sanitizar y validar inputs
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $password_tutor = $_POST['password_tutor'] ?? '';
    
    // Validación de formato de matrícula (Ej: B2024001)
    if (!preg_match('/^[A-Z]\d{7}$/', $matricula)) {
        $error_message = "Formato de matrícula no válido.";
    } 
    // Validación de longitud de contraseña
    elseif (strlen($password_tutor) < 6) {
        $error_message = "La clave de acceso debe tener al menos 6 caracteres.";
    }
    else {
        try {
            // Consultar tutor y alumno asociado
            $sql = "SELECT 
                        t.id_tutor,
                        t.matricula_alumno,
                        t.password_tutor,
                        t.nombre_tutor,
                        t.telefono_tutor,
                        a.nombre AS nombre_alumno,
                        a.apellido_paterno,
                        a.apellido_materno,
                        a.grupo
                    FROM tutores t
                    INNER JOIN alumnos a ON t.matricula_alumno = a.matricula
                    WHERE t.matricula_alumno = :matricula 
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['matricula' => $matricula]);
            $tutor = $stmt->fetch();

            // Verificar credenciales
            $credenciales_validas = false;
            
            if ($tutor) {
                // Si la contraseña está hasheada, usar password_verify
                if (password_get_info($tutor['password_tutor'])['algo'] !== 0) {
                    $credenciales_validas = password_verify($password_tutor, $tutor['password_tutor']);
                } 
                // Si es texto plano (compatibilidad con BD actual)
                else {
                    $credenciales_validas = ($password_tutor === $tutor['password_tutor']);
                }
            }

            if ($credenciales_validas) {
                // ✅ AUTENTICACIÓN EXITOSA
                
                // Regenerar ID de sesión por seguridad
                session_regenerate_id(true);
                
                // Resetear intentos fallidos
                unset($_SESSION['intentos_fallidos']);
                unset($_SESSION['tiempo_bloqueo']);
                
                // Guardar datos en sesión
                $_SESSION['tutor_autenticado'] = true;
                $_SESSION['id_tutor'] = $tutor['id_tutor'];
                $_SESSION['alumno_matricula'] = $tutor['matricula_alumno'];
                $_SESSION['alumno_nombre'] = trim($tutor['nombre_alumno'] . ' ' . 
                                                  $tutor['apellido_paterno'] . ' ' . 
                                                  $tutor['apellido_materno']);
                $_SESSION['tutor_nombre'] = $tutor['nombre_tutor'];
                $_SESSION['alumno_grupo'] = $tutor['grupo'] ?? 'N/A';
                $_SESSION['ultima_actividad'] = time();
                
                // Registrar acceso en log (opcional)
                try {
                    $log_sql = "INSERT INTO logs_acceso (matricula_alumno, tipo_usuario, fecha_hora, ip_address) 
                                VALUES (:matricula, 'tutor', NOW(), :ip)";
                    $log_stmt = $pdo->prepare($log_sql);
                    $log_stmt->execute([
                        'matricula' => $matricula,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                } catch (PDOException $e) {
                    // Silenciar error de log para no bloquear el login
                }
                
                // Redirigir al portal
                header("Location: padres.php");
                exit;
                
            } else {
                // ❌ CREDENCIALES INCORRECTAS
                $intentos_fallidos++;
                $_SESSION['intentos_fallidos'] = $intentos_fallidos;
                
                // Bloquear después de 5 intentos fallidos
                if ($intentos_fallidos >= 5) {
                    $_SESSION['tiempo_bloqueo'] = time() + (15 * 60); // 15 minutos
                    $error_message = "Demasiados intentos fallidos. Cuenta bloqueada temporalmente.";
                } else {
                    $error_message = "La matrícula o clave de acceso no coinciden con nuestros registros.";
                }
            }

        } catch (PDOException $e) {
            // Error de base de datos
            $error_message = "Error temporal en el servidor. Por favor, intente más tarde.";
            error_log("Error login_padres.php: " . $e->getMessage()); // Log interno
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portal de acceso para tutores - Sistema COBAEV">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso de Tutores - COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        
        /* Animación de shake para errores */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .shake { animation: shake 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col justify-between p-4 md:p-8 selection:bg-red-200">

    <div class="max-w-md mx-auto w-full text-left flex-shrink-0">
        <a href="index.html" class="inline-flex items-center text-xs font-bold tracking-wider text-zinc-400 hover:text-vino uppercase transition-colors group">
            <span class="mr-2 group-hover:-translate-x-1 transition-transform">←</span> Volver al inicio
        </a>
    </div>

    <div class="max-w-md mx-auto w-full bg-white border border-zinc-200/80 rounded-2xl p-6 md:p-8 shadow-sm my-auto space-y-6">
        
        <div class="text-center space-y-2">
            <div class="flex items-center justify-center space-x-2">
                <span class="text-vino font-serif-elegant font-bold text-2xl tracking-wider">SGA</span>
                <span class="text-zinc-300 text-xl">|</span>
                <span class="text-dorado font-serif-elegant italic text-xl">Padres de Familia</span>
            </div>
            <p class="text-[10px] text-zinc-400 font-bold tracking-widest uppercase px-4 leading-normal">
                Consulta y monitoreo de accesos escolares en tiempo real
            </p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg p-3 text-center font-medium shake">
                <svg class="w-4 h-4 inline-block mr-1 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4" autocomplete="off">
            
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1 tracking-wide">Matrícula del Alumno</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="matricula" 
                        id="matricula"
                        required 
                        placeholder="Ej. B2024001" 
                        maxlength="8"
                        pattern="[A-Z]\d{7}"
                        title="Formato: 1 letra mayúscula seguida de 7 números"
                        value="<?= isset($_POST['matricula']) ? htmlspecialchars($_POST['matricula']) : '' ?>"
                        class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm font-mono focus:outline-none focus:border-vino transition-colors placeholder:font-sans placeholder:text-zinc-400 uppercase"
                        <?= ($tiempo_bloqueo > time()) ? 'disabled' : '' ?>
                    >
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide">Clave de Acceso</label>
                    <a href="recuperar_clave.php" class="text-[10px] font-semibold text-dorado hover:underline">Solicitar clave</a>
                </div>
                <div class="relative">
                    <input 
                        type="password" 
                        name="password_tutor" 
                        id="password_tutor"
                        required 
                        placeholder="Introduce tu clave privada" 
                        minlength="6"
                        class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 pr-9 text-sm focus:outline-none focus:border-vino transition-colors"
                        <?= ($tiempo_bloqueo > time()) ? 'disabled' : '' ?>
                    >
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                    <button type="button" id="togglePassword" class="absolute right-3 top-3.5 text-zinc-400 hover:text-zinc-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pt-2">
                <button 
                    type="submit" 
                    class="w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    <?= ($tiempo_bloqueo > time()) ? 'disabled' : '' ?>
                >
                    <?= ($tiempo_bloqueo > time()) ? 'Cuenta Bloqueada' : 'Ingresar al Monitor' ?>
                </button>
            </div>
        </form>

        <?php if ($intentos_fallidos > 0 && $intentos_fallidos < 5): ?>
            <div class="text-center text-[10px] text-amber-600 font-medium">
                ⚠️ Intento <?= $intentos_fallidos ?> de 5
            </div>
        <?php endif; ?>

    </div>

    <div class="max-w-md mx-auto w-full text-center text-[9px] text-zinc-400 uppercase tracking-widest flex-shrink-0 leading-normal px-2">
        Protección de datos conforme a la legislación escolar vigente
    </div>

    <script>
        // Mostrar/ocultar contraseña
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password_tutor');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
        });

        // Convertir matrícula a mayúsculas automáticamente
        document.getElementById('matricula')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>

</body>
</html>
