<?php
$page_title = 'Formulario de Producto';
require_once '../../config/Database.php';
require_once '../../templates/admin/header.php';

$database = new Database();
$db = $database->getConnection();

$edit_mode = false;
$producto = [];
$producto_imagenes = [];
$categorias_actuales = []; // Array para las categorías ya seleccionadas

// Lógica para modo edición
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $id_producto = filter_var($_GET['edit_id'], FILTER_SANITIZE_NUMBER_INT);
    $page_title = 'Editar Producto';
    
    // Obtener datos principales del producto
    $query_edit = "SELECT * FROM productos WHERE id = :id";
    $stmt_edit = $db->prepare($query_edit);
    $stmt_edit->bindParam(':id', $id_producto);
    $stmt_edit->execute();
    $producto = $stmt_edit->fetch(PDO::FETCH_ASSOC);

    // Obtener todas las imágenes existentes del producto
    $stmt_images = $db->prepare("SELECT id, imagen_url FROM producto_imagenes WHERE id_producto = :id_producto");
    $stmt_images->bindParam(':id_producto', $id_producto);
    $stmt_images->execute();
    $producto_imagenes = $stmt_images->fetchAll(PDO::FETCH_ASSOC);

    // Obtener las categorías asignadas a este producto
    $stmt_cats = $db->prepare("SELECT id_categoria FROM producto_categorias WHERE id_producto = ?");
    $stmt_cats->execute([$id_producto]);
    $categorias_actuales = $stmt_cats->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Obtener todas las Marcas y todas las Categorías disponibles
