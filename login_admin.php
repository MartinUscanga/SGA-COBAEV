<?php
/**
 * Login de Administradores/Vigilantes
 * Sistema de Checador - SGA COBAEV
 */

session_start();
$error_message = "";

// Si ya está autenticado, redirigir al checador
if (isset($_SESSION['usuario_autenticado']) && $_SESSION['usuario_autenticado'] === true) {
    header("Location: checador.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'conexion.php';

    $username = trim($_POST['usuario']);
    $password = trim($_POST['password']);
    
    try {
        // Consultar usuario administrador
        $sql = "SELECT id_usuario, username, password_admin, nombre_completo, rol, ultimo_acceso 
                FROM usuarios_admin 
                WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Validar credenciales
        // Nota: En producción usa password_verify() con hashes
        if ($user && $password === $user['password_admin']) {
            
            // Actualizar último acceso
            $update_sql = "UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id_usuario = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(['id' => $user['id_usuario']]);
            
            // Guardar en sesión
            $_SESSION['usuario_autenticado'] = true;
            $_SESSION['usuario_id'] = $user['id_usuario'];
            $_SESSION['usuario_nombre'] = $user['nombre_completo'];
            $_SESSION['usuario_rol'] = $user['rol'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['ultima_actividad'] = time();
            
            // Redirigir al checador
            header("Location: checador.php");
            exit;
        } else {
            $error_message = "Usuario o contraseña incorrectos.";
        }

    } catch (PDOException $e) {
        $error_message = "Error temporal en el servidor. Por favor, intente más tarde.";
        error_log("Error login_admin: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso Administrativo - SGA COBAEV</title>
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
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col justify-between p-4 md:p-8 selection:bg-red-200">

    <div class="max-w-md mx-auto w-full text-left flex-shrink-0">
        <a href="index.php" class="inline-flex items-center text-xs font-bold tracking-wider text-zinc-400 hover:text-vino uppercase transition-colors group">
            <span class="mr-2 group-hover:-translate-x-1 transition-transform">←</span> Volver al inicio
        </a>
    </div>

    <div class="max-w-md mx-auto w-full bg-white border border-zinc-200/80 rounded-2xl p-6 md:p-8 shadow-sm my-auto space-y-6">
        
        <div class="text-center space-y-2">
            <div class="flex items-center justify-center space-x-2">
                <span class="text-vino font-serif-elegant font-bold text-2xl tracking-wider">CHECADOR</span>
                <span class="text-zinc-300 text-xl">|</span>
                <span class="text-dorado font-serif-elegant italic text-xl">Acceso Administrativo</span>
            </div>
            <p class="text-[10px] text-zinc-400 font-bold tracking-widest uppercase px-4 leading-normal">
                Sistema de control de asistencias institucional
            </p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg p-3 text-center font-medium">
                <svg class="w-4 h-4 inline-block mr-1 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4" autocomplete="off">
            
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1 tracking-wide">Usuario</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="usuario" 
                        required 
                        placeholder="Usuario administrativo" 
                        autofocus
                        autocomplete="username"
                        class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm focus:outline-none focus:border-vino transition-colors placeholder:text-zinc-400">
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1 tracking-wide">Contraseña</label>
                <div class="relative">
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="Contraseña de acceso" 
                        autocomplete="current-password"
                        class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm focus:outline-none focus:border-vino transition-colors">
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all">
                    Acceder al Checador
                </button>
            </div>
        </form>

        <!-- Información adicional -->
        <div class="border-t border-zinc-200 pt-4 space-y-2">
            <p class="text-[10px] text-zinc-500 text-center">
                <svg class="w-3 h-3 inline-block mr-1 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                Este acceso está reservado para personal autorizado
            </p>
        </div>

    </div>

    <div class="max-w-md mx-auto w-full text-center text-[9px] text-zinc-400 uppercase tracking-widest flex-shrink-0 leading-normal px-2">
        Sistema de Control de Asistencias • COBAEV
    </div>

</body>
</html>
