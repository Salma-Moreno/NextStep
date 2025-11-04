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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatus - Estudiante</title>
    <link rel="stylesheet" href="../assets/becas.css">
    
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>
 <div class="container">
        <h2>Estatus de Solicitud Actual</h2>
        <p class="subtitle">Aquí puedes visualizar el progreso de tu solicitud más reciente.</p>

        <!-- Simulación de datos dinámicos -->
        <!-- <?php echo $solicitud['Tipo_Beca']; ?> -->
        <div class="card">
            <h3>Beca Académica - Periodo 2025-A</h3>
            <p><strong>Fecha de solicitud:</strong> 10 de enero de 2025</p>
            <p><strong>Promedio registrado:</strong> 94</p>
            <p><strong>Dependencia:</strong> Secretaría de Educación</p>
            <p><strong>Punto de entrega:</strong> Centro Comunitario Reforma</p>
            
            <div class="status-bar">
                <div class="step completed">Enviada</div>
                <div class="step completed">En revisión</div>
                <div class="step active">Aprobada</div>
                <div class="step">Entrega</div>
            </div>
        </div>

        <button class="btn"><a href="../Student/Historical.php">Ver historial completo</a></button>
    </div>
    </body>
</html>