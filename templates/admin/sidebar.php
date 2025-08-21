<?php 
// Esta variable nos ayuda a saber en qué página estamos para resaltar el enlace del menú
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
        <li>
            <a href="#salesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-chart-line me-2"></i>Ventas
            </a>
            <ul class="collapse list-unstyled" id="salesSubmenu">
                <li><a href="ventas.php" class="ps-4">Gestión de Ventas</a></li>
                <li><a href="pagos_revision.php" class="ps-4">Revisión de Pagos</a></li>
            </ul>
        </li>
        <li class="<?php echo in_array($current_page, ['clientes.php', 'cliente_history.php', 'cliente_form.php']) ? 'active' : ''; ?>">
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
        <li>
            <a href="#purchasesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-dolly-flatbed me-2"></i>Compras
            </a>
            <ul class="collapse list-unstyled" id="purchasesSubmenu">
                <li><a href="compras.php" class="ps-4">Gestión de Compras</a></li>
                <li><a href="proveedores.php" class="ps-4">Proveedores</a></li>
            </ul>
        </li>
        <li>
            <a href="#settingsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-cog me-2"></i>Configuración
            </a>
            <ul class="collapse list-unstyled" id="settingsSubmenu">
                <li><a href="metodos_pago.php" class="ps-4">Métodos de Pago (Manual)</a></li>
                <li><a href="pasarelas_pago.php" class="ps-4">Pasarelas de Pago (Online)</a></li>
            </ul>
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