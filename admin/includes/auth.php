<?php
/**
 * Autenticacion y verificacion de rol para el panel administrativo
 * SGA COBAEV
 *
 * Solo permite acceso a usuarios con rol Superadmin o Admin.
 * Incluir este archivo al inicio de cada pagina del panel admin.
 */
session_start();

// Verificar que el usuario esta autenticado
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php?redirect=dashboard");
    exit;
}

// Verificar que el rol del usuario es Superadmin o Admin
$roles_permitidos = ['Superadmin', 'Admin'];
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], $roles_permitidos)) {
    header("Location: ../login_admin.php?redirect=dashboard");
    exit;
}
