<?php
$page_title = 'Catálogo de Perfumes';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once '../templates/public/header_public.php';

$database = new Database();
$db = $database->getConnection();

// Obtener datos para los filtros
$marcas = $db->query("SELECT id, nombre FROM marcas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$max_price = $db->query("SELECT MAX(precio_usdt) FROM productos WHERE visible = 1")->fetchColumn() ?: 1000;
?>

<div class="d-lg-none mb-3">
    <div class="input-group">
        <input type="text" class="form-control" placeholder="Buscar por nombre o similitud..." id="search-input-mobile">
        <button class="btn btn-outline-secondary" type="button" id="search-clear-mobile"><i class="fas fa-times"></i></button>
    </div>
</div>

<div class="d-lg-none mb-3 text-center">
    <button class="btn btn-outline-dark w-100" type="button" id="filter-toggle-button">
        <i class="fas fa-filter me-2"></i>Mostrar Filtros
    </button>
</div>

<div class="row">
    <aside class="col-lg-3 filter-sidebar" id="filter-sidebar">
        <div class="sidebar-header d-flex justify-content-between align-items-center">
            <h4>Filtros</h4>
            <button class="btn-close d-lg-none" id="close-filter-button"></button>
        </div>
        <form id="filter-form">
            <div class="input-group mb-3 d-none d-lg-flex">
                <input type="text" class="form-control" name="search" placeholder="Buscar..." id="search-input-desktop">
                <button class="btn btn-outline-secondary" type="button" id="search-clear-desktop"><i class="fas fa-times"></i></button>
            </div>
            <hr class="d-none d-lg-block">

            <div class="mb-3">
                <h5>Marca</h5>
                <?php foreach ($marcas as $marca): ?>
                <div class="form-check">
                    <input class="form-check-input filter-change" type="checkbox" name="marcas[]" value="<?php echo $marca['id']; ?>" id="marca-<?php echo $marca['id']; ?>">
                    <label class="form-check-label" for="marca-<?php echo $marca['id']; ?>">
                        <?php echo htmlspecialchars($marca['nombre']); ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <hr>
            
            <div class="mb-3">
                <h5>Categoría</h5>
                <?php foreach ($categorias as $categoria): ?>
                <div class="form-check">
                    <input class="form-check-input filter-change" type="checkbox" name="categorias[]" value="<?php echo $categoria['id']; ?>" id="cat-<?php echo $categoria['id']; ?>">
                    <label class="form-check-label" for="cat-<?php echo $categoria['id']; ?>">
                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <hr>

            <div class="mb-3">
                <h5>Género</h5>
                <div class="form-check">
                    <input class="form-check-input filter-change" type="checkbox" name="generos[]" value="Hombre" id="gen-hombre">
                    <label class="form-check-label" for="gen-hombre">Hombre</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input filter-change" type="checkbox" name="generos[]" value="Mujer" id="gen-mujer">
                    <label class="form-check-label" for="gen-mujer">Mujer</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input filter-change" type="checkbox" name="generos[]" value="Unisex" id="gen-unisex">
                    <label class="form-check-label" for="gen-unisex">Unisex</label>
                </div>
            </div>
            <hr>
            
             <div class="mb-3">
                <h5>Precio</h5>
                <label for="price-range" class="form-label">Hasta: <span id="price-value">$<?php echo ceil($max_price); ?></span></label>
                <input type="range" class="form-range filter-change" min="0" max="<?php echo ceil($max_price); ?>" value="<?php echo ceil($max_price); ?>" id="price-range" name="precio_max">
            </div>
        </form>
    </aside>

    <div class="col-lg-9">
        <div class="text-center mb-4 d-none d-lg-block">
             <h1>Nuestro Catálogo</h1>
            <p class="lead">Descubre tu próxima fragancia favorita.</p>
        </div>
        <div id="product-grid" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 g-4">
            </div>
        <div id="no-results" class="text-center" style="display: none;">
            <h3>No se encontraron productos</h3>
            <p>Intenta ajustar tus filtros.</p>
        </div>
    </div>
</div>

<div class="overlay" id="overlay"></div>

<?php require_once '../templates/public/footer_public.php'; ?>
<script src="<?php echo BASE_URL; ?>public/js/filters.js"></script> 