<?php 
session_start();

/* ========= GUARDIÁN: Solo estudiantes pueden entrar =========== */
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

/* ========= Conexión a la base de datos ======== */
include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];

/* ======== 1. Verificar si el estudiante tiene datos personales ========= */
$tieneDatosPersonales = false;
$student_id = null;

$sql = "SELECT ID_Student, Name, Last_Name, Email_Address 
        FROM student 
        WHERE FK_ID_User = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];

    if (!empty($row['Name']) &&
        !empty($row['Last_Name']) &&
        !empty($row['Email_Address'])) {
        $tieneDatosPersonales = true;
    }
}

$stmt->close();

/* ======== 2. Verificar si el estudiante ya tiene una solicitud activa ========= */
$tieneSolicitudActiva = false;
$solicitud_activa = null;

// **CAMBIO CRÍTICO: Solo verificar solicitudes activas (no canceladas, no rechazadas)**
if ($student_id) {
    $sql = "SELECT ID_status, FK_ID_Kit, status
            FROM aplication
            WHERE FK_ID_Student = ?
              AND status NOT IN ('Cancelada', 'Rechazada')
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($fila = $res->fetch_assoc()) {
        $tieneSolicitudActiva = true;
        $solicitud_activa = $fila;
    }
    
    $stmt->close();
}

/* ======== 3. Obtener TODOS los kits que el estudiante YA HA SOLICITADO ========= */
$kits_solicitados = [];

// **CAMBIO CRÍTICO: Incluir TODAS las solicitudes, sin importar el estado**
if ($student_id) {
    $sql_kits = "SELECT DISTINCT FK_ID_Kit 
                FROM aplication 
                WHERE FK_ID_Student = ?";
    
    $stmt_kits = $conn->prepare($sql_kits);
    $stmt_kits->bind_param("i", $student_id);
    $stmt_kits->execute();
    $result_kits = $stmt_kits->get_result();
    
    while ($kit_row = $result_kits->fetch_assoc()) {
        $kits_solicitados[] = (int)$kit_row['FK_ID_Kit'];
    }
    
    $stmt_kits->close();
}

/* ========= 4. Cancelar solicitud ========= */
if (isset($_POST['cancel_application']) && $student_id) {
    // **IMPORTANTE: Cambiar a 'Cancelada' en lugar de borrar**
    $sql = "UPDATE aplication
            SET status = 'Cancelada'
            WHERE FK_ID_Student = ?
              AND status NOT IN ('Cancelada', 'Rechazada', 'Entregada')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    
    if ($stmt->execute()) {
        header('Location: application.php');
        exit;
    }
    
    $stmt->close();
}

