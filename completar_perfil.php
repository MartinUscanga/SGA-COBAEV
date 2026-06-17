<?php
/**
 * Completar Perfil - Primera vez que inicia sesión
 * El padre debe llenar sus datos reales antes de acceder al portal
 */
session_start();

if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    header("Location: login_padres.php");
    exit;
}

require_once 'conexion.php';

$matricula_alumno = $_SESSION['alumno_matricula'] ?? '';
$nombre_tutor = $_SESSION['tutor_nombre'] ?? 'Tutor';
$mensaje_error = "";

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre_tutor'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_nueva = trim($_POST['password_nueva'] ?? '');
    $password_confirmar = trim($_POST['password_confirmar'] ?? '');

    // Validaciones
    if (empty($nombre) || strlen($nombre) < 3) {
        $mensaje_error = "El nombre debe tener al menos 3 caracteres.";
    } elseif (empty($telefono) || !preg_match('/^[0-9]{10}$/', $telefono)) {
        $mensaje_error = "El teléfono debe tener exactamente 10 dígitos.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "El correo electrónico no es válido.";
    } elseif (empty($password_nueva) || strlen($password_nueva) < 6) {
        $mensaje_error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje_error = "Las contraseñas no coinciden.";
    } else {
        try {
            // Actualizar datos del tutor en TODOS sus registros (multi-alumno)
            $sql = "UPDATE tutores SET 
                        nombre_tutor = :nombre, 
                        telefono = :telefono, 
                        email = :email, 
                        password_tutor = :password,
                        perfil_completo = 1
                    WHERE nombre_tutor = :nombre_anterior 
                    AND password_tutor = :password_anterior";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nombre' => $nombre,
                'telefono' => $telefono,
                'email' => $email,
                'password' => $password_nueva,
                'nombre_anterior' => $nombre_tutor,
                'password_anterior' => $_SESSION['password_actual'] ?? $password_nueva
            ]);

            // Si no se actualizó con nombre_anterior, intentar por matrícula
            if ($stmt->rowCount() === 0) {
                $sql2 = "UPDATE tutores SET 
                            nombre_tutor = :nombre, 
                            telefono = :telefono, 
                            email = :email, 
                            password_tutor = :password,
                            perfil_completo = 1
                         WHERE matricula_alumno = :matricula";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'email' => $email,
                    'password' => $password_nueva,
                    'matricula' => $matricula_alumno
                ]);
            }

            // Actualizar sesión
            $_SESSION['tutor_nombre'] = $nombre;

            // Redirigir al portal
            header("Location: padres.php");
            exit;

        } catch (PDOException $e) {
            $mensaje_error = "Error al guardar los datos. Intente de nuevo.";
            error_log("Error completar_perfil.php: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#5c1931">
    <title>Completar Perfil - SGA COBAEV</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col selection:bg-red-200">

    <!-- Header -->
    <header class="bg-vino px-4 py-4 text-center">
        <p class="text-white font-serif-elegant font-bold text-lg tracking-wide">SGA COBAEV</p>
        <p class="text-white/50 text-[10px] font-medium uppercase tracking-widest mt-0.5">Complete su perfil para continuar</p>
    </header>

    <!-- Main -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="max-w-md w-full space-y-5">

            <!-- Bienvenida -->
            <div class="text-center space-y-2">
                <div class="w-16 h-16 mx-auto bg-dorado/10 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-dorado" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-zinc-800 font-serif-elegant">¡Bienvenido/a al Portal!</h1>
                <p class="text-xs text-zinc-500 leading-relaxed max-w-xs mx-auto">
                    Es la primera vez que inicia sesión. Por favor, complete sus datos reales para poder usar el sistema.
                </p>
            </div>

            <!-- Error -->
            <?php if (!empty($mensaje_error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl p-3 text-center font-medium">
                    <?= htmlspecialchars($mensaje_error) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                <div class="p-5 space-y-4">

                    <!-- Nombre completo -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Nombre completo</label>
                        <input 
                            type="text" 
                            name="nombre_tutor" 
                            required
                            minlength="3"
                            placeholder="Ej. María López García"
                            value="<?= htmlspecialchars($_POST['nombre_tutor'] ?? '') ?>"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Teléfono celular (10 dígitos)</label>
                        <input 
                            type="tel" 
                            name="telefono" 
                            required
                            maxlength="10"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="Ej. 2281234567"
                            value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm font-mono focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400 placeholder:font-sans">
                        <p class="text-[10px] text-zinc-400 mt-1">Se usará para recuperar su contraseña</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Correo electrónico (opcional)</label>
                        <input 
                            type="email" 
                            name="email" 
                            placeholder="Ej. correo@ejemplo.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Separador -->
                    <div class="flex items-center space-x-3 py-1">
                        <div class="h-px bg-zinc-200 flex-grow"></div>
                        <span class="text-[9px] text-zinc-400 uppercase font-bold tracking-wider">Nueva contraseña</span>
                        <div class="h-px bg-zinc-200 flex-grow"></div>
                    </div>

                    <!-- Contraseña nueva -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Crear su contraseña</label>
                        <input 
                            type="password" 
                            name="password_nueva" 
                            required
                            minlength="6"
                            placeholder="Mínimo 6 caracteres"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Confirmar contraseña -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Confirmar contraseña</label>
                        <input 
                            type="password" 
                            name="password_confirmar" 
                            required
                            minlength="6"
                            placeholder="Repita la contraseña"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Botón -->
                    <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-bold text-xs tracking-wider uppercase py-3.5 rounded-lg shadow-sm active:scale-[0.98] transition-all mt-2">
                        Completar mi perfil
                    </button>

                </div>
            </form>

            <!-- Info -->
            <p class="text-center text-[10px] text-zinc-400 leading-relaxed">
                Sus datos se almacenan de forma segura y solo se usan para notificaciones escolares.
            </p>

        </div>
    </main>

    <!-- Script: solo números en teléfono -->
    <script>
        document.querySelector('input[name="telefono"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>

</body>
</html>
