<?php
session_start();
require_once '../../config/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    header('Location: ../../public/catalog.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$action = $_POST['action'];

try {
    switch ($action) {
        case 'register':
            $nombre_raw = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
            $apellido_raw = filter_input(INPUT_POST, 'apellido', FILTER_SANITIZE_STRING);
            $nombre = mb_strtoupper($nombre_raw, 'UTF-8');
            $apellido = mb_strtoupper($apellido_raw, 'UTF-8');
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'];
            $cedula = filter_input(INPUT_POST, 'cedula', FILTER_SANITIZE_NUMBER_INT) ?: null;
            $fecha_nacimiento = filter_input(INPUT_POST, 'fecha_nacimiento', FILTER_SANITIZE_STRING);
            $telefono_raw = filter_input(INPUT_POST, 'telefono', FILTER_SANITIZE_NUMBER_INT);
            $country_code = filter_input(INPUT_POST, 'country_code', FILTER_SANITIZE_STRING);

            if (!$email) throw new Exception("El formato del email no es válido.");
            if (empty($nombre) || empty($apellido)) throw new Exception("El nombre y el apellido son obligatorios.");
            if (strlen($password) < 6) throw new Exception("La contraseña debe tener al menos 6 caracteres.");
            if (empty($telefono_raw)) throw new Exception("El número de teléfono es obligatorio.");
            if (empty($fecha_nacimiento)) { throw new Exception("La fecha de nacimiento es obligatoria para el registro."); }
            $dob = new DateTime($fecha_nacimiento);
            $today = new DateTime('now');
            $age = $today->diff($dob)->y;
            if ($age < 12) { throw new Exception("Debes tener al menos 12 años para registrarte."); }

            $telefono_final = '';
            if ($country_code === 'VE') {
                if (substr($telefono_raw, 0, 1) === '0') { $telefono_limpio = substr($telefono_raw, 1); } else { $telefono_limpio = $telefono_raw; }
                $telefono_final = '58' . $telefono_limpio;
            } else { $telefono_final = $telefono_raw; }

            $stmt_email = $db->prepare("SELECT id FROM clientes WHERE email = ?");
            $stmt_email->execute([$email]);
            if ($stmt_email->fetch()) throw new Exception("Este email ya está registrado.");
            if ($cedula) {
                 $stmt_cedula = $db->prepare("SELECT id FROM clientes WHERE cedula = ?");
                 $stmt_cedula->execute([$cedula]);
                 if ($stmt_cedula->fetch()) throw new Exception("Esta cédula ya está registrada.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // CONSULTA ACTUALIZADA: Añadimos los valores por defecto (1) para las preferencias
            $query = "INSERT INTO clientes (nombre, apellido, email, password, cedula, fecha_nacimiento, telefono, pais, acepta_email_pub, acepta_ws_pub) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)";
            $stmt = $db->prepare($query);
            $stmt->execute([$nombre, $apellido, $email, $hashed_password, $cedula, $fecha_nacimiento, $telefono_final, $country_code]);
            
            $_SESSION['success_message'] = "¡Registro exitoso! Por favor, inicia sesión.";
            header('Location: ../../public/login.php');
            exit;

        case 'login':
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'];
            $stmt = $db->prepare("SELECT * FROM clientes WHERE email = ?");
            $stmt->execute([$email]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && password_verify($password, $client['password'])) {
                if ($client['activo'] == 0) {
                    throw new Exception("Esta cuenta ha sido bloqueada. Por favor, contacta a soporte.");
                }
                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $client['id'];
                $_SESSION['client_name'] = $client['nombre'];
                if (isset($_SESSION['redirect_to_checkout'])) {
                    unset($_SESSION['redirect_to_checkout']);
                    header('Location: ../../public/checkout.php');
                } else {
                    header('Location: ../../public/catalog.php');
                }
                exit;
            } else {
                throw new Exception("Email o contraseña incorrectos.");
            }
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    $redirect_url = ($action == 'login') ? '../../public/login.php' : '../../public/register.php';
    header("Location: $redirect_url");
    exit;
}