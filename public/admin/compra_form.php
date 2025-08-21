<?php
$page_title = 'Registrar Nueva Compra';
require_once '../../templates/admin/header.php';
// NUEVO: Conectar a la BD para obtener proveedores
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();
$stmt_proveedores = $db->prepare("SELECT id, nombre FROM proveedores ORDER BY nombre ASC");
$stmt_proveedores->execute();
$proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>

    <div class="card" style="max-width: 600px;">
        <div class="card-body">
            <form action="../../app/crud/compras_process.php" method="POST">
                <input type="hidden" name="action" value="create_compra">

                <div class="mb-3">
                    <label for="fecha_compra" class="form-label">Fecha de la Compra</label>
                    <input type="date" class="form-control" id="fecha_compra" name="fecha_compra" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="id_proveedor" class="form-label">Proveedor (Opcional)</label>
                    <select class="form-select" id="id_proveedor" name="id_proveedor">
                        <option value="">-- Sin Proveedor --</option>
                        <?php foreach ($proveedores as $proveedor): ?>
                            <option value="<?php echo $proveedor['id']; ?>"><?php echo htmlspecialchars($proveedor['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="text-end">
                    <a href="compras.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Crear y Añadir Productos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>