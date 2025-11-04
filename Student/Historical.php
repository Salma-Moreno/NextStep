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
    <title>Historial - Estudiante</title>
    <link rel="stylesheet" href="../assets/becas.css">
    
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">
        <h2>Historial de Solicitudes de Beca</h2>
        <p class="subtitle">Consulta tus solicitudes anteriores y su estatus.</p>

        <table class="table">
            <thead>
                <tr>
                    <th>ID Solicitud</th>
                    <th>Fecha de Solicitud</th>
                    <th>Tipo de Beca</th>
                    <th>Periodo</th>
                    <th>Estatus</th>
                </tr>
            </thead>
            <tbody>
                <!-- Ejemplo de filas estáticas (aquí iría un loop PHP) -->
                <!-- <?php while($row = mysqli_fetch_assoc($result)) { ?> -->
                <tr>
                    <td>001</td>
                    <td>2025-01-10</td>
                    <td>Beca Académica</td>
                    <td>2025-A</td>
                    <td><span class="status approved">Aprobada</span></td>
                </tr>
                <tr>
                    <td>002</td>
                    <td>2025-06-20</td>
                    <td>Beca Deportiva</td>
                    <td>2025-B</td>
                    <td><span class="status pending">En revisión</span></td>
                </tr>
                <tr>
                    <td>003</td>
                    <td>2024-08-15</td>
                    <td>Beca Alimentaria</td>
                    <td>2024-B</td>
                    <td><span class="status rejected">Rechazada</span></td>
                </tr>
                <!-- <?php } ?> -->
            </tbody>
        </table>
    </div>

</body>
</html>
