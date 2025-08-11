<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/categorias.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'create':
            $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
            if (empty($nombre)) {
                throw new Exception('El nombre de la categoría no puede estar vacío.');
            }
            $query = "INSERT INTO categorias (nombre) VALUES (:nombre)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->execute();
            $_SESSION['message'] = 'Categoría creada exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
            $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
            if (empty($id) || empty($nombre)) {
                throw new Exception('Datos inválidos para actualizar.');
            }
            $query = "UPDATE categorias SET nombre = :nombre WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $_SESSION['message'] = 'Categoría actualizada exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
            if (empty($id)) {
                throw new Exception('ID inválido para eliminar.');
            }
            $query = "DELETE FROM categorias WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $_SESSION['message'] = 'Categoría eliminada exitosamente.';
            $_SESSION['message_type'] = 'success';
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (PDOException $e) {
    // CAPTURA INTELIGENTE DEL ERROR DE CLAVE FORÁNEA
    if ($e->getCode() == '23000') {
        $_SESSION['message'] = 'Error: No se puede eliminar esta categoría porque está siendo utilizada por uno o más productos.';
        $_SESSION['message_type'] = 'danger';
    } else {
        $_SESSION['message'] = 'Error de base de datos: ' . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: ../../public/admin/categorias.php');
exit;