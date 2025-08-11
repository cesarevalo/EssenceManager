<?php
$page_title = 'Editar Cliente';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: clientes.php');
    exit;
}

$id_cliente = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id_cliente]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $_SESSION['message'] = 'Cliente no encontrado.';
    $_SESSION['message_type'] = 'danger';
    header('Location: clientes.php');
    exit;
}

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mt-4 mb-0"><?php echo $page_title; ?>: <?php echo htmlspecialchars(strtoupper($cliente['nombre'] . ' ' . $cliente['apellido'])); ?></h1>
        <a href="clientes.php" class="btn btn-secondary mt-4"><i class="fas fa-arrow-left me-2"></i>Volver al Listado</a>
    </div>

    <form action="../../app/crud/clientes_process.php" method="POST">
        <input type="hidden" name="action" value="update_client">
        <input type="hidden" name="id_cliente" value="<?php echo htmlspecialchars($cliente['id']); ?>">

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>Información Personal</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="nombre" class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" id="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" style="text-transform:uppercase;" required></div>
                            <div class="col-md-6 mb-3"><label for="apellido" class="form-label">Apellido</label><input type="text" class="form-control" name="apellido" id="apellido" value="<?php echo htmlspecialchars($cliente['apellido']); ?>" style="text-transform:uppercase;"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($cliente['email']); ?>"></div>
                            <div class="col-md-6 mb-3"><label for="telefono" class="form-label">Teléfono</label><input type="tel" class="form-control" name="telefono" id="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" required></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label for="cedula" class="form-label">Cédula</label><input type="text" class="form-control" name="cedula" id="cedula" value="<?php echo htmlspecialchars($cliente['cedula']); ?>"></div>
                            <div class="col-md-6 mb-3"><label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label><input type="date" class="form-control" name="fecha_nacimiento" id="fecha_nacimiento" value="<?php echo htmlspecialchars($cliente['fecha_nacimiento']); ?>"></div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña (Opcional)</label><input type="password" class="form-control" name="password" id="password"><small class="form-text text-muted">Deja este campo en blanco para no cambiar la contraseña.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>Configuraciones</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Permitir Comprar a Crédito</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="permite_credito" value="1" id="permite_credito" <?php echo ($cliente['permite_credito'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="permite_credito">No / Sí</label>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Estado de la Cuenta</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="activo" id="activo_si" value="1" <?php echo ($cliente['activo'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="activo_si">Activo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="activo" id="activo_no" value="0" <?php echo ($cliente['activo'] == 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label text-danger" for="activo_no">Bloqueado</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
    </form>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>