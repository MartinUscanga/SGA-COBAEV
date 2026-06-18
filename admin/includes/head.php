<?php
/**
 * Shared <head> section for all admin pages
 * SGA COBAEV - Panel Administrativo
 * 
 * Required variable: $page_title (string) - The page title
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($page_title ?? 'Panel Admin SGA COBAEV') ?></title>
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
        .bg-dorado { background-color: #a48253; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex">