$marcas = $db->query("SELECT id, nombre FROM marcas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$categorias_disponibles = $db->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $edit_mode ? 'Editar Producto' : 'Añadir Nuevo Producto'; ?></h1>

    <form action="../../app/crud/products_process.php" method="POST" enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($producto['id']); ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Producto</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="5"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="notas_salida" class="form-label">Notas de Salida</label>
                                <textarea class="form-control" id="notas_salida" name="notas_salida" rows="3"><?php echo htmlspecialchars($producto['notas_salida'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="notas_corazon" class="form-label">Notas de Corazón</label>
                                <textarea class="form-control" id="notas_corazon" name="notas_corazon" rows="3"><?php echo htmlspecialchars($producto['notas_corazon'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="notas_base" class="form-label">Notas de Base</label>
                                <textarea class="form-control" id="notas_base" name="notas_base" rows="3"><?php echo htmlspecialchars($producto['notas_base'] ?? ''); ?></textarea>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="similitud" class="form-label">Similitud Olfativa (Opcional)</label>
                            <input type="text" class="form-control" id="similitud" name="similitud" value="<?php echo htmlspecialchars($producto['similitud'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="id_marca" class="form-label">Marca</label>
                            <select class="form-select" id="id_marca" name="id_marca" required>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?php echo $marca['id']; ?>" <?php echo (($producto['id_marca'] ?? '') == $marca['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($marca['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="categorias" class="form-label">Categorías</label>
                            <select class="form-select" id="categorias" name="categorias[]" multiple required>
                                <?php foreach ($categorias_disponibles as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" <?php echo in_array($categoria['id'], $categorias_actuales) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="genero" class="form-label">Género</label>
                                <select class="form-select" id="genero" name="genero" required>
                                    <option value="Hombre" <?php echo (($producto['genero'] ?? '') == 'Hombre') ? 'selected' : ''; ?>>Hombre</option>
                                    <option value="Mujer" <?php echo (($producto['genero'] ?? '') == 'Mujer') ? 'selected' : ''; ?>>Mujer</option>
                                    <option value="Unisex" <?php echo (($producto['genero'] ?? '') == 'Unisex') ? 'selected' : ''; ?>>Unisex</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="concentracion" class="form-label">Concentración</label>
                                <select class="form-select" id="concentracion" name="concentracion" required>
                                    <option value="EDP" <?php echo (($producto['concentracion'] ?? '') == 'EDP') ? 'selected' : ''; ?>>EDP</option>
                                    <option value="EDT" <?php echo (($producto['concentracion'] ?? '') == 'EDT') ? 'selected' : ''; ?>>EDT</option>
                                    <option value="EDC" <?php echo (($producto['concentracion'] ?? '') == 'EDC') ? 'selected' : ''; ?>>EDC</option>
                                    <option value="Parfum" <?php echo (($producto['concentracion'] ?? '') == 'Parfum') ? 'selected' : ''; ?>>Parfum</option>
                                    <option value="Aceite" <?php echo (($producto['concentracion'] ?? '') == 'Aceite') ? 'selected' : ''; ?>>Aceite</option>
                                </select>
                            </div>
                        </div>
                         <div class="mb-3">
                            <label for="tamano_ml" class="form-label">Tamaño (ml)</label>
                            <input type="number" class="form-control" id="tamano_ml" name="tamano_ml" value="<?php echo htmlspecialchars($producto['tamano_ml'] ?? '100'); ?>" required>
                        </div>
                         <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="precio_usdt" class="form-label">Precio (USDT)</label>
                                <input type="number" step="0.01" class="form-control" id="precio_usdt" name="precio_usdt" value="<?php echo htmlspecialchars($producto['precio_usdt'] ?? '0.00'); ?>" required>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="stock" class="form-label">Stock</label>
                                <input type="number" class="form-control" id="stock" name="stock" value="<?php echo htmlspecialchars($producto['stock'] ?? '0'); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($producto['sku'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="codigo_barra" class="form-label">Código de Barra</label>
                                <input type="text" class="form-control" id="codigo_barra" name="codigo_barra" value="<?php echo htmlspecialchars($producto['codigo_barra'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="estado_disponibilidad" class="form-label">Estado de Disponibilidad</label>
                            <select class="form-select" id="estado_disponibilidad" name="estado_disponibilidad" required>
                                <option value="Disponible" <?php echo (($producto['estado_disponibilidad'] ?? '') == 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                <option value="Por llegar" <?php echo (($producto['estado_disponibilidad'] ?? '') == 'Por llegar') ? 'selected' : ''; ?>>Por llegar</option>
                                <option value="Agotado" <?php echo (($producto['estado_disponibilidad'] ?? '') == 'Agotado') ? 'selected' : ''; ?>>Agotado</option>
                            </select>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Visible en Catálogo</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visible" id="visible_si" value="1" <?php echo (($producto['visible'] ?? 1) == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="visible_si">Sí</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="visible" id="visible_no" value="0" <?php echo (($producto['visible'] ?? 1) == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="visible_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Imágenes del Producto</h5>
            </div>
            <div class="card-body">
                <?php if ($edit_mode && !empty($producto_imagenes)): ?>
                    <h6>Imágenes Actuales</h6>
                    <div class="row">
                        <?php foreach ($producto_imagenes as $img): ?>
                        <div class="col-md-3 text-center mb-3">
                            <img src="<?php echo BASE_URL; ?>public/uploads/products/<?php echo htmlspecialchars($img['imagen_url']); ?>" class="img-thumbnail mb-2" style="height: 150px; object-fit: cover;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="imagen_principal" value="<?php echo htmlspecialchars($img['imagen_url']); ?>" id="main_img_<?php echo $img['id']; ?>" <?php echo ($producto['imagen_url'] == $img['imagen_url']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="main_img_<?php echo $img['id']; ?>">Principal</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="eliminar_imagenes[]" value="<?php echo $img['id']; ?>" id="delete_img_<?php echo $img['id']; ?>">
                                <label class="form-check-label text-danger" for="delete_img_<?php echo $img['id']; ?>">Eliminar</label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                <?php endif; ?>

                <h6>Añadir Nuevas Imágenes</h6>
                <div class="mb-3">
                    <label for="imagenes" class="form-label">Puedes seleccionar varias imágenes a la vez.</label>
                    <input class="form-control" type="file" id="imagenes" name="imagenes[]" multiple>
                </div>
            </div>
        </div>
        
        <div class="text-end mt-4">
            <a href="products.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar Producto' : 'Guardar Producto'; ?></button>
        </div>
    </form>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>

<script>
$(document).ready(function() {
    $('#categorias').select2({
        theme: "bootstrap-5",
        placeholder: "Selecciona una o más categorías",
    });
});
</script>