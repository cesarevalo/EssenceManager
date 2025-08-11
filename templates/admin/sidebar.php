<?php 
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>public/admin/dashboard.php">
            <img src="<?php echo BASE_URL; ?>public/images/logo.jpg" alt="Top Perfumes Ccs Logo" style="height: 60px; max-width: 100%; object-fit: contain;">
        </a>
    </div>

    <ul class="list-unstyled components">
        <p class="ms-3"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        
        <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        </li>
        <li class="<?php echo ($current_page == 'ventas.php' || $current_page == 'venta_detalle.php') ? 'active' : ''; ?>">
            <a href="ventas.php"><i class="fas fa-chart-line me-2"></i>Gestión de Ventas</a>
        </li>
        <li class="<?php echo ($current_page == 'clientes.php') ? 'active' : ''; ?>">
            <a href="clientes.php"><i class="fas fa-users me-2"></i>Clientes</a>
        </li>
        <li>
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-box-open me-2"></i>Productos
            </a>
            <ul class="collapse list-unstyled" id="productSubmenu">
                <li><a href="products.php" class="ps-4">Ver Productos</a></li>
                <li><a href="marcas.php" class="ps-4">Marcas</a></li>
                <li><a href="categorias.php" class="ps-4">Categorías</a></li>
            </ul>
        </li>
        <li class="<?php echo ($current_page == 'compras.php' || $current_page == 'compra_detalle.php') ? 'active' : ''; ?>">
            <a href="compras.php"><i class="fas fa-dolly-flatbed me-2"></i>Gestión de Compras</a>
        </li>
        <li class="<?php echo ($current_page == 'proveedores.php') ? 'active' : ''; ?>">
            <a href="proveedores.php"><i class="fas fa-truck-loading me-2"></i>Proveedores</a>
        </li>
        <li class="<?php echo ($current_page == 'balance.php') ? 'active' : ''; ?>">
            <a href="balance.php"><i class="fas fa-balance-scale me-2"></i>Balance y Reportes</a>
        </li>
         <li>
            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a>
        </li>
    </ul>
</nav>

<div id="content">
    <nav class="navbar navbar-expand-lg navbar-light bg-light top-navbar">
        <div class="container-fluid">
            <button type="button" id="sidebarCollapse" class="btn">
                <i class="fas fa-align-left"></i>
                <span>Ocultar Menú</span>
            </button>
        </div>
    </nav>