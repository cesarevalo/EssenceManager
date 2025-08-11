<?php
// Archivo: config/Database.php

class Database {
    // Parámetros de la base de datos para cPanel
    private $host = 'localhost'; // Generalmente es 'localhost', tu proveedor de hosting lo confirma.
    
    // 👇 USA LOS DATOS QUE CREASTE EN CPANEL 👇
    private $db_name = 'toppvsqo_essencemanager'; // El nombre completo de la BD de cPanel
    private $username = 'toppvsqo_admintpccs';       // El nombre completo del usuario de la BD de cPanel
    private $password = 'g&cY?Q7-6pKU';     // La contraseña que asignaste a ese usuario
    
    private $conn;

    // El resto del código es exactamente el mismo...
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4',
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo 'Error de Conexión: ' . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>