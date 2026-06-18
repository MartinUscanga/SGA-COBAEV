<?php
/**
 * Shared header bar for all admin pages
 * SGA COBAEV - Panel Administrativo
 * 
 * Required variable: $page_header (string) - The page header title
 */
?>
    <!-- Main Content -->
    <main class="flex-1 md:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-zinc-200 px-4 md:px-8 py-4 flex items-center justify-between sticky top-0 z-10">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-zinc-600 hover:text-vino">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h2 class="font-serif-elegant text-xl font-bold text-vino"><?= htmlspecialchars($page_header ?? 'Panel Admin') ?></h2>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-zinc-500 hidden sm:inline"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></span>
                <a href="../logout.php" class="text-xs font-bold text-white bg-vino hover:bg-opacity-90 px-4 py-2 rounded-lg transition-colors">Cerrar Sesion</a>
            </div>
        </header>
