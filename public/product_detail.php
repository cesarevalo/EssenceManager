<?php
require_once '../config/Database.php';
session_start(); 

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: catalog.php');
    exit;
}

$id_producto = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$database = new Database();
$db = $database->getConnection();

// --- Consulta principal para obtener datos del producto ---
$query = "SELECT p.*, m.nombre AS marca_nombre FROM productos p LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.id = :id AND p.visible = 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id_producto);
$stmt->execute();
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    header('Location: catalog.php');
    exit;
}

// --- LÓGICA ADICIONAL PARA ESTADOS DE DISPONIBILIDAD ---
$is_coming_soon = false;
$already_subscribed = false;

if ($producto['stock'] <= 0) {
    // 1. Determinar si está "Por llegar" (en tránsito)
    $stmt_transit = $db->prepare("SELECT 1 FROM compras_detalle cd JOIN compras c ON cd.id_compra = c.id WHERE cd.id_producto = ? AND c.estado = 'En tránsito' LIMIT 1");
    $stmt_transit->execute([$id_producto]);
    if ($stmt_transit->fetch()) {
        $is_coming_soon = true;
    }

    // 2. Verificar si el cliente ya solicitó notificación (para ambos casos: por llegar o agotado)
    if (isset($_SESSION['client_id'])) {
        $stmt_check = $db->prepare("SELECT 1 FROM notificaciones_stock WHERE id_cliente = ? AND id_producto = ?");
        $stmt_check->execute([$_SESSION['client_id'], $id_producto]);
        if ($stmt_check->fetch()) {
            $already_subscribed = true;
        }
    }
}

// --- Obtener la lista de categorías para este producto ---
$stmt_cats = $db->prepare("SELECT c.nombre FROM producto_categorias pc JOIN categorias c ON pc.id_categoria = c.id WHERE pc.id_producto = ?");
$stmt_cats->execute([$id_producto]);
$categorias_producto = $stmt_cats->fetchAll(PDO::FETCH_COLUMN, 0);

// --- Obtener imágenes de la galería ---
$stmt_gallery = $db->prepare("SELECT imagen_url FROM producto_imagenes WHERE id_producto = :id_producto");
$stmt_gallery->bindParam(':id_producto', $id_producto);
$stmt_gallery->execute();
$gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);

$page_title = htmlspecialchars($producto['nombre']);
require_once '../config/config.php';
require_once '../templates/public/header_public.php';
?>

