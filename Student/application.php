<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}
//Conexion a la base de datos
include '../Conexiones/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Student</title>
    <link rel="stylesheet" href="../assets/Student/application.css">
</head>
<body>
<?php include '../Includes/HeaderMenuE.php'; ?>
     <div class="container">
        <h1>Solicitud de Beca</h1>

        <!-- Simulación: si el estudiante tiene datos personales -->
        <!-- <?php if ($tieneDatosPersonales) { ?> -->
        <div class="scholarship-section">
            <h2>Becas Disponibles</h2>
            <p>Selecciona una de las becas disponibles para ti:</p>

            <div class="scholarship-list">
                <div class="scholarship-card">
                    <h3>Beca Académica</h3>
                    <p>Otorgada a estudiantes con promedio mayor a 90.</p>
                    <button class="apply-btn">Aplicar</button>
                </div>

                <div class="scholarship-card">
                    <h3>Beca de Transporte</h3>
                    <p>Apoyo económico para cubrir gastos de traslado.</p>
                    <button class="apply-btn">Aplicar</button>
                </div>

                <div class="scholarship-card">
                    <h3>Beca de Excelencia Deportiva</h3>
                    <p>Dirigida a estudiantes destacados en actividades deportivas.</p>
                    <button class="apply-btn">Aplicar</button>
                </div>
            </div>
        </div>

        <!-- <?php } else { ?> -->
        <div class="no-data-section">
            <h2>Información del Estudiante</h2>
            <p class="warning">
                Por el momento no se encuentra información para solicitud disponible.<br>
                Completa tu perfil para poder aplicar a una beca.
            </p>

            <a href="perfil.php" class="btn">Ir a mi perfil</a>
        </div>
        <!-- <?php } ?> -->
    </div>
</body>
</html>