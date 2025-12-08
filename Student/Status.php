<?php
session_start();

// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];

// ============================
// 1. Obtener ID del estudiante
// ============================
$sql_student_id  = "SELECT ID_Student FROM student WHERE FK_ID_User = ? LIMIT 1";
$stmt_student    = $conn->prepare($sql_student_id);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student  = $stmt_student->get_result();

$student_id = null;
if ($row = $result_student->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];
}
$stmt_student->close();

// Variables para solicitudes y punto de entrega por defecto
$solicitudes = [];
$defaultPickupPointName = null;
$defaultPickupDateForUser = null;

// Solo seguimos si tenemos estudiante
if ($student_id) {

    // ==============================
    // 2. Calcular PERIODO ACTUAL
    // ==============================
    $month = (int)date('n');  // 1-12
    $year  = (int)date('Y');

    if ($month >= 1 && $month <= 7) {
        $currentPeriod = 'Enero - Julio';
    } else {
        $currentPeriod = 'Agosto - Diciembre';
    }

    // =======================================================
    // 2.5. Obtener EL ÚLTIMO delivery "vigente" del estudiante
    //      (se usará como punto/fecha por defecto)
    //      - No Rechazada/Cancelada
    //      - Kit no expirado (End_date >= HOY)
    // =======================================================
    $sql_default_delivery = "
        SELECT 
            d.FK_ID_Point,
            d.Date,
            DATE_FORMAT(d.Date, '%d de %M de %Y %H:%i') AS Fecha_Entrega_Def,
            p.Name AS Punto_Def_Name
        FROM delivery d
        JOIN aplication a 
          ON a.FK_ID_Student = d.FK_ID_Student 
         AND a.FK_ID_Kit     = d.FK_ID_Kit
        JOIN kit k 
          ON k.ID_Kit = d.FK_ID_Kit
        JOIN collection_point p 
          ON p.ID_Point = d.FK_ID_Point
        WHERE d.FK_ID_Student = ?
          AND a.status NOT IN ('Rechazada', 'Cancelada')
          AND k.End_date >= CURDATE()
        ORDER BY d.Date DESC
        LIMIT 1
    ";
    if ($stmt_def = $conn->prepare($sql_default_delivery)) {
        $stmt_def->bind_param("i", $student_id);
        $stmt_def->execute();
        $res_def = $stmt_def->get_result();
        if ($row_def = $res_def->fetch_assoc()) {
            $defaultPickupPointName  = $row_def['Punto_Def_Name'] ?? null;
            $defaultPickupDateForUser = $row_def['Fecha_Entrega_Def'] ?? null;
        }
        $stmt_def->close();
    }

    // ==============================================
    // 3. Traer TODAS las solicitudes del periodo actual
    // ==============================================
    $sql_applications = "
        SELECT 
            A.status,
            A.ID_status,
            CONCAT('Kit ', K.ID_Kit) AS Beca_Name,
            S.Period,
            S.Year,
            SD.Average,
            P.Name AS Punto_Entrega_Name,
            DATE_FORMAT(A.Application_date, '%d de %M de %Y') AS Fecha_Solicitud,
            A.Application_date,
            D.Date AS Pickup_Date,
            DATE_FORMAT(D.Date, '%d de %M de %Y %H:%i') AS Fecha_Entrega
        FROM aplication A
        JOIN student ST ON A.FK_ID_Student = ST.ID_Student
        JOIN kit K ON A.FK_ID_Kit = K.ID_Kit
        JOIN semester S ON K.FK_ID_Semester = S.ID_Semester
        LEFT JOIN student_details SD 
            ON ST.ID_Student = SD.FK_ID_Student
        LEFT JOIN delivery D 
            ON A.FK_ID_Student = D.FK_ID_Student 
           AND A.FK_ID_Kit     = D.FK_ID_Kit
        LEFT JOIN collection_point P 
            ON D.FK_ID_Point = P.ID_Point
        WHERE A.FK_ID_Student = ?
          AND S.Year = ?
          AND S.Period = ?
        ORDER BY A.Application_date DESC, A.ID_status DESC
    ";

    $stmt_app = $conn->prepare($sql_applications);
    $stmt_app->bind_param("iis", $student_id, $year, $currentPeriod);
    $stmt_app->execute();
    $result_app = $stmt_app->get_result();

    while ($row = $result_app->fetch_assoc()) {

        // ==========================================================
        // Fallback: si esta solicitud NO tiene delivery propio
        // y el estudiante ya tiene un punto de entrega por defecto
        // y la beca está en un estado "vigente",
        // le asignamos ese mismo punto y fecha para mostrar en estatus
        // (NO tocamos BD, solo la vista)
        // ==========================================================
        $status_row = $row['status'] ?? null;
        $esVigente = in_array($status_row, ['Enviada', 'En revisión', 'Aprobada', 'Entrega']);

        if ($esVigente && empty($row['Punto_Entrega_Name']) && $defaultPickupPointName) {
            $row['Punto_Entrega_Name'] = $defaultPickupPointName;
        }
        if ($esVigente && empty($row['Fecha_Entrega']) && $defaultPickupDateForUser) {
            $row['Fecha_Entrega'] = $defaultPickupDateForUser;
        }

        $solicitudes[] = $row;
    }
    $stmt_app->close();
}

