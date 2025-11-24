<?php
$host = '127.0.0.1';
$port = 3306; // Puerto determinado de XAMPP, cambienlo si lo necesitan.
//$port = 3307; // Este es el puerto que yo uso, porfa no lo borren 
$dbname = 'nextstepdatabase';
$username = 'root'; // Cambiar si tu servidor tiene otro usuario
$password = ''; // Si tu usuario tiene contraseña, cámbiala

$conn = new mysqli($host, $username, $password, $dbname, $port);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Establecer el charset (buena práctica)
$conn->set_charset("utf8mb4");

// echo "Conexión exitosa"; 
?>
