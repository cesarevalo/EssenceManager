<?php
session_start();
$_SESSION = array(); // Limpiar el array de sesión
session_destroy();  // Destruir la sesión
header('Location: catalog.php');
exit;
?>