<?php 
// Iniciar sesión solo si no hay una activa.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Incluimos la configuración
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

// NUEVO: Calcular el total de artículos en el carrito
$cart_item_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Top Perfumes Ccs'; ?> - Tu Tienda de Perfumes</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;700&family=Playfair+Display:wght@700&display=swap">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>public/catalog.php">
        <img src="<?php echo BASE_URL; ?>public/images/logo.webp" alt="Top Perfumes Ccs Logo" style="height: 40px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>public/catalog.php">Catálogo</a>
        </li>

        <?php if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in']): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-user me-1"></i>Hola, <?php echo htmlspecialchars($_SESSION['client_name']); ?>
              </a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#">Mi Cuenta</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>public/logout_client.php">Cerrar Sesión</a></li>
              </ul>
            </li>
        <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo BASE_URL; ?>public/login.php">Iniciar Sesión</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo BASE_URL; ?>public/register.php">Registrarse</a>
            </li>
        <?php endif; ?>

        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>public/cart_view.php">
            <i class="fas fa-shopping-cart"></i> Carrito
            <?php if ($cart_item_count > 0): ?>
                <span class="badge rounded-pill bg-danger ms-1"><?php echo $cart_item_count; ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container my-4">
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
<?php endif; ?>