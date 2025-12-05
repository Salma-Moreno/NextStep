<?php
session_start();

/* ========= GUARDIÁN: Solo estudiantes ========== */
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

/* ========= Conexión a la base de datos ========= */
include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];

/* ========= Obtener estudiante y verificar datos personales ========= */
$tieneDatosPersonales = false;
$student_id = null;

$sql_student = "SELECT s.ID_Student, s.Name, s.Last_Name, s.Email_Address, s.Phone_Number,
                        a.Street, a.City, a.Postal_Code
                FROM student s
                LEFT JOIN address a ON s.ID_Student = a.FK_ID_Student
                WHERE s.FK_ID_User = ?
                LIMIT 1";

$stmt = $conn->prepare($sql_student);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];

    $datosStudent = !empty($row['Name']) && !empty($row['Last_Name']) && !empty($row['Email_Address']) && !empty($row['Phone_Number']);
    $datosAddress = !empty($row['Street']) && !empty($row['City']) && !empty($row['Postal_Code']);

    if ($datosStudent && $datosAddress) {
        $tieneDatosPersonales = true;
    }
}
$stmt->close();

/* ========= Obtener TODAS las solicitudes activas del estudiante ========= */
// Usamos un array donde la clave es el ID del Kit para acceso rápido
$mis_solicitudes = [];

if ($student_id) {
    // Nota: Como ahora borramos las canceladas, no hace falta filtrar "status <> Cancelada"
    // porque ya no existirán en la BD. Pero lo dejamos por seguridad.
    $sql = "SELECT FK_ID_Kit, status 
            FROM aplication 
            WHERE FK_ID_Student = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($fila = $res->fetch_assoc()) {
        $mis_solicitudes[$fila['FK_ID_Kit']] = $fila['status'];
    }
    $stmt->close();
}

