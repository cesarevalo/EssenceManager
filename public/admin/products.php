<?php
$page_title = 'Gestión de Productos';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// --- LÓGICA DEL BUSCADOR ---
$search_term = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search_term)) {
    $search_like = '%' . $search_term . '%';
    $where_clause = "WHERE (p.nombre LIKE ? OR m.nombre LIKE ? OR p.sku LIKE ? OR p.codigo_barra LIKE ?)";
    $params = [$search_like, $search_like, $search_like, $search_like];
}

// --- CONSULTA PRINCIPAL ---
$query = "
    SELECT 
        p.id, p.nombre, p.precio_usdt, p.costo_promedio_usdt, p.stock, p.visible, p.imagen_url, p.tamano_ml,
        m.nombre AS marca_nombre, 
        GROUP_CONCAT(c.nombre SEPARATOR ', ') AS categorias_nombres
    FROM 
        productos p
    LEFT JOIN 
        marcas m ON p.id_marca = m.id
    LEFT JOIN 
        producto_categorias pc ON p.id = pc.id_producto
    LEFT JOIN 
        categorias c ON pc.id_categoria = c.id
    {$where_clause}
    GROUP BY
        p.id
    ORDER BY 
        p.id DESC
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>

    <div class="row mb-3">
        <div class="col-md-6">
            <a href="product_form.php" class="btn btn-success"><i class="fas fa-plus"></i> Añadir Nuevo Producto</a>
        </div>
        <div class="col-md-6">
            <form action="products.php" method="GET" class="d-flex">
                <input class="form-control me-2" type="search" placeholder="Buscar por nombre, marca, SKU..." name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                <button class="btn btn-outline-primary" type="submit">Buscar</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h5>Listado de Productos</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Imagen</th>
                            <th>Nombre</th>
                            <th>Tamaño</th>
                            <th>Marca</th>
                            <th>Categorías</th>
                            <th>Costo Promedio</th>
                            <th>Precio Venta</th>
                            <th>Stock</th>
                            <th>Visible</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="10" class="text-center">No se encontraron productos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($producto['imagen_url'])): ?>
                                        <img src="<?php echo BASE_URL; ?>public/uploads/products/<?php echo htmlspecialchars($producto['imagen_url']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" width="50">
                                    <?php else: ?>
                                        <img src="<?php echo BASE_URL; ?>public/images/placeholder.png" alt="No image" width="50">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($producto['tamano_ml']); ?> ml</td>
                                <td><?php echo htmlspecialchars($producto['marca_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($producto['categorias_nombres']); ?></td>
                                <td class="fw-bold text-danger">$<?php echo number_format($producto['costo_promedio_usdt'], 2); ?></td>
                                <td class="fw-bold text-success"><?php echo number_format($producto['precio_usdt'], 2); ?> USDT</td>
                                <td><?php echo htmlspecialchars($producto['stock']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $producto['visible'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $producto['visible'] ? 'Sí' : 'No'; ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <a href="product_movements.php?id=<?php echo $producto['id']; ?>" class="btn btn-sm btn-info" title="Historial de Movimientos"><i class="fas fa-clipboard-list"></i></a>
                                    <a href="product_form.php?edit_id=<?php echo $producto['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <form action="../../app/crud/products_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este producto?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>