<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Student</title>
    <link rel="stylesheet" href="../assets/viewStudent.css">
</head>
<body>
<?php include '../Includes/HeaderMenuE.php'; ?>
    <div class="container">
        <h1>Bienvenido</h1>
        <p>Esta es la pantalla para aplicar a las Becas para el <strong>Estudiante</strong>.</p>
        <p>Si tiene datos en la base de datos se muestran las becas disponibles, 
            si no se piden los datos personales y aparece mensaje de "Por el momento no se
            encuentra informacion para solicitud disponible"</p>
            <a href="../Student/perfil.php">
            <button class="btn">Perfil</button></a>
    </div>
</body>
</html>