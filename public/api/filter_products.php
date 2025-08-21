<?php
header('Content-Type: application/json');
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// --- Consulta Principal con Cálculo de Estado Dinámico ---
$query = "
    SELECT 
        p.id, p.nombre, p.precio_usdt, p.imagen_url, p.tamano_ml, p.stock, m.nombre AS marca_nombre,
        CASE
            WHEN p.stock > 0 THEN 'Disponible'
            WHEN p.stock <= 0 AND EXISTS (
                SELECT 1
                FROM compras_detalle cd
                JOIN compras c ON cd.id_compra = c.id
                WHERE cd.id_producto = p.id AND c.estado = 'En tránsito'
            ) THEN 'Por llegar'
            ELSE 'Agotado'
        END AS estado_dinamico
    FROM 
        productos p
    LEFT JOIN 
        marcas m ON p.id_marca = m.id
";

$params = [];
$where_clauses = ["p.visible = 1"];
$having_clauses = [];
$param_types = '';
$execute_params = [];


// --- Lógica de Filtros ---
if (!empty($_GET['search'])) {
    $search_term = '%' . filter_var($_GET['search'], FILTER_SANITIZE_STRING) . '%';
    $where_clauses[] = "(p.nombre LIKE ? OR m.nombre LIKE ? OR p.similitud LIKE ?)";
    $execute_params[] = $search_term; $execute_params[] = $search_term; $execute_params[] = $search_term;
}
if (!empty($_GET['marcas']) && is_array($_GET['marcas'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['marcas']), '?'));
    $where_clauses[] = "p.id_marca IN ($placeholders)";
    $execute_params = array_merge($execute_params, $_GET['marcas']);
}
if (!empty($_GET['categorias']) && is_array($_GET['categorias'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['categorias']), '?'));
    $where_clauses[] = "p.id IN (SELECT id_producto FROM producto_categorias WHERE id_categoria IN ($placeholders))";
    $execute_params = array_merge($execute_params, $_GET['categorias']);
}
if (!empty($_GET['generos']) && is_array($_GET['generos'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['generos']), '?'));
    $where_clauses[] = "p.genero IN ($placeholders)";
    $execute_params = array_merge($execute_params, $_GET['generos']);
}
if (!empty($_GET['precio_max'])) {
    $where_clauses[] = "p.precio_usdt <= ?";
    $execute_params[] = $_GET['precio_max'];
}

// NUEVO: Filtro por el estado dinámico
if (!empty($_GET['disponibilidad']) && is_array($_GET['disponibilidad'])) {
    $placeholders = implode(',', array_fill(0, count($_GET['disponibilidad']), '?'));
    $having_clauses[] = "estado_dinamico IN ($placeholders)";
    $execute_params = array_merge($execute_params, $_GET['disponibilidad']);
}

// --- Finalización de la consulta ---
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}
$query .= " GROUP BY p.id";
if (!empty($having_clauses)) {
    $query .= " HAVING " . implode(" AND ", $having_clauses);
}
$query .= " ORDER BY p.stock DESC, p.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($execute_params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($productos);
?>