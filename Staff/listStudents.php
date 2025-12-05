<?php
session_start();
require '../Conexiones/db.php'; // Asegúrate que este archivo define $conn (mysqli)

// Guardián: Si no hay sesión o el rol no es 'Staff', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Consulta que obtiene TODOS los estudiantes aunque no tengan detalles ni solicitud de beca
$query = "
SELECT 
    s.ID_Student,
    s.Name,
    s.Last_Name,
    sd.License,
    sd.Average,
    a.status
FROM student s
LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
LEFT JOIN aplication a ON s.ID_Student = a.FK_ID_Student
ORDER BY s.Last_Name, s.Name
";

// Ejecutar la consulta y comprobar errores
$result = mysqli_query($conn, $query);
if ($result === false) {
    die("Error en la consulta SQL: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/Staff/list.css">
    <title>Lista de Estudiantes</title>

    <script>
        function filterTable() {
            const input = document.getElementById("search");
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll("#studentTable tbody tr");

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? "" : "none";
            });
        }
    </script>

</head>

<body>
<?php include '../includes/HeaderMenuStaff.php'; ?> 
<div class="container">
    <h2>Estudiantes Inscritos</h2>

    <div class="search-box">
        <input 
            type="text" 
            id="search" 
            placeholder="Buscar por nombre, apellido, matrícula…"
            onkeyup="filterTable()"
        >
    </div>

    <table id="studentTable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Matrícula</th>
                <th>Promedio</th>
                <th>Estatus de Beca</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $statusRaw = $row['status'] ?? null;

                    // Mapeo exhaustivo para cubrir Inglés y Variantes de la BD
                    $statusMap = [
                        'Pending'   => 'Pendiente', 
                        'En revisión' => 'Pendiente', 
                        'Revisión'  => 'Pendiente',
                        
                        'Approved'  => 'Aprobado',
                        
                        'Rejected'  => 'Rechazado',
                        
                        'Delivered' => 'Entregado', 
                        'Entrega'   => 'Entregado', 
                        'Entregado' => 'Entregado',
                        
                        'Canceled'  => 'Cancelado'
                    ];

                    // Determinar el texto a mostrar
                    if (!$statusRaw) {
                        $statusText = "Sin Solicitud";
                        $cssClass = "status-null";
                    } else {
                        // Si existe en el mapa, úsalo. Si no, usa el original
                        $statusText = $statusMap[$statusRaw] ?? $statusRaw;
                        
                        // Determinar la clase CSS basada en el TEXTO FINAL ESTANDARIZADO
                        // Esto asegura que "En revisión" (ahora "Pendiente") tenga color amarillo
                        switch ($statusText) {
                            case 'Pendiente': $cssClass = 'status-Pending'; break;
                            case 'Aprobado':  $cssClass = 'status-Approved'; break;
                            case 'Rechazado': $cssClass = 'status-Rejected'; break;
                            case 'Entregado': $cssClass = 'status-Delivered'; break;
                            case 'Cancelado': $cssClass = 'status-Canceled'; break;
                            default:          $cssClass = 'status-Check';
                        }
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['Last_Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['License'] ?? 'No asignada') ?></td>
                    <td><?= htmlspecialchars($row['Average'] ?? '—') ?></td>
                    
                    <td class="<?= htmlspecialchars($cssClass) ?>"><?= htmlspecialchars($statusText) ?></td>
                    
                    <td>
                        <button class="btn-action btn-perfil"
                            onclick="location.href='view_student.php?id=<?= $row['ID_Student'] ?>'">
                                Perfil
                        </button>

                        <button class="btn-action btn-solicitudes"
                            onclick="location.href='view_applications.php?id=<?= $row['ID_Student'] ?>'">
                                Solicitudes
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>

    </table>
</div>

</body>
</html>