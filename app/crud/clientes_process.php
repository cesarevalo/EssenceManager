<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/admin/clientes.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'update_client':
            // Recolectar y sanitizar datos
            $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_SANITIZE_NUMBER_INT);
            $nombre_raw = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
            $apellido_raw = filter_input(INPUT_POST, 'apellido', FILTER_SANITIZE_STRING);
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $telefono = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_STRING);
            $cedula = filter_input(INPUT_POST, 'cedula', FILTER_SANITIZE_STRING);
            $fecha_nacimiento = filter_input(INPUT_POST, 'fecha_nacimiento', FILTER_SANITIZE_STRING);
            $password = $_POST['password'];
            
            // Recolectar nuevos campos
            $permite_credito = isset($_POST['permite_credito']) ? 1 : 0;
            $activo = filter_input(INPUT_POST, 'activo', FILTER_VALIDATE_INT);

            if (empty($id_cliente)) {
                throw new Exception("ID de cliente no válido.");
            }

            // Convertir a mayúsculas
            $nombre = mb_strtoupper($nombre_raw, 'UTF-8');
            $apellido = mb_strtoupper($apellido_raw, 'UTF-8');
            
            // Construir la consulta de actualización
            $set_clauses = [
                "nombre = :nombre",
                "apellido = :apellido",
                "email = :email",
                "telefono = :telefono",
                "cedula = :cedula",
                "fecha_nacimiento = :fecha_nacimiento",
                "permite_credito = :permite_credito",
                "activo = :activo"
            ];
            $params = [
                ':id' => $id_cliente,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':email' => $email ?: null, // Guardar NULL si el email está vacío
                ':telefono' => $telefono,
                ':cedula' => $cedula,
                ':fecha_nacimiento' => !empty($fecha_nacimiento) ? $fecha_nacimiento : null,
                ':permite_credito' => $permite_credito,
                ':activo' => $activo
            ];

            // Solo actualizar la contraseña si se proporcionó una nueva
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    throw new Exception("La nueva contraseña debe tener al menos 6 caracteres.");
                }
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $set_clauses[] = "password = :password";
                $params[':password'] = $hashed_password;
            }

            $sql = "UPDATE clientes SET " . implode(', ', $set_clauses) . " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $_SESSION['message'] = 'Cliente actualizado exitosamente.';
            $_SESSION['message_type'] = 'success';
            header('Location: ../../public/admin/clientes.php');
            break;
            
        // Aquí irían otras acciones en el futuro (ej. 'create_client', 'delete_client')
    }
} catch (Exception $e) {
    $_SESSION['message'] = 'Error: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    // Redirigir de vuelta al formulario de edición si es posible
    $redirect_url = isset($_POST['id_cliente']) ? '../../public/admin/cliente_form.php?id=' . $_POST['id_cliente'] : '../../public/admin/clientes.php';
    header('Location: ' . $redirect_url);
}
exit;