/* ========= 5. Aplicar a beca ========= */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Enviada';

    // Validaciones
    if (!$tieneDatosPersonales || !$student_id) {
        header('Location: perfil.php?completa=1');
        exit;
    }

    // **CAMBIO: Verificar si YA tiene solicitud activa**
    if ($tieneSolicitudActiva) {
        header('Location: application.php?error=solicitud_activa');
        exit;
    }
    
    // **CAMBIO CRÍTICO: Verificar si YA solicitó ESTA beca (sin importar estado)**
    if (in_array($kit_id, $kits_solicitados)) {
        header('Location: application.php?error=beca_ya_solicitada');
        exit;
    }

    // Generar nuevo ID
    $next_id_status = 1;
    $sql_max_id = "SELECT MAX(ID_status) AS max_id FROM aplication";
    $result_max = $conn->query($sql_max_id);

    if ($result_max && $row_max = $result_max->fetch_assoc()) {
        if (!is_null($row_max['max_id'])) {
            $next_id_status = (int)$row_max['max_id'] + 1;
        }
    }

    // Insertar nueva solicitud
    $sql_insert = "INSERT INTO aplication 
                    (ID_status, FK_ID_Student, FK_ID_Kit, status)
                   VALUES (?, ?, ?, ?)";

    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "iiis",
        $next_id_status,
        $student_id,
        $kit_id,
        $initial_status
    );

    if ($stmt_insert->execute()) {
        header('Location: application.php?success=1');
        exit;
    }

    $stmt_insert->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Student</title>
    <link rel="stylesheet" href="../assets/Student/application.css">
    <style>
        .alert {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .status-label {
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            background-color: #17a2b8;
            color: white;
        }
        
        .status-label.ya-solicitada {
            background-color: #6c757d;
        }
        
        .status-label.entregada {
            background-color: #28a745;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .btn:hover {
            background-color: #0056b3;
        }
        
        .cancel-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .cancel-btn:hover {
            background-color: #c82333;
        }
        
        .apply-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .apply-btn:hover {
            background-color: #218838;
        }
        
        .apply-btn.disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        
        .warning {
            color: #856404;
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
        }
    </style>
</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">

    <!-- ========= CARD SUPERIOR ========= -->
    <div class="scholarship-header-card">
        <h1>Solicitud de Beca</h1>

        <?php if ($tieneDatosPersonales): ?>
            <h2>Selecciona una de las becas disponibles para ti:</h2>
            
            <!-- Mostrar mensajes de error/success -->
            <?php if (isset($_GET['error']) && $_GET['error'] == 'beca_ya_solicitada'): ?>
                <div class="alert alert-warning">
                    ❌ Ya has solicitado esta beca anteriormente. <strong>NO puedes volver a solicitarla.</strong>
                </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] == 'solicitud_activa'): ?>
                <div class="alert alert-warning">
                    ⚠️ Ya tienes una solicitud activa. Debes cancelarla antes de solicitar otra.
                </div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ ¡Solicitud enviada exitosamente!
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h2>Información del Estudiante</h2>
            <p class="warning">
                No hemos encontrado tu información personal.<br>
                Debes completar tu perfil para aplicar a una beca.
            </p>
            <a href="perfil.php" class="btn">Ir a mi perfil</a>
        <?php endif; ?>
    </div>

    <?php if ($tieneDatosPersonales): ?>

    <!-- ========= LISTA DE BECAS ========= -->
    <div class="scholarship-list">

        <?php
        // Construir la consulta EXCLUYENDO kits ya solicitados (sin importar estado)
        $sql = "
            SELECT 
                k.ID_Kit,
                CONCAT('Kit ', s.Period, ' ', s.Year) AS Name,
                CONCAT(
                    'Material escolar para el periodo ',
                    s.Period, ' ', s.Year
                ) AS ShortDescription,
                CONCAT(
                    'Vigente del ',
                    DATE_FORMAT(k.Start_date, '%d/%m/%Y'),
                    ' al ',
                    DATE_FORMAT(k.End_date, '%d/%m/%Y')
                ) AS LongDescription
            FROM kit k
            INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
            WHERE k.Start_date <= CURDATE()
              AND k.End_date   >= CURDATE()
        ";
        
        // **CAMBIO CRÍTICO: Excluir TODOS los kits que ya solicitó (sin importar estado)**
        if (!empty($kits_solicitados)) {
            $excluded_ids = implode(',', $kits_solicitados);
            $sql .= " AND k.ID_Kit NOT IN ($excluded_ids)";
        }
        
        $sql .= " ORDER BY k.Start_date DESC";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):

            while ($row = $result->fetch_assoc()):
                $kitId = (int)$row['ID_Kit'];
                $esKitSolicitado = in_array($kitId, $kits_solicitados);
                
                // Verificar si es la beca activa actual
                $esBecaActiva = $tieneSolicitudActiva && 
                               $solicitud_activa && 
                               ((int)$solicitud_activa['FK_ID_Kit'] === $kitId);
        ?>

        <!-- ========= CARD INDIVIDUAL ========= -->
        <div class="scholarship-card">
            <div class="card-image"></div>

            <div class="card-body">
                <h3><?= htmlspecialchars($row['Name']) ?></h3>
                <p class="card-short-text"><?= htmlspecialchars($row['ShortDescription']) ?></p>
                <p class="card-long-text"><?= htmlspecialchars($row['LongDescription']) ?></p>

                <div class="card-footer">

                    <?php if (!$tieneSolicitudActiva && !$esKitSolicitado): ?>
                        <form method="POST" action="application.php">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">
                                 Solicitar beca
                            </button>
                        </form>

                    <?php elseif ($esBecaActiva): ?>
                        <span class="status-label"> Solicitud enviada</span>
                        
                        <?php if ($solicitud_activa['status'] !== 'Entregada'): ?>
                            <form method="POST" action="application.php">
                                <button type="submit" name="cancel_application" class="cancel-btn" 
                                    onclick="return confirm('¿Cancelar esta solicitud?\n\nUna vez cancelada NO podrás volver a solicitarla.')">
                                     Cancelar solicitud
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="status-label entregada"> Beca entregada</span>
                        <?php endif; ?>

                    <?php elseif ($esKitSolicitado): ?>
                        <span class="status-label ya-solicitada"> Ya solicitada</span>
                        
                    <?php else: ?>
                        <button type="button" class="apply-btn disabled" disabled>
                            No disponible
                        </button>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endwhile;
        else:
            echo "<p>No hay becas disponibles por el momento.</p>";
        endif;
        ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Confirmación antes de cancelar
document.querySelectorAll('form[action*="cancel_application"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!confirm('¿Estás seguro de cancelar esta solicitud?\n\nUna vez cancelada NO podrás volver a solicitarla.')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>