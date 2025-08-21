<?php
require_once __DIR__ . '/../../config/config.php';
// Iniciar sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guardián de seguridad: si no hay sesión de admin, redirige al login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Usamos BASE_URL para una redirección robusta
    header('Location: ' . BASE_URL . 'public/admin/login.php');
    exit;
}

// Helper function para chequear permisos
function has_permission($permission) {
    return isset($_SESSION['user_permissions'][$permission]) && $_SESSION['user_permissions'][$permission] === true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Top Perfumes Ccs'; ?> - Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&display=swap">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
</head>
<body>
<div class="wrapper">