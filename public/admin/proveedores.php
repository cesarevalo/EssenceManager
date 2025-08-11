<?php
$page_title = 'Gestión de Proveedores';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$edit_mode = false;
$proveedor_a_editar = ['id' => '', 'nombre' => '', 'rif' => '', 'telefono' => '', 'email' => '', 'direccion' => ''];

if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $id_proveedor = filter_var($_GET['edit_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt_edit = $db->prepare("SELECT * FROM proveedores WHERE id = :id");
    $stmt_edit->bindParam(':id', $id_proveedor);
    $stmt_edit->execute();
    $proveedor_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

$stmt_proveedores = $db->prepare("SELECT id, nombre, rif, telefono, email FROM proveedores ORDER BY nombre ASC");
$stmt_proveedores->execute();
$proveedores = $stmt_proveedores->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5><?php echo $edit_mode ? 'Editar Proveedor' : 'Añadir Nuevo Proveedor'; ?></h5></div>
                <div class="card-body">
                    <form action="../../app/crud/proveedores_process.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>">
                        <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?php echo htmlspecialchars($proveedor_a_editar['id']); ?>"><?php endif; ?>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre del Proveedor</label>
                            <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($proveedor_a_editar['nombre']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="rif" class="form-label">RIF (Opcional)</label>
                            <input type="text" class="form-control" name="rif" value="<?php echo htmlspecialchars($proveedor_a_editar['rif']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                            <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($proveedor_a_editar['telefono']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email (Opcional)</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($proveedor_a_editar['email']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="direccion" class="form-label">Dirección (Opcional)</label>
                            <textarea class="form-control" name="direccion"><?php echo htmlspecialchars($proveedor_a_editar['direccion']); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($edit_mode): ?><a href="proveedores.php" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5>Listado de Proveedores</h5></div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead><tr><th>Nombre</th><th>RIF</th><th>Teléfono</th><th>Email</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($proveedores as $proveedor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($proveedor['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['rif']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($proveedor['email']); ?></td>
                                <td>
                                    <a href="proveedores.php?edit_id=<?php echo $proveedor['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form action="../../app/crud/proveedores_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro?');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $proveedor['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>