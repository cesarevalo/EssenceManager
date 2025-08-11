<?php
header('Content-Type: application/json');
require_once '../../config/Database.php';

$database = new Database();
$db = $database->getConnection();

// --- Consulta Principal ---
$query = "
    SELECT 
        p.id, p.nombre, p.precio_usdt, p.imagen_url, p.tamano_ml, p.stock, m.nombre AS marca_nombre
    FROM 
        productos p
    LEFT JOIN 
        marcas m ON p.id_marca = m.id
";

$params = [];
$where_clauses = ["p.visible = 1"];

// --- Lógica de Filtros ---
if (!empty($_GET['search'])) {
    $search_term = '%' . filter_var($_GET['search'], FILTER_SANITIZE_STRING) . '%';
    $where_clauses[] = "(p.nombre LIKE ? OR m.nombre LIKE ? OR p.similitud LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($_GET['marcas']) && is_array($_GET['marcas'])) {
    $marcas_sanitized = array_map('intval', $_GET['marcas']);
    $in_marcas = implode(',', array_fill(0, count($marcas_sanitized), '?'));
    $where_clauses[] = "p.id_marca IN ($in_marcas)";
    $params = array_merge($params, $marcas_sanitized);
}

// =====================================================================
// LÓGICA CORREGIDA PARA FILTRO POR CATEGORÍAS (USA SUBQUERY)
// =====================================================================
if (!empty($_GET['categorias']) && is_array($_GET['categorias'])) {
    $categorias_sanitized = array_map('intval', $_GET['categorias']);
    $in_categorias = implode(',', array_fill(0, count($categorias_sanitized), '?'));
    // Buscamos productos cuyo ID exista en la tabla pivote con alguna de las categorías seleccionadas
    $where_clauses[] = "p.id IN (SELECT id_producto FROM producto_categorias WHERE id_categoria IN ($in_categorias))";
    $params = array_merge($params, $categorias_sanitized);
}

if (!empty($_GET['generos']) && is_array($_GET['generos'])) {
    $generos_sanitized = array_map('htmlspecialchars', $_GET['generos']);
    $in_generos = implode(',', array_fill(0, count($generos_sanitized), '?'));
    $where_clauses[] = "p.genero IN ($in_generos)";
    $params = array_merge($params, $generos_sanitized);
}

if (!empty($_GET['precio_max'])) {
    $precio_max = filter_var($_GET['precio_max'], FILTER_VALIDATE_FLOAT);
    if ($precio_max) {
        $where_clauses[] = "p.precio_usdt <= ?";
        $params[] = $precio_max;
    }
}

// --- LÓGICA DE VISIBILIDAD DE STOCK (AUTOMÁTICA) ---
$where_clauses[] = "(p.stock > 0 OR p.id IN (
    SELECT cd.id_producto 
    FROM compras_detalle cd
    JOIN compras c ON cd.id_compra = c.id
    WHERE c.estado = 'En tránsito'
))";


// --- Finalización de la consulta ---
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY p.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($productos);
?>