<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

//Conexion a la base de datos
include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];
$solicitud = null;

// Paso 1: Obtener el ID del estudiante usando el ID de usuario de la sesión
$sql_student_id = "SELECT ID_Student FROM student WHERE FK_ID_User = ?";
$stmt_student = $conn->prepare($sql_student_id);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

if ($result_student->num_rows > 0) {
    $student_row = $result_student->fetch_assoc();
    $student_id = $student_row['ID_Student'];

    // Paso 2: Consulta compleja para obtener la solicitud más reciente y sus detalles
    // Nota: La tabla 'aplication' no tiene columna de fecha, pero la base de datos puede tener un campo de marca de tiempo (timestamp)
    // Usaremos un ORDER BY y LIMIT 1 para obtener la más reciente.
    // Además, 'aplication' no tiene fecha, usaremos la fecha del registro en 'user' temporalmente o asumiremos que existe en 'aplication'.
    
    // Si la tabla 'aplication' NO tiene fecha, asume que la más reciente es la de mayor ID:
    $sql_aplication = "
        SELECT 
            A.status,
            K.Name AS Beca_Name,
            S.Period,
            S.Year,
            SD.Average,
            P.Name AS Punto_Entrega_Name,
            DATE_FORMAT(U.registration_date, '%d de %M de %Y') AS Fecha_Solicitud
        FROM aplication A
        JOIN student ST ON A.FK_ID_Student = ST.ID_Student
        JOIN user U ON ST.FK_ID_User = U.ID_User 
        JOIN kit K ON A.FK_ID_Kit = K.ID_Kit
        JOIN semester S ON K.FK_ID_Semester = S.ID_Semester
        LEFT JOIN student_details SD ON ST.ID_Student = SD.FK_ID_Student
        LEFT JOIN delivery D ON A.FK_ID_Student = D.FK_ID_Student AND A.FK_ID_Kit = D.FK_ID_Kit -- Asumiendo que delivery registra la info
        LEFT JOIN collection_point P ON D.FK_ID_Point = P.ID_Point
        WHERE A.FK_ID_Student = ?
        ORDER BY A.ID_status DESC -- Asume que el mayor ID es la más reciente.
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

// Mapeo del estado de la DB a los nombres de la barra de progreso
$status_map = [
    'Enviada' => 'Enviada',
    'Revision' => 'En revisión', // Asume que el estado en la DB es 'Revision' o similar
    'Aprobada' => 'Aprobada',
    'Rechazada' => 'Rechazada',
    'Entregada' => 'Entrega' // Asume que el estado en la DB es 'Entregada'
];

// Definición de los pasos y sus posibles estados
$steps = [
    'Enviada', 
    'En revisión', 
    'Aprobada', 
    'Entrega'
];

$current_status = $solicitud ? $status_map[$solicitud['status']] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatus - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/becas.css">
    
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>
 <div class="container">
        <h2>Estatus de Solicitud Actual</h2>
        <p class="subtitle">Aquí puedes visualizar el progreso de tu solicitud más reciente.</p>

        <?php if ($solicitud): ?>
        <div class="card">
                        <h3><?php echo htmlspecialchars($solicitud['Beca_Name']) . ' - Periodo ' . htmlspecialchars($solicitud['Year'] . '-' . $solicitud['Period']); ?></h3>
            <p><strong>Fecha de solicitud:</strong> <?php echo htmlspecialchars($solicitud['Fecha_Solicitud']); ?></p>
            <p><strong>Promedio registrado:</strong> <?php echo htmlspecialchars($solicitud['Average'] ?? 'N/A'); ?></p>
            <p><strong>Dependencia:</strong> Secretaría de Educación</p>             
            <p><strong>Punto de entrega:</strong> <?php echo htmlspecialchars($solicitud['Punto_Entrega_Name'] ?? 'Pendiente de Asignar'); ?></p>
            
            <div class="status-bar">
                <?php 
                $completed = true; 
                $found_current = false;

                foreach ($steps as $step) {
                    $class = '';

                    if ($step == $current_status) {
                        $class = 'active'; // Estado actual
                        $completed = false; // Los siguientes no serán completados
                        $found_current = true;
                    } elseif ($completed && !$found_current) {
                        $class = 'completed'; // Pasos anteriores
                    }

                    // Si la solicitud fue rechazada, marcamos los anteriores como completed
                    if ($solicitud['status'] === 'Rechazada' && $class === 'active') {
                        $class = 'rejected';
                    }
                    
                    echo "<div class='step $class'>" . htmlspecialchars($step) . "</div>";
                }
                ?>
            </div>
        </div>
        <?php else: ?>
            <div class="card">
                <p>No has realizado ninguna solicitud de beca o no se ha encontrado tu información.</p>
            </div>
        <?php endif; ?>

        <button class="btn"><a href="../Student/Historical.php">Ver historial completo</a></button>
    </div>
    </body>
</html>