<div class="container product-detail-container mt-5">
    <div class="row">
        <div class="col-md-6 text-center">
            <div class="main-image mb-3">
                <img src="uploads/products/<?php echo htmlspecialchars($producto['imagen_url'] ?? 'placeholder.png'); ?>" class="img-fluid rounded shadow-sm" alt="Imagen principal de <?php echo htmlspecialchars($producto['nombre']); ?>" id="main-product-image" style="max-height: 500px;">
            </div>
            
            <?php if (!empty($gallery_images)): ?>
            <div class="thumbnail-gallery d-flex justify-content-center flex-wrap">
                <?php foreach ($gallery_images as $image): ?>
                <div class="p-1">
                    <img src="uploads/products/<?php echo htmlspecialchars($image['imagen_url']); ?>" class="img-thumbnail product-thumbnail" alt="Miniatura de <?php echo htmlspecialchars($producto['nombre']); ?>" style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h6 class="text-muted"><?php echo htmlspecialchars($producto['marca_nombre']); ?></h6>
            <h1><?php echo htmlspecialchars($producto['nombre']); ?></h1>
            <p class="lead price mb-4"><?php echo number_format($producto['precio_usdt'], 2); ?> USDT <span class="text-muted fw-normal">/ <?php echo htmlspecialchars($producto['tamano_ml']); ?> ml</span></p>
            
            <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
            
            <?php if ($producto['stock'] > 0): ?>
                <form action="../app/cart_process.php" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $producto['id']; ?>">
                    <div class="row align-items-end">
                        <div class="col-md-5">
                            <label for="quantity" class="form-label">Cantidad:</label>
                            <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" max="<?php echo $producto['stock']; ?>">
                        </div>
                        <div class="col-md-7">
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" style="background-color: #3D405B; border-color: #3D405B;">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    <?php echo 'Añadir al Carrito (Solo quedan ' . $producto['stock'] . ')'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="mt-4">
                    <?php if ($is_coming_soon): ?>
                        <div class="d-grid">
                            <button type="button" class="btn btn-secondary btn-lg" disabled><i class="fas fa-clock me-2"></i>Próximamente</button>
                        </div>
                        <div class="mt-2">
                            <?php if ($already_subscribed): ?>
                                <div class="alert alert-success text-center">¡Ya estás en la lista! Te avisaremos.</div>
                            <?php elseif (isset($_SESSION['client_logged_in'])): ?>
                                <form action="../app/notification_process.php" method="POST" class="d-grid">
                                    <input type="hidden" name="id_producto" value="<?php echo $producto['id']; ?>">
                                    <button type="submit" class="btn btn-info btn-lg"><i class="fas fa-bell me-2"></i>Avisarme cuando llegue</button>
                                </form>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-primary btn-lg d-grid">Inicia sesión para que te avisemos</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="d-grid">
                             <button type="button" class="btn btn-danger btn-lg" disabled><i class="fas fa-times-circle me-2"></i>Agotado</button>
                        </div>
                        <div class="mt-2">
                            <?php if ($already_subscribed): ?>
                                <div class="alert alert-success text-center">¡Solicitud recibida! Te avisaremos si vuelve a estar disponible.</div>
                            <?php elseif (isset($_SESSION['client_logged_in'])): ?>
                                <form action="../app/notification_process.php" method="POST" class="d-grid">
                                    <input type="hidden" name="id_producto" value="<?php echo $producto['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-lg"><i class="fas fa-box-tissue me-2"></i>Solicitar Reposición</button>
                                </form>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-primary btn-lg d-grid">Inicia sesión para solicitar reposición</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <hr class="my-4">

            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas" type="button" role="tab">Notas Olfativas</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="detalles-tab" data-bs-toggle="tab" data-bs-target="#detalles" type="button" role="tab">Detalles</button>
                </li>
            </ul>
            <div class="tab-content pt-3" id="myTabContent">
                <div class="tab-pane fade show active" id="notas" role="tabpanel">
                    <strong>Notas de Salida:</strong><p><?php echo htmlspecialchars($producto['notas_salida']); ?></p>
                    <strong>Notas de Corazón:</strong><p><?php echo htmlspecialchars($producto['notas_corazon']); ?></p>
                    <strong>Notas de Base:</strong><p><?php echo htmlspecialchars($producto['notas_base']); ?></p>
                </div>
                <div class="tab-pane fade" id="detalles" role="tabpanel">
                    <ul class="list-unstyled">
                        <li><strong>Categorías:</strong> <?php echo implode(', ', array_map('htmlspecialchars', $categorias_producto)); ?></li>
                        <li><strong>Género:</strong> <?php echo htmlspecialchars($producto['genero']); ?></li>
                        <li><strong>Tamaño:</strong> <?php echo htmlspecialchars($producto['tamano_ml']); ?> ml</li>
                        <li><strong>Concentración:</strong> <?php echo htmlspecialchars($producto['concentracion']); ?></li>
                        <li><strong>SKU:</strong> <?php echo htmlspecialchars($producto['sku']); ?></li>
                         <?php if(!empty($producto['similitud'])): ?>
                            <li><strong>Similitud Olfativa:</strong> <?php echo htmlspecialchars($producto['similitud']); ?></li>
                         <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('main-product-image');
    const thumbnails = document.querySelectorAll('.product-thumbnail');

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            mainImage.src = this.src;
        });
    });
});
</script>