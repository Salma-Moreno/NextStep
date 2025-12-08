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

// 2. Si encontramos student_id, traemos TODAS sus solicitudes
$historial = [];
if ($student_id) {
    // Traer TODAS las solicitudes + punto de entrega + fecha de entrega
    $sqlHistorial = "
    SELECT 
        a.ID_status AS ID_Solicitud,
        DATE_FORMAT(a.Application_date, '%Y-%m-%d %H:%i') AS FechaSolicitud,
        CONCAT('Kit ', s.Period, ' ', s.Year) AS TipoBeca,
        CONCAT(s.Year, '-', s.Period) AS Periodo,
        a.status AS Estatus,
        k.Start_date,
        k.End_date,
        CASE 
            WHEN CURDATE() < k.Start_date THEN 'Pendiente'
            WHEN CURDATE() > k.End_date   THEN 'Finalizado'
            ELSE 'Vigente'
        END AS EstadoPeriodo,
        
        -- Punto de entrega y fecha de entrega (pueden ser NULL)
        p.Name    AS PuntoEntrega,
        p.address AS DireccionEntrega,
        DATE_FORMAT(d.Date, '%Y-%m-%d %H:%i') AS FechaEntrega
    FROM aplication a
    INNER JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
    INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
    LEFT JOIN delivery d 
        ON d.FK_ID_Student = a.FK_ID_Student 
       AND d.FK_ID_Kit     = a.FK_ID_Kit
    LEFT JOIN collection_point p 
        ON p.ID_Point = d.FK_ID_Point
    WHERE a.FK_ID_Student = ?
    ORDER BY a.Application_date DESC, a.ID_status DESC
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .table thead {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #edf2f7;
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .table td {
            padding: 16px 15px;
            color: #4a5568;
            vertical-align: top;
            font-size: 0.9rem;
        }
        
        /* ESTILOS PARA LOS STATUS */
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            min-width: 110px;
            text-align: center;
            text-transform: capitalize;
        }
        
        .status-enviada {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-en-revisión {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-aprobada {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-rechazada {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-entrega {
            background-color: #d1c4e9;
            color: #311b92;
            border: 1px solid #b39ddb;
        }
        
        .status-cancelada {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .period-status {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .period-status-finalizado {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .period-status-vigente {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .period-status-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .id-badge {
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 5px;
            font-family: monospace;
            font-weight: 600;
            color: #2c3e50;
            border: 1px solid #eaeaea;
        }

        .entrega-text {
            font-size: 0.9rem;
            color: #4a5568;
        }

        .entrega-pendiente {
            color: #a0aec0;
            font-style: italic;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>

    <div class="container">
        <h2> Historial de Solicitudes de Beca</h2>
        <p class="subtitle">
            Consulta todas tus solicitudes, su estatus, vigencia y datos de entrega.
        </p>

        <table class="table">
            <thead>
                <tr>
                    <th>ID Solicitud</th>
                    <th>Fecha de Solicitud</th>
                    <th>Tipo de Beca</th>
                    <th>Periodo</th>
                    <th>Inicio Beca</th>
                    <th>Fin Beca</th>
                    <th>Vigencia</th>
                    <th>Estatus</th>
                    <th>Punto de entrega</th>
                    <th>Fecha de entrega</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($historial)): ?>
                <tr>
                    <td colspan="10" class="empty-state">
                        📭 No tienes solicitudes registradas todavía.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($historial as $fila): ?>
                    <?php
                        // Texto de estado
                        $statusText = $fila['Estatus'] ?? 'Sin estado';

                        // Clase de estado
                        $statusClass = 'status-' . strtolower(str_replace(' ', '-', $statusText));
                        $statusClass = in_array($statusText, ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada']) 
                            ? $statusClass 
                            : 'status-enviada'; // fallback
                        
                        // Vigencia: Pendiente / Vigente / Finalizado
                        $estadoPeriodo = $fila['EstadoPeriodo'] ?? 'Vigente';
                        $periodoClass  = 'period-status-' . strtolower($estadoPeriodo);

                        // Formatear fecha de solicitud
                        $fechaFormateada = !empty($fila['FechaSolicitud']) 
                            ? date('d/m/Y H:i', strtotime($fila['FechaSolicitud'])) 
                            : 'Sin fecha';

                        // Fechas de inicio y fin de beca
                        $fechaInicioBeca = !empty($fila['Start_date'])
                            ? date('d/m/Y', strtotime($fila['Start_date']))
                            : 'N/A';

                        $fechaFinBeca = !empty($fila['End_date'])
                            ? date('d/m/Y', strtotime($fila['End_date']))
                            : 'N/A';

                        // Entrega (punto y fecha)
                        $puntoEntrega     = $fila['PuntoEntrega'] ?? null;
                        $direccionEntrega = $fila['DireccionEntrega'] ?? null;
                        $fechaEntregaRaw  = $fila['FechaEntrega'] ?? null;
                        $fechaEntrega     = $fechaEntregaRaw 
                            ? date('d/m/Y H:i', strtotime($fechaEntregaRaw)) 
                            : null;
                    ?>
                    <tr>
                        <td>
                            <span class="id-badge">#<?= htmlspecialchars($fila['ID_Solicitud'] ?? 'N/A') ?></span>
                        </td>
                        <td><?= htmlspecialchars($fechaFormateada) ?></td>
                        <td><?= htmlspecialchars($fila['TipoBeca'] ?? 'Kit') ?></td>
                        <td><?= htmlspecialchars($fila['Periodo'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($fechaInicioBeca) ?></td>
                        <td><?= htmlspecialchars($fechaFinBeca) ?></td>
                        <td>
                            <span class="period-status <?= htmlspecialchars($periodoClass) ?>">
                                <?= htmlspecialchars($estadoPeriodo) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status <?= htmlspecialchars($statusClass) ?>">
                                <?= htmlspecialchars($statusText) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($puntoEntrega && $direccionEntrega): ?>
                                <div class="entrega-text">
                                    <strong><?= htmlspecialchars($puntoEntrega) ?></strong><br>
                                    <span><?= htmlspecialchars($direccionEntrega) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="entrega-text entrega-pendiente">
                                    Pendiente de asignar
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($fechaEntrega): ?>
                                <span class="entrega-text">
                                    <?= htmlspecialchars($fechaEntrega) ?>
                                </span>
                            <?php else: ?>
                                <span class="entrega-text entrega-pendiente">
                                    Pendiente
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    console.log('Total de solicitudes: <?= count($historial) ?>');
    <?php if (!empty($historial)): ?>
        console.log('Primera solicitud:', <?= json_encode($historial[0]) ?>);
    <?php endif; ?>
    </script>
</body>
</html>
