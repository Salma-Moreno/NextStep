<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Staff', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/Staff/IndexStaff.css">
    <title>Staff - Inicio</title>
</head>
<body>
        <?php include '../Includes/HeaderMenuStaff.php'; ?>
    <div class="container">
        <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>!</h1>
        <p>Has iniciado sesión como <strong>Staff</strong>.</p>
        <p>Este es tu panel de control.</p>
    </div>
</body>
</html>