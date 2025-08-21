<?php
$page_title = 'Gestión de Métodos de Pago';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$edit_mode = false;
$metodo_a_editar = ['id' => '', 'nombre_metodo' => '', 'tipo_moneda' => '', 'datos_pago' => '', 'activo' => 1];

if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $id_metodo = filter_var($_GET['edit_id'], FILTER_SANITIZE_NUMBER_INT);
    $stmt_edit = $db->prepare("SELECT * FROM metodos_pago_config WHERE id = ?");
    $stmt_edit->execute([$id_metodo]);
    $metodo_a_editar = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

$stmt_metodos = $db->prepare("SELECT * FROM metodos_pago_config ORDER BY tipo_moneda, nombre_metodo ASC");
$stmt_metodos->execute();
$metodos = $stmt_metodos->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <p>Configura los métodos de pago que tus clientes verán al reportar un abono.</p>
    
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
                <div class="card-header"><h5><?php echo $edit_mode ? 'Editar Método' : 'Añadir Nuevo Método'; ?></h5></div>
                <div class="card-body">
                    <form action="../../app/crud/metodos_pago_process.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>">
                        <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?php echo $metodo_a_editar['id']; ?>"><?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nombre del Método</label>
                            <input type="text" class="form-control" name="nombre_metodo" value="<?php echo htmlspecialchars($metodo_a_editar['nombre_metodo']); ?>" placeholder="Ej: Binance, Pago Móvil" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Moneda</label>
                            <select name="tipo_moneda" class="form-select" required>
                                <option value="USDT" <?php echo ($metodo_a_editar['tipo_moneda'] == 'USDT') ? 'selected' : ''; ?>>USDT</option>
                                <option value="VES" <?php echo ($metodo_a_editar['tipo_moneda'] == 'VES') ? 'selected' : ''; ?>>VES (Bs.)</option>
                                <option value="USD" <?php echo ($metodo_a_editar['tipo_moneda'] == 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Datos para el Pago</label>
                            <textarea class="form-control" name="datos_pago" rows="4" placeholder="Ej: Correo: topperfumes@email.com, CI: V-12345678, Tel: 0412-..." required><?php echo htmlspecialchars($metodo_a_editar['datos_pago']); ?></textarea>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Estado</label>
                             <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" <?php echo ($metodo_a_editar['activo'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo">Activo / Visible para el cliente</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Actualizar' : 'Guardar'; ?></button>
                        <?php if ($edit_mode): ?><a href="metodos_pago.php" class="btn btn-secondary">Cancelar</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5>Métodos de Pago Configurados</h5></div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead><tr><th>Nombre</th><th>Moneda</th><th>Datos</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($metodos as $metodo): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($metodo['nombre_metodo']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo $metodo['tipo_moneda']; ?></span></td>
                                <td><?php echo nl2br(htmlspecialchars($metodo['datos_pago'])); ?></td>
                                <td><span class="badge bg-<?php echo $metodo['activo'] ? 'success' : 'danger'; ?>"><?php echo $metodo['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                                <td class="text-nowrap">
                                    <a href="metodos_pago.php?edit_id=<?php echo $metodo['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <form action="../../app/crud/metodos_pago_process.php" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro?');">
                                        <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $metodo['id']; ?>">
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