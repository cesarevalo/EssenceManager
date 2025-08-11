<?php
$page_title = 'Gestión de Marcas';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

// Conexión a la BD
$database = new Database();
$db = $database->getConnection();

// Variables para el formulario de edición
$edit_mode = false;
$marca_a_editar = ['id' => '', 'nombre' => ''];

// Lógica para modo edición
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $id_marca = filter_var($_GET['edit_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $query_edit = "SELECT id, nombre FROM marcas WHERE id = :id";
    $stmt_edit = $db->prepare($query_edit);
    $stmt_edit->bindParam(':id', $id_marca);
    $stmt_edit->execute();
    $marca_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

// Obtener todas las marcas para la lista
$query_marcas = "SELECT id, nombre FROM marcas ORDER BY nombre ASC";
$stmt_marcas = $db->prepare($query_marcas);
$stmt_marcas->execute();
$marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);

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
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $edit_mode ? 'Editar Marca' : 'Añadir Nueva Marca'; ?></h5>
                </div>
                <div class="card-body">
                    <form action="../../app/crud/marcas_process.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($marca_a_editar['id']); ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Marca</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($marca_a_editar['nombre']); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($edit_mode): ?>
                            <a href="marcas.php" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Listado de Marcas</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($marcas as $marca): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($marca['id']); ?></td>
                                <td><?php echo htmlspecialchars($marca['nombre']); ?></td>
                                <td>
                                    <a href="marcas.php?edit_id=<?php echo $marca['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    
                                    <form action="../../app/crud/marcas_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta marca?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $marca['id']; ?>">
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

<?php
require_once '../../templates/admin/footer.php';
?>