// =======================
// 4. Mapeo de estados
// =======================
$status_map = [
    'Enviada'     => 'Enviada',
    'En revisión' => 'En revisión',
    'Aprobada'    => 'Aprobada',
    'Rechazada'   => 'Rechazada',
    'Entrega'     => 'Entrega',
    'Cancelada'   => 'Cancelada'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatus - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/becas.css">
    <style>
        .status-bar {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
            padding-top: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px 10px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            z-index: 2;
            font-weight: bold;
            background-color: transparent;
            color: white !important;
        }
        .step.completed {
            color: white !important;
        }
        .step.active {
            color: white !important;
        }
        .step.rejected {
            color: white !important;
        }
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            margin: 0 auto 5px;
            border-radius: 50%;
            background-color: #ddd;
            color: white !important;
            font-weight: bold;
        }
        .step.completed .step-indicator {
            background-color: #52c41a;
        }
        .step.active .step-indicator {
            background-color: #1890ff;
        }
        .step.rejected .step-indicator {
            background-color: #ff4d4f;
        }
        .status-bar::before {
            content: '';
            position: absolute;
            top: 35px;
            left: 0;
            right: 0;
            height: 3px;
            background: #ddd;
            z-index: 1;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .progress-line {
            position: absolute;
            top: 35px;
            left: 0;
            height: 3px;
            background-color: #1890ff;
            z-index: 1;
            transition: width 0.3s ease;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn a {
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn:hover a {
            color: white;
        }
        .step span,
        .step div {
            color: white !important;
        }
        .info-box {
            margin-top: 10px;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .info-success {
            background-color: #e6ffed;
            color: #1b5e20;
            border: 1px solid #a5d6a7;
        }
        .info-warning {
            background-color: #fff8e1;
            color: #8d6e63;
            border: 1px solid #ffe0b2;
        }
        .subtitle-period {
            color: #555;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>
    <div class="container">
        <h2>Estatus de solicitudes del periodo actual</h2>
        <?php
            // Mostrar el periodo actual que se está usando
            $monthNow = (int)date('n');
            $yearNow  = (int)date('Y');
            $textoPeriodo = ($monthNow >= 1 && $monthNow <= 7) 
                ? 'Enero - Julio ' . $yearNow 
                : 'Agosto - Diciembre ' . $yearNow;
        ?>
        <p class="subtitle">
            Aquí puedes visualizar el progreso de todas tus solicitudes correspondientes al periodo 
            <strong><?php echo htmlspecialchars($textoPeriodo); ?></strong>.
        </p>

        <?php if (empty($solicitudes)): ?>
            <div class="card">
                <p>No tienes solicitudes registradas en el periodo actual.</p>
            </div>
        <?php else: ?>

            <?php foreach ($solicitudes as $solicitud): ?>
                <?php
                    $status_raw = $solicitud['status'] ?? null;
                    $current_status = $status_raw && isset($status_map[$status_raw])
                        ? $status_map[$status_raw]
                        : '';

                    // Definir pasos según el estado de ESTA solicitud
                    $steps = [];
                    switch ($status_raw) {
                        case 'Enviada':
                        case 'En revisión':
                        case 'Aprobada':
                        case 'Entrega':
                            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
                            break;
                        case 'Rechazada':
                        case 'Cancelada':
                            $steps = ['Enviada', $status_map[$status_raw] ?? $status_raw];
                            break;
                        default:
                            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
                    }

                    $fechaEntrega = $solicitud['Fecha_Entrega'] ?? null;
                ?>

                <div class="card">
                    <h3>
                        <?php 
                        echo htmlspecialchars($solicitud['Beca_Name']) 
                           . ' - Periodo ' 
                           . htmlspecialchars(($solicitud['Year'] ?? '') . ' - ' . ($solicitud['Period'] ?? '')); 
                        ?>
                    </h3>

                    <p><strong>ID Solicitud:</strong> #<?php echo htmlspecialchars($solicitud['ID_status']); ?></p>
                    <p><strong>Fecha de solicitud:</strong> <?php echo htmlspecialchars($solicitud['Fecha_Solicitud']); ?></p>
                    <p><strong>Promedio registrado:</strong> <?php echo htmlspecialchars($solicitud['Average'] ?? 'N/A'); ?></p>
                    <p>
                        <strong>Punto de entrega:</strong> 
                        <?php echo htmlspecialchars($solicitud['Punto_Entrega_Name'] ?? 'Pendiente de asignar'); ?>
                    </p>

                    <?php if (in_array($current_status, ['Aprobada', 'Entrega'])): ?>
                        <?php if ($fechaEntrega): ?>
                            <div class="info-box info-success">
                                <strong>📦 Tu beca está lista para entrega.</strong><br>
                                Podrás recoger tu beca en el punto de entrega seleccionado 
                                <strong>a partir del <?php echo htmlspecialchars($fechaEntrega); ?></strong>.
                            </div>
                        <?php else: ?>
                            <div class="info-box info-warning">
                                <strong>⚠️ Tu beca está aprobada, pero aún no has seleccionado un punto de entrega.</strong><br>
                                Ve a la sección <em>"Puntos de entrega"</em> para elegir una sucursal donde recoger tu beca.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="status-bar">
                        <?php 
                        if (!empty($steps)) {
                            $total_steps = count($steps);
                            $current_step_index = array_search($current_status, $steps);
                            if ($current_step_index === false) {
                                $current_step_index = 0;
                            }

                            // Calcular el ancho de la línea de progreso
                            $progress_width = ($total_steps > 1) 
                                ? ($current_step_index / ($total_steps - 1)) * 100 
                                : 0;

                            echo '<div class="progress-line" style="width: ' . $progress_width . '%;"></div>';

                            foreach ($steps as $index => $step) {
                                $class = '';

                                if ($step == $current_status) {
                                    $class = 'active';
                                } elseif (in_array($step, ['Rechazada', 'Cancelada'])) {
                                    $class = 'rejected';
                                } else {
                                    if ($index < $current_step_index) {
                                        $class = 'completed';
                                    }
                                }

                                echo '<div class="step ' . $class . '">';
                                echo '<div class="step-indicator">' . ($index + 1) . '</div>';
                                echo htmlspecialchars($step);
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>

                    <?php if (in_array($current_status, ['Rechazada', 'Cancelada'])): ?>
                        <div style="background-color: #fff2f0; padding: 15px; border-radius: 5px; margin-top: 15px;">
                            <strong>⚠️ Nota:</strong> Esta solicitud ha sido <strong><?php echo $current_status; ?></strong>.
                            <?php if ($current_status == 'Rechazada'): ?>
                                Contacta al departamento correspondiente para más información.
                            <?php else: ?>
                                Esta solicitud ha sido cancelada por el personal administrativo.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <button class="btn">
            <a href="../Student/Historical.php">Ver historial completo</a>
        </button>
    </div>
</body>
</html>
