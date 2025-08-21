<?php
// app/auth/login_process.php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../public/admin/login.php');
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['error_message'] = 'Por favor, introduce el email y la contraseña.';
    header('Location: ../../public/admin/login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "
        SELECT u.id, u.nombre, u.email, u.password, u.id_rol, r.permisos 
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id
        WHERE u.email = :email AND u.activo = 1
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (password_verify($password, $user['password'])) {
            // Contraseña correcta: Iniciar sesión y guardar datos
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nombre'];
            $_SESSION['user_role_id'] = $user['id_rol'];
            $_SESSION['logged_in'] = true;
            
            // ¡NUEVO! Decodificar y guardar los permisos JSON en la sesión
            $_SESSION['user_permissions'] = json_decode($user['permisos'], true);

            header('Location: ../../public/admin/dashboard.php');
            exit;
        }
    }

    $_SESSION['error_message'] = 'Email o contraseña incorrectos.';
    header('Location: ../../public/admin/login.php');
    exit;

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error en la base de datos.';
    error_log($e->getMessage()); // Guardar error real en logs del servidor
    header('Location: ../../public/admin/login.php');
    exit;
}