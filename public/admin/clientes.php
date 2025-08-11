<?php
$page_title = 'Gestión de Clientes';
require_once '../../templates/admin/header.php';
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// Lógica de Filtros y Paginación
$records_per_page = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

$where_clauses = [];
$params = [];
$filter_params_for_url = [];

// Filtro de Búsqueda
$search_term = $_GET['search'] ?? '';
if (!empty($search_term)) {
    $search_like = '%' . $search_term . '%';
    $where_clauses[] = "(nombre LIKE :search OR apellido LIKE :search OR email LIKE :search OR cedula LIKE :search OR telefono LIKE :search)";
    $params[':search'] = $search_like;
    $filter_params_for_url['search'] = $search_term;
}

$where_sql = count($where_clauses) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

// Obtener el total de registros para la paginación
$query_count = "SELECT COUNT(*) FROM clientes {$where_sql}";
$stmt_count = $db->prepare($query_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Obtener los registros de la página actual
$query = "SELECT * FROM clientes {$where_sql} ORDER BY fecha_registro DESC LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($query);

// Bindeamos los parámetros
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../../templates/admin/sidebar.php';
?>

<div class="container-fluid">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>

    <div class="card mb-4">
        <div class="card-body">
            <form action="clientes.php" method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="search" class="form-control" name="search" placeholder="Buscar por nombre, apellido, email, cédula o teléfono..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Listado de Clientes (Total: <?php echo $total_records; ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Cédula</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr><td colspan="6" class="text-center">No se encontraron clientes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(strtoupper($cliente['nombre'] . ' ' . $cliente['apellido'])); ?></td>
                                <td><?php echo htmlspecialchars($cliente['email'] ?? 'No registrado'); ?></td>
                                <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($cliente['cedula']); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($cliente['fecha_registro'])); ?></td>
                                <td>
                                    <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-warning" title="Editar Cliente"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $page - 1])); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filter_params_for_url, ['page' => $page + 1])); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../templates/admin/footer.php'; ?>