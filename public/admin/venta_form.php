<?php
$page_title = 'Registrar Nueva Venta';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

// Obtener todos los clientes para el buscador
$database = new Database();
$db = $database->getConnection();
$stmt_clientes = $db->query("SELECT id, nombre, apellido, cedula FROM clientes ORDER BY nombre ASC");
$clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <p>Paso 1: Define el cliente para esta venta.</p>

    <div class="card" style="max-width: 800px;">
        <div class="card-body">
            <form action="../../app/crud/ventas_process.php" method="POST">
                <input type="hidden" name="action" value="create_manual_sale">

                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="client_type" id="existing_client_radio" value="existing" checked>
                    <label class="form-check-label" for="existing_client_radio">Usar Cliente Existente</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="client_type" id="new_client_radio" value="new">
                    <label class="form-check-label" for="new_client_radio">Crear Cliente Nuevo (Express)</label>
                </div>
                <hr>

                <div id="existing-client-section" class="mb-3">
                    <label for="id_cliente" class="form-label">Buscar Cliente</label>
                    <select class="form-select" id="id_cliente" name="id_cliente">
                        <option value="">Escribe para buscar un cliente...</option>
                        <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php echo htmlspecialchars(strtoupper($cliente['nombre'] . ' ' . $cliente['apellido'])) . ' (CI: ' . htmlspecialchars($cliente['cedula']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="new-client-section" class="mb-3" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre_nuevo" class="form-label">Nombre del Nuevo Cliente</label>
                            <input type="text" class="form-control" name="nombre_nuevo" id="nombre_nuevo" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono_nuevo" class="form-label">Teléfono con Whatsapp</label>
                            <div class="input-group">
                                <span class="input-group-text">+58</span>
                                <input type="tel" class="form-control" name="telefono_nuevo" id="telefono_nuevo" placeholder="4121234567">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="ventas.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Siguiente: Añadir Productos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>

<script>
$(document).ready(function() {
    // Inicializar Select2
    $('#id_cliente').select2({
        theme: "bootstrap-5",
        placeholder: "Busca por nombre, apellido o cédula",
    });

    // Lógica para mostrar/ocultar secciones
    $('input[name="client_type"]').on('change', function() {
        if (this.value === 'existing') {
            $('#existing-client-section').show();
            $('#id_cliente').prop('required', true);
            $('#new-client-section').hide();
            $('#nombre_nuevo').prop('required', false);
            $('#telefono_nuevo').prop('required', false);
        } else {
            $('#existing-client-section').hide();
            $('#id_cliente').prop('required', false);
            $('#new-client-section').show();
            $('#nombre_nuevo').prop('required', true);
            $('#telefono_nuevo').prop('required', true);
        }
    });

    // Disparar el evento 'change' al cargar para establecer el estado inicial correcto
    $('input[name="client_type"]:checked').trigger('change');
});
</script>