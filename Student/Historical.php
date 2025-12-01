<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

// Conexión a la base de datos
include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];

// 1. Buscar el ID_Student del usuario logueado
$student_id = null;
$sqlStudent = "SELECT ID_Student 
               FROM student 
               WHERE FK_ID_User = ?
               LIMIT 1";
$stmt = $conn->prepare($sqlStudent);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resStudent = $stmt->get_result();

if ($rowSt = $resStudent->fetch_assoc()) {
    $student_id = (int)$rowSt['ID_Student'];
}
$stmt->close();

// 2. Si encontramos student_id, traemos su historial de solicitudes
$historial = [];
if ($student_id) {

    // OJO: aquí asumo que tienes una columna Application_date en "aplication".
    // Si no la tienes, puedes cambiarla por k.Start_date o lo que tengas.
    $sqlHistorial = "
    SELECT 
        a.ID_status AS ID_Solicitud,
        DATE_FORMAT(a.Application_date, '%Y-%m-%d') AS FechaSolicitud,
        CONCAT('Kit ', s.Period, ' ', s.Year) AS TipoBeca,
        CONCAT(s.Year, '-', s.Period) AS Periodo,
        a.status AS Estatus
    FROM aplication a
    INNER JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
    INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
    WHERE a.FK_ID_Student = ?
      AND (
            a.status = 'Enviada'      -- solicitudes actuales / hechas
            OR k.End_date < CURDATE() -- las que ya tuvo y se le acabaron
          )
    ORDER BY a.Application_date DESC
";
    $stmtHist = $conn->prepare($sqlHistorial);
    $stmtHist->bind_param("i", $student_id);
    $stmtHist->execute();
    $resultHist = $stmtHist->get_result();

    while ($row = $resultHist->fetch_assoc()) {
        $historial[] = $row;
    }
    $stmtHist->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/becas.css">
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
        <?php if (empty($historial)): ?>
            <tr>
                <td colspan="5" style="text-align:center;">
                    No tienes solicitudes registradas todavía.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($historial as $fila): ?>
                <?php
                    // Clase CSS según el estatus
                    $statusText = $fila['Estatus'];
                    $statusClass = '';

                    switch ($statusText) {
                        case 'Aprobada':
                            $statusClass = 'approved';
                            break;
                        case 'Rechazada':
                        case 'Cancelada':
                            $statusClass = 'rejected';
                            break;
                        case 'Enviada':
                        case 'En revisión':
                        default:
                            $statusClass = 'pending';
                            break;
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($fila['ID_Solicitud']) ?></td>
                    <td><?= htmlspecialchars($fila['FechaSolicitud']) ?></td>
                    <td><?= htmlspecialchars($fila['TipoBeca']) ?></td>
                    <td><?= htmlspecialchars($fila['Periodo']) ?></td>
                    <td>
                        <span class="status <?= $statusClass ?>">
                            <?= htmlspecialchars($statusText) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>