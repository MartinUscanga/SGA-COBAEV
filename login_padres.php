<?php
// Inicio de la sesion para el tutor (configuracion centralizada)
require_once 'iniciar_sesion_padres.php';
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Incluimos el archivo de conexión
    require_once 'conexion.php';

    // 2. Limpiamos y preparamos los datos recibidos del formulario
    $matricula = strtoupper(trim($_POST['matricula']));
    $password_tutor = trim($_POST['password_tutor']);
    
    try {
        // 3. Consultamos si existe un tutor vinculado a esa matrícula exacta
        $sql = "SELECT t.*, a.nombre AS nombre_alumno, a.apellido_paterno AS apellido_paterno, a.apellido_materno AS apellido_materno 
                FROM tutores t
                INNER JOIN alumnos a ON t.matricula_alumno = a.matricula
                WHERE t.matricula_alumno = :matricula";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['matricula' => $matricula]);
        $tutor = $stmt->fetch();

        // 4. Validamos las credenciales
        if ($tutor && $password_tutor === $tutor['password_tutor']) {
            
            // 5. Buscar TODOS los alumnos vinculados al mismo tutor (mismo nombre_tutor y password_tutor)
            $sql_todos = "SELECT t.id_tutor, t.matricula_alumno, a.nombre, a.apellido_paterno, a.apellido_materno 
                          FROM tutores t
                          INNER JOIN alumnos a ON t.matricula_alumno = a.matricula
                          WHERE t.nombre_tutor = :nombre_tutor AND t.password_tutor = :password_tutor";
            $stmt_todos = $pdo->prepare($sql_todos);
            $stmt_todos->execute([
                'nombre_tutor' => $tutor['nombre_tutor'],
                'password_tutor' => $tutor['password_tutor']
            ]);
            $todos_alumnos = $stmt_todos->fetchAll();

            // Construir array de alumnos vinculados
            $alumnos_sesion = [];
            foreach ($todos_alumnos as $alumno_row) {
                $alumnos_sesion[] = [
                    'matricula' => $alumno_row['matricula_alumno'],
                    'nombre_completo' => $alumno_row['nombre'] . ' ' . $alumno_row['apellido_paterno'] . ' ' . $alumno_row['apellido_materno']
                ];
            }

            // Si por alguna razon no se encontraron alumnos, usar el original
            if (empty($alumnos_sesion)) {
                $alumnos_sesion[] = [
                    'matricula' => $tutor['matricula_alumno'],
                    'nombre_completo' => $tutor['nombre_alumno'] . ' ' . $tutor['apellido_paterno'] . ' ' . $tutor['apellido_materno']
                ];
            }

            // Guardamos las variables de sesion (multi-alumno)
            $_SESSION['tutor_autenticado'] = true;
            $_SESSION['tutor_nombre']      = $tutor['nombre_tutor'];
            $_SESSION['tutor_id']          = $tutor['id_tutor'];
            $_SESSION['alumnos']           = $alumnos_sesion;
            $_SESSION['password_actual']   = $tutor['password_tutor'];

            // Variables de compatibilidad (alumno activo = el que se uso para login)
            $_SESSION['alumno_matricula']  = $tutor['matricula_alumno'];
            $_SESSION['alumno_nombre']     = $tutor['nombre_alumno'] . ' ' . $tutor['apellido_paterno'] . ' ' . $tutor['apellido_materno'];
            
            // Regenerar ID de sesion para prevenir session fixation
            session_regenerate_id(true);

            // Verificar si es primera vez (perfil no completado)
            if (isset($tutor['perfil_completo']) && $tutor['perfil_completo'] == 0) {
                header("Location: completar_perfil.php");
                exit;
            }

            // Redireccionamos al portal de seguimiento (padres.php)
            header("Location: padres.php");
            exit;
        } else {
            // Mensaje generico de seguridad
            $error_message = "La matrícula o clave de acceso no coinciden con nuestros registros.";
        }

    } catch (PDOException $e) {
        $error_message = "Error temporal en el servidor. Por favor, intente más tarde.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#5c1931">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="manifest" href="manifest.json">
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
                <span class="text-vino font-serif-elegant font-bold text-2xl tracking-wider">SGA</span>
                <span class="text-zinc-300 text-xl">|</span>
                <span class="text-dorado font-serif-elegant italic text-xl">Padres de Familia</span>
            </div>
            <p class="text-[10px] text-zinc-400 font-bold tracking-widest uppercase px-4 leading-normal">
                Consulta y monitoreo de accesos escolares en tiempo real
            </p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg p-3 text-center font-medium">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1 tracking-wide">Matrícula del Alumno</label>
                <div class="relative">
                    <input type="text" name="matricula" required placeholder="Ej. 123310070" inputmode="numeric"
                           class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm font-mono focus:outline-none focus:border-vino transition-colors placeholder:font-sans placeholder:text-zinc-400 uppercase">
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                </div>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide">Clave de Acceso</label>
                    <a href="recuperar_clave.php" class="text-[10px] font-semibold text-dorado hover:underline">Recuperar clave</a>
                </div>
                <div class="relative">
                    <input type="password" name="password_tutor" required placeholder="Introduce tu clave privada" 
                           class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm focus:outline-none focus:border-vino transition-colors">
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all">
                    Ingresar al Monitor
                </button>
            </div>
        </form>

    </div>

    <div class="max-w-md mx-auto w-full text-center text-[9px] text-zinc-400 uppercase tracking-widest flex-shrink-0 leading-normal px-2">
        Protección de datos conforme a la legislación escolar vigente
    </div>

</body>
</html>