/* ========= Cancelar solicitud ESPECÍFICA (BORRADO FÍSICO) ========= */
if (isset($_POST['cancel_application'], $_POST['kit_id']) && $student_id) {
    $kit_to_cancel = (int)$_POST['kit_id'];

    // PROTECCIÓN: Solo permite eliminar si NO está aprobada ni entregada
    $estados_protegidos = [
        'Approved', 'Aprobado', 'Aprobada', 
        'Delivered', 'Entregado', 'Entregada', 'Entrega'
    ];
    
    // Verificamos el estado actual de ESTE kit específico
    $currentStatus = $mis_solicitudes[$kit_to_cancel] ?? '';
    
    // Si el estado NO está en la lista de protegidos, procedemos a BORRAR
    if (!in_array($currentStatus, $estados_protegidos)) {
        
        // --- CAMBIO PRINCIPAL AQUÍ ---
        // Antes: UPDATE aplication SET status = 'Cancelada' ...
        // Ahora: DELETE FROM aplication ...
        
        $sql = "DELETE FROM aplication WHERE FK_ID_Student = ? AND FK_ID_Kit = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $student_id, $kit_to_cancel);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Location: application.php');
    exit;
}

/* ========= Aplicar a beca ========= */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Pending'; 

    if (!$tieneDatosPersonales || !$student_id) {
        header('Location: perfil.php?completa=1');
        exit;
    }

    // VERIFICACIÓN: Solo bloqueamos si YA tiene solicitud PARA ESTE MISMO KIT
    if (isset($mis_solicitudes[$kit_id])) {
        header('Location: application.php');
        exit;
    }

    // Calcular ID_status
    $next_id_status = 1;
    $sql_max = "SELECT MAX(ID_status) AS max_id FROM aplication";
    $res_max = $conn->query($sql_max);
    if ($res_max && $row_max = $res_max->fetch_assoc()) {
        if (!is_null($row_max['max_id'])) $next_id_status = (int)$row_max['max_id'] + 1;
    }

    // Insertar solicitud
    $sql_insert = "INSERT INTO aplication (ID_status, FK_ID_Student, FK_ID_Kit, status, Application_date)
                   VALUES (?, ?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iiis", $next_id_status, $student_id, $kit_id, $initial_status);
    $stmt_insert->execute();
    $stmt_insert->close();

    header('Location: application.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Beca</title>
    <link rel="stylesheet" href="../assets/Student/application.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            width: 100%;
            margin-top: 10px;
            box-sizing: border-box;
        }
        .status-approved { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-delivered { background-color: #cce5ff; color: #004085; border: 1px solid #b8daff; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .btn-cancelar-neutro {
            background-color: #6c757d; 
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-size: 14px;
        }
        .btn-cancelar-neutro:hover { background-color: #5a6268; }
        
        .status-text-pending {
            display: block;
            margin-top: 10px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">
    <h1>Solicitud de Beca</h1>

    <?php if (!$tieneDatosPersonales): ?>
        <div class="no-data-section">
            <p>Debes completar tu perfil para aplicar a una beca.</p>
            <a href="perfil.php" class="btn">Ir a mi perfil</a>
        </div>
    <?php else: ?>
        <div class="scholarship-list">
            <?php
            // Traer TODAS las becas disponibles
            $sql = "SELECT k.ID_Kit,
                           CONCAT('Kit ', s.Period, ' ', s.Year) AS Name,
                           CONCAT('Material escolar para el periodo ', s.Period, ' ', s.Year) AS ShortDescription,
                           CONCAT('Vigente del ', DATE_FORMAT(k.Start_date, '%d/%m/%Y'), ' al ', DATE_FORMAT(k.End_date, '%d/%m/%Y')) AS LongDescription
                    FROM kit k
                    INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
                    WHERE k.Start_date <= CURDATE()
                      AND k.End_date >= CURDATE()";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                    $kitId = (int)$row['ID_Kit'];
                    
                    // ¿Tiene el estudiante una solicitud activa PARA ESTE KIT?
                    $estadoSolicitudActual = isset($mis_solicitudes[$kitId]) ? $mis_solicitudes[$kitId] : false;
            ?>
                <div class="scholarship-card">
                    <h3><?= htmlspecialchars($row['Name']) ?></h3>
                    <p><?= htmlspecialchars($row['ShortDescription']) ?></p>
                    <p><?= htmlspecialchars($row['LongDescription']) ?></p>

                    <?php if (!$estadoSolicitudActual): ?>
                        
                        <form method="POST" action="application.php">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">Solicitar beca</button>
                        </form>
                    
                    <?php else: ?>
                        
                        <?php 
                            $variantesAprobado = ['Approved', 'Aprobado', 'Aprobada'];
                            $variantesEntregado = ['Delivered', 'Entregado', 'Entregada', 'Entrega'];
                            $variantesRechazado = ['Rejected', 'Rechazado', 'Rechazada'];
                            
                            if (in_array($estadoSolicitudActual, $variantesAprobado)): ?>
                                <div class="status-badge status-approved">✅ Solicitud Aprobada</div>

                            <?php elseif (in_array($estadoSolicitudActual, $variantesEntregado)): ?>
                                <div class="status-badge status-delivered">📦 Kit Entregado</div>

                            <?php elseif (in_array($estadoSolicitudActual, $variantesRechazado)): ?>
                                 <div class="status-badge status-rejected">❌ Solicitud Rechazada</div>

                            <?php else: ?>
                                <span class="status-text-pending">Solicitud enviada (Pendiente)</span>
                                
                                <form method="POST" action="application.php">
                                    <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                                    <button type="submit" name="cancel_application" class="btn-cancelar-neutro">
                                        Cancelar solicitud
                                    </button>
                                </form>
                            <?php endif; ?>

                    <?php endif; ?>
                </div>
            <?php
                endwhile;
            else:
                echo "<p>No hay becas disponibles actualmente.</p>";
            endif;
            ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>