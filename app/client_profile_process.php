<?php
session_start();
require_once '../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/catalog.php');
    exit;
}

$id_cliente = $_SESSION['client_id'] ?? 0;
if (empty($id_cliente)) {
    header('Location: ../public/login.php'); exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_personal_info': // Renombramos la acción anterior para ser más específicos
            // Recolectar y sanitizar datos personales
            $nombre_raw = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
            $apellido_raw = filter_input(INPUT_POST, 'apellido', FILTER_SANITIZE_STRING);
            $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING);
            $nombre = mb_strtoupper($nombre_raw, 'UTF-8');
            $apellido = mb_strtoupper($apellido_raw, 'UTF-8');
            $set_clauses = ["nombre = :nombre", "apellido = :apellido", "telefono = :telefono"];
            $params = [':id' => $id_cliente, ':nombre' => $nombre, ':apellido' => $apellido, ':telefono' => $telefono];

            // Lógica para el cambio de contraseña
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("Para cambiar la contraseña, debes llenar los tres campos.");
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception("La nueva contraseña y su confirmación no coinciden.");
                }
                if (strlen($new_password) < 6) {
                    throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
                }
                $stmt_pass = $db->prepare("SELECT password FROM clientes WHERE id = ?");
                $stmt_pass->execute([$id_cliente]);
                $current_hash = $stmt_pass->fetchColumn();
                if (!password_verify($current_password, $current_hash)) {
                    throw new Exception("La contraseña actual es incorrecta.");
                }
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $set_clauses[] = "password = :password";
                $params[':password'] = $new_hashed_password;
            }

            $sql = "UPDATE clientes SET " . implode(', ', $set_clauses) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $_SESSION['client_name'] = $nombre;
            $_SESSION['message'] = 'Tu perfil ha sido actualizado exitosamente.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../public/mi_cuenta.php');
            break;

        case 'update_preferences':
            // NUEVA ACCIÓN PARA GUARDAR PREFERENCIAS
            $acepta_email_pub = isset($_POST['acepta_email_pub']) ? 1 : 0;
            $acepta_ws_pub = isset($_POST['acepta_ws_pub']) ? 1 : 0;

            $sql = "UPDATE clientes SET acepta_email_pub = ?, acepta_ws_pub = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$acepta_email_pub, $acepta_ws_pub, $id_cliente]);
            
            $_SESSION['message'] = 'Tus preferencias de comunicación han sido guardadas.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../public/mi_cuenta.php');
            break;
            
        default:
             throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    // Redirigir a la página anterior
    $redirect_url = ($_POST['action'] === 'update_preferences') ? '../public/mi_cuenta.php' : '../public/editar_perfil.php';
    header('Location: ' . $redirect_url);
}
exit;