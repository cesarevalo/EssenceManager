<?php
session_start();
require_once '../config/config.php';
require_once '../config/Database.php';

// Seguridad: Si el cliente no ha iniciado sesión, lo redirigimos al login
if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$id_cliente = $_SESSION['client_id'];
$database = new Database();
$db = $database->getConnection();

// Obtenemos los datos actuales del cliente para rellenar el formulario
$stmt_cliente = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt_cliente->execute([$id_cliente]);
$cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

$page_title = 'Editar Mi Perfil';
require_once '../templates/public/header_public.php';
?>

<div class="container">
    <h1 class="my-4">Editar Mi Perfil</h1>

    <div class="card">
        <div class="card-body">
            <form action="../app/client_profile_process.php" method="POST">
                <h5 class="card-title">Información Personal</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" style="text-transform:uppercase;" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control" name="apellido" id="apellido" value="<?php echo htmlspecialchars($cliente['apellido']); ?>" style="text-transform:uppercase;">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email (no se puede cambiar)</label>
                        <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($cliente['email']); ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="telefono" class="form-label">Teléfono con Whatsapp</label>
                        <input type="tel" class="form-control" name="telefono" id="telefono" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" required>
                    </div>
                </div>
                
                <hr class="my-4">

                <h5 class="card-title">Cambiar Contraseña (Opcional)</h5>
                <p class="text-muted">Deja los siguientes campos en blanco si no deseas cambiar tu contraseña.</p>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Contraseña Actual</label>
                    <input type="password" class="form-control" name="current_password" id="current_password">
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                    <input type="password" class="form-control" name="new_password" id="new_password">
                </div>
                 <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                </div>

                <div class="text-end">
                    <a href="mi_cuenta.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary" style="background-color: #3D405B; border-color: #3D405B;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../templates/public/footer_public.php'; ?>