<?php
session_start();

// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];
$solicitud = null;

// Obtener ID del estudiante
$sql_student_id = "SELECT ID_Student FROM student WHERE FK_ID_User = ?";
$stmt_student = $conn->prepare($sql_student_id);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows > 0) {
    $student_row = $result_student->fetch_assoc();
    $student_id = $student_row['ID_Student'];

    // Consulta para la solicitud más reciente
    $sql_aplication = "
        SELECT 
            A.status,
            A.ID_status,
            K.Name AS Beca_Name,
            S.Period,
            S.Year,
            SD.Average,
            P.Name AS Punto_Entrega_Name,
            DATE_FORMAT(A.Application_date, '%d de %M de %Y') AS Fecha_Solicitud,
            A.Application_date
        FROM aplication A
        JOIN student ST ON A.FK_ID_Student = ST.ID_Student
        JOIN kit K ON A.FK_ID_Kit = K.ID_Kit
        JOIN semester S ON K.FK_ID_Semester = S.ID_Semester
        LEFT JOIN student_details SD ON ST.ID_Student = SD.FK_ID_Student
        LEFT JOIN delivery D ON A.FK_ID_Student = D.FK_ID_Student AND A.FK_ID_Kit = D.FK_ID_Kit
        LEFT JOIN collection_point P ON D.FK_ID_Point = P.ID_Point
        WHERE A.FK_ID_Student = ?
        ORDER BY A.Application_date DESC, A.ID_status DESC
        LIMIT 1
    ";

    $stmt_aplication = $conn->prepare($sql_aplication);
    $stmt_aplication->bind_param("i", $student_id);
    $stmt_aplication->execute();
    $result_aplication = $stmt_aplication->get_result();

    if ($result_aplication->num_rows > 0) {
        $solicitud = $result_aplication->fetch_assoc();
    }
}

// Mapeo de estados
$status_map = [
    'Enviada' => 'Enviada',
    'En revisión' => 'En revisión',
    'Aprobada' => 'Aprobada',
    'Rechazada' => 'Rechazada',
    'Entrega' => 'Entrega',
    'Cancelada' => 'Cancelada'
];

// Definir pasos según el estado actual
$steps = [];
if ($solicitud) {
    $status = $solicitud['status'];
    
    switch($status) {
        case 'Enviada':
            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
            break;
        case 'En revisión':
            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
            break;
        case 'Aprobada':
            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
            break;
        case 'Entrega':
            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
            break;
        case 'Rechazada':
        case 'Cancelada':
            $steps = ['Enviada', $status_map[$status]];
            break;
        default:
            $steps = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];
    }
}

$current_status = $solicitud ? $status_map[$solicitud['status']] : '';
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
            background-color: #f8f9fa; /* Fondo claro para la barra */
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
            color: white !important; /* TODOS los textos en blanco */
        }
        .step.completed {
            color: white !important; /* Blanco */
            font-weight: bold;
        }
        .step.active {
            color: white !important; /* Blanco */
            font-weight: bold;
        }
        .step.rejected {
            color: white !important; /* Blanco */
            font-weight: bold;
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
            color: white !important; /* Texto blanco dentro del círculo */
            font-weight: bold;
        }
        .step.completed .step-indicator {
            background-color: #52c41a;
            color: white !important;
        }
        .step.active .step-indicator {
            background-color: #1890ff;
            color: white !important;
        }
        .step.rejected .step-indicator {
            background-color: #ff4d4f;
            color: white !important;
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
        
        /* Estilos para la línea de progreso */
        .progress-line {
            position: absolute;
            top: 35px;
            left: 0;
            height: 3px;
            background-color: #1890ff; /* Color azul para la parte completada */
            z-index: 1;
            transition: width 0.3s ease;
        }
        
        /* Estilos específicos para el botón de "Ver historial completo" */
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
        
        /* Para asegurar que el texto sea blanco en todas partes */
        .step span,
        .step div {
            color: white !important;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>
    <div class="container">
        <h2>Estatus de Solicitud Actual</h2>
        <p class="subtitle">Aquí puedes visualizar el progreso de tu solicitud más reciente.</p>

        <?php if ($solicitud): ?>
        <div class="card">
            <h3><?php echo htmlspecialchars($solicitud['Beca_Name']) . ' - Periodo ' . htmlspecialchars($solicitud['Year'] . '-' . $solicitud['Period']); ?></h3>
            <p><strong>ID Solicitud:</strong> #<?php echo htmlspecialchars($solicitud['ID_status']); ?></p>
            <p><strong>Fecha de solicitud:</strong> <?php echo htmlspecialchars($solicitud['Fecha_Solicitud']); ?></p>
            <p><strong>Promedio registrado:</strong> <?php echo htmlspecialchars($solicitud['Average'] ?? 'N/A'); ?></p>
            <p><strong>Punto de entrega:</strong> <?php echo htmlspecialchars($solicitud['Punto_Entrega_Name'] ?? 'Pendiente de Asignar'); ?></p>
            
            <div class="status-bar">
                <?php 
                if (!empty($steps)) {
                    $total_steps = count($steps);
                    $current_step_index = array_search($current_status, $steps);
                    if ($current_step_index === false) {
                        $current_step_index = 0;
                    }
                    
                    // Calcular el ancho de la línea de progreso
                    $progress_width = ($total_steps > 1) ? ($current_step_index / ($total_steps - 1)) * 100 : 0;
                    
                    echo '<div class="progress-line" style="width: ' . $progress_width . '%;"></div>';
                    
                    foreach ($steps as $index => $step) {
                        $class = '';
                        
                        if ($step == $current_status) {
                            $class = 'active';
                        } elseif (in_array($step, ['Rechazada', 'Cancelada'])) {
                            $class = 'rejected';
                        } else {
                            // Determinar si es completado (estados anteriores al actual)
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
                    <strong>⚠️ Nota:</strong> Tu solicitud ha sido <strong><?php echo $current_status; ?></strong>.
                    <?php if ($current_status == 'Rechazada'): ?>
                        Contacta al departamento correspondiente para más información.
                    <?php else: ?>
                        Esta solicitud ha sido cancelada por el personal administrativo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <div class="card">
                <p>No has realizado ninguna solicitud de beca.</p>
            </div>
        <?php endif; ?>

        <button class="btn"><a href="../Student/Historical.php">Ver historial completo</a></button>
    </div>
</body>
</html>