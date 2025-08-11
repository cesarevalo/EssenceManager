<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/products.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

function handleMultipleImageUploads($files) {
    $uploaded_files = [];
    $upload_dir = '../../public/uploads/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024;

    foreach ($files['name'] as $key => $name) {
        if ($files['error'][$key] === 0) {
            $tmp_name = $files['tmp_name'][$key];
            $file_type = $files['type'][$key];
            $file_size = $files['size'][$key];

            if (!in_array($file_type, $allowed_types) || $file_size > $max_size) continue;

            $file_extension = pathinfo($name, PATHINFO_EXTENSION);
            $new_filename = uniqid('prod_', true) . '.' . $file_extension;
            
            if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                $uploaded_files[] = $new_filename;
            }
        }
    }
    return $uploaded_files;
}

try {
    $db->beginTransaction();

    switch ($action) {
        case 'create':
            $params = [
                'nombre' => FILTER_SANITIZE_STRING, 'id_marca' => FILTER_SANITIZE_NUMBER_INT,
                'genero' => FILTER_SANITIZE_STRING, 'concentracion' => FILTER_SANITIZE_STRING,
                'tamano_ml' => FILTER_SANITIZE_NUMBER_INT, 'descripcion' => FILTER_SANITIZE_STRING,
                'notas_salida' => FILTER_SANITIZE_STRING, 'notas_corazon' => FILTER_SANITIZE_STRING,
                'notas_base' => FILTER_SANITIZE_STRING, 'similitud' => FILTER_SANITIZE_STRING,
                'precio_usdt' => FILTER_VALIDATE_FLOAT, 'stock' => FILTER_SANITIZE_NUMBER_INT,
                'sku' => FILTER_SANITIZE_STRING, 'codigo_barra' => FILTER_SANITIZE_STRING,
                'estado_disponibilidad' => FILTER_SANITIZE_STRING, 'visible' => FILTER_VALIDATE_INT
            ];
            $data = [];
            foreach ($params as $param => $filter) {
                $data[$param] = filter_input(INPUT_POST, $param, $filter, FILTER_NULL_ON_FAILURE);
            }

            $sql = "INSERT INTO productos (nombre, id_marca, genero, concentracion, tamano_ml, descripcion, notas_salida, notas_corazon, notas_base, similitud, precio_usdt, stock, sku, codigo_barra, estado_disponibilidad, visible) VALUES (:nombre, :id_marca, :genero, :concentracion, :tamano_ml, :descripcion, :notas_salida, :notas_corazon, :notas_base, :similitud, :precio_usdt, :stock, :sku, :codigo_barra, :estado_disponibilidad, :visible)";
            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            $id_producto = $db->lastInsertId();

            if (!empty($_POST['categorias'])) {
                $stmt_cat = $db->prepare("INSERT INTO producto_categorias (id_producto, id_categoria) VALUES (?, ?)");
                foreach ($_POST['categorias'] as $id_categoria) {
                    $stmt_cat->execute([$id_producto, $id_categoria]);
                }
            }
            
            $nuevas_imagenes = [];
            if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
                $nuevas_imagenes = handleMultipleImageUploads($_FILES['imagenes']);
            }
            if (!empty($nuevas_imagenes)) {
                $stmt_img = $db->prepare("INSERT INTO producto_imagenes (id_producto, imagen_url) VALUES (?, ?)");
                foreach ($nuevas_imagenes as $img_url) {
                    $stmt_img->execute([$id_producto, $img_url]);
                }
                $imagen_principal = $nuevas_imagenes[0];
                $stmt_update_main = $db->prepare("UPDATE productos SET imagen_url = ? WHERE id = ?");
                $stmt_update_main->execute([$imagen_principal, $id_producto]);
            }

            $_SESSION['message'] = 'Producto creado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'update':
            $params = [
                'nombre' => FILTER_SANITIZE_STRING, 'id_marca' => FILTER_SANITIZE_NUMBER_INT,
                'genero' => FILTER_SANITIZE_STRING, 'concentracion' => FILTER_SANITIZE_STRING,
                'tamano_ml' => FILTER_SANITIZE_NUMBER_INT, 'descripcion' => FILTER_SANITIZE_STRING,
                'notas_salida' => FILTER_SANITIZE_STRING, 'notas_corazon' => FILTER_SANITIZE_STRING,
                'notas_base' => FILTER_SANITIZE_STRING, 'similitud' => FILTER_SANITIZE_STRING,
                'precio_usdt' => FILTER_VALIDATE_FLOAT, 'stock' => FILTER_SANITIZE_NUMBER_INT,
                'sku' => FILTER_SANITIZE_STRING, 'codigo_barra' => FILTER_SANITIZE_STRING,
                'estado_disponibilidad' => FILTER_SANITIZE_STRING, 'visible' => FILTER_VALIDATE_INT
            ];
            $data = [];
            foreach ($params as $param => $filter) {
                 $data[$param] = filter_input(INPUT_POST, $param, $filter, FILTER_NULL_ON_FAILURE);
            }
            $id_producto = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
            $data['id'] = $id_producto;

            $set_parts = [];
            foreach ($data as $key => $value) {
                if ($key != 'id') $set_parts[] = "$key = :$key";
            }
            $sql = "UPDATE productos SET " . implode(', ', $set_parts) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($data);

            $stmt_del_cats = $db->prepare("DELETE FROM producto_categorias WHERE id_producto = ?");
            $stmt_del_cats->execute([$id_producto]);
            if (!empty($_POST['categorias'])) {
                $stmt_cat = $db->prepare("INSERT INTO producto_categorias (id_producto, id_categoria) VALUES (?, ?)");
                foreach ($_POST['categorias'] as $id_categoria) {
                    $stmt_cat->execute([$id_producto, $id_categoria]);
                }
            }

            if (!empty($_POST['eliminar_imagenes'])) {
                // ... (lógica para eliminar imágenes) ...
            }
            if (isset($_FILES['imagenes']) && !empty($_FILES['imagenes']['name'][0])) {
                // ... (lógica para añadir nuevas imágenes) ...
            }
            if (!empty($_POST['imagen_principal'])) {
                // ... (lógica para actualizar imagen principal) ...
            } else {
                 // ... (lógica para reasignar imagen principal si fue borrada) ...
            }
            
            $_SESSION['message'] = 'Producto actualizado exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'delete':
            // ... (delete logic) ...
            break;
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/products.php');
exit;