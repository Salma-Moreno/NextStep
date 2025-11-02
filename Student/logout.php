<?php
session_start();

// Limpiar todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Redirigir al login del estudiante
header('Location: StudentLogin.php');
exit;
?>
