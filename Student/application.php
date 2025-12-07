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

/* ======== 1. Verificar si el estudiante tiene datos personales COMPLETOS ========= */
$tieneDatosPersonales = false;
$student_id = null;
$perfil_completo = false;

$sql = "SELECT s.ID_Student, s.Name, s.Last_Name, s.Email_Address, s.Phone_Number,
               sd.Birthdate, sd.High_school, sd.Grade, sd.License, sd.Average,
               td.Tutor_name, td.Tutor_lastname, td.Phone_Number as Tutor_Phone,
               ad.Street, ad.City, ad.Postal_Code
        FROM student s
        LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
        LEFT JOIN tutor_data td ON s.ID_Student = td.FK_ID_Student
        LEFT JOIN address ad ON s.ID_Student = ad.FK_ID_Student
        WHERE s.FK_ID_User = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];
    
    // Verificar si TODOS los campos obligatorios están llenos
    $tieneDatosPersonales = (
        !empty($row['Name']) &&
        !empty($row['Last_Name']) &&
        !empty($row['Email_Address']) &&
        !empty($row['Phone_Number']) &&
        !empty($row['Birthdate']) &&
        !empty($row['High_school']) &&
        !empty($row['Grade']) &&
        !empty($row['Tutor_name']) &&
        !empty($row['Tutor_lastname']) &&
        !empty($row['Tutor_Phone']) &&
        !empty($row['Street']) &&
        !empty($row['City']) &&
        !empty($row['Postal_Code'])
    );
    
    $perfil_completo = $tieneDatosPersonales;
}

$stmt->close();

/* ======== 2. Obtener TODAS las becas que el estudiante YA HA SOLICITADO ========= */
$kits_solicitados = []; // IDs de kits ya solicitados (sin importar estado)
$solicitudes_activas = []; // Solicitudes con estados activos
$tieneSolicitudesActivas = false;

if ($student_id) {
    // Consulta para obtener TODOS los kits que el estudiante ya ha solicitado
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
    
    // Consulta para obtener solicitudes ACTIVAS (para mostrar estado)
    $sql_activas = "SELECT FK_ID_Kit, status
                    FROM aplication
                    WHERE FK_ID_Student = ?
                      AND status NOT IN ('Cancelada', 'Rechazada')";
    
    $stmt_activas = $conn->prepare($sql_activas);
    $stmt_activas->bind_param("i", $student_id);
    $stmt_activas->execute();
    $res_activas = $stmt_activas->get_result();
    
    while ($activa = $res_activas->fetch_assoc()) {
        $solicitudes_activas[] = $activa;
    }
    
    $tieneSolicitudesActivas = !empty($solicitudes_activas);
    $stmt_activas->close();
}

/* ========= 3. Cancelar solicitud ========= */
if (isset($_POST['cancel_application']) && $student_id && isset($_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    
    $sql = "UPDATE aplication
            SET status = 'Cancelada'
            WHERE FK_ID_Student = ?
              AND FK_ID_Kit = ?
              AND status NOT IN ('Cancelada', 'Rechazada', 'Entregada')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $kit_id);
    
    if ($stmt->execute()) {
        header('Location: application.php?cancel_success=1');
        exit;
    }
    
    $stmt->close();
}

/* ========= 4. Aplicar a beca ========= */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Enviada';

    // Validaciones
    if (!$perfil_completo) {
        header('Location: perfil.php?completa=1');
        exit;
    }
    
    // Verificar que el estudiante no haya solicitado esta beca antes
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
    <title>Solicitud de Becas - Estudiante</title>
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
        
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .status-label {
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            background-color: #17a2b8;
            color: white;
            display: inline-block;
            margin-right: 10px;
        }
        
        .status-label.ya-solicitada {
            background-color: #6c757d;
        }
        
        .status-label.entregada {
            background-color: #28a745;
        }
        
        .status-label.enviada {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-label.en-revision {
            background-color: #0dcaf0;
            color: white;
        }
        
        .status-label.aprobada {
            background-color: #198754;
            color: white;
        }
        
        .status-label.entrega {
            background-color: #6f42c1;
            color: white;
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
        
        .profile-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        
        .profile-warning h3 {
            color: #856404;
            margin-top: 0;
        }
        
        .profile-warning .btn {
            margin-top: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .scholarship-header-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        
        .scholarship-header-card h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .scholarship-header-card h2 {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .scholarship-list {
            display: grid;
            gap: 20px;
        }
        
        .scholarship-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
        }
        
        .card-image {
            width: 150px;
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .card-body {
            flex: 1;
            padding: 20px;
        }
        
        .card-body h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .card-short-text {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .card-long-text {
            color: #4a5568;
            margin-bottom: 15px;
        }
        
        .card-footer {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .active-applications-info {
            background-color: #e7f3ff;
            border: 1px solid #b6d4fe;
            color: #084298;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .active-applications-info h4 {
            margin-top: 0;
            color: #084298;
        }
        
        .application-status-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
        }
        
        .application-status-list li {
            padding: 5px 0;
            border-bottom: 1px solid rgba(8, 66, 152, 0.1);
        }
        
        .application-status-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">

    <!-- ========= CARD SUPERIOR ========= -->
    <div class="scholarship-header-card">
        <h1> Solicitud de Becas</h1>

        <?php if ($perfil_completo): ?>
            <h2>Selecciona una de las becas disponibles para ti:</h2>
            
            <!-- Mostrar mensajes de error/success -->
            <?php if (isset($_GET['error']) && $_GET['error'] == 'beca_ya_solicitada'): ?>
                <div class="alert alert-warning">
                     Ya has solicitado esta beca anteriormente. <strong>NO puedes volver a solicitarla.</strong>
                </div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                     ¡Solicitud enviada exitosamente!
                </div>
            <?php elseif (isset($_GET['cancel_success'])): ?>
                <div class="alert alert-success">
                     ¡Solicitud cancelada exitosamente!
                </div>
            <?php endif; ?>

            <!-- Información de solicitudes activas -->
            <?php if ($tieneSolicitudesActivas): ?>
                <div class="active-applications-info">
                    <h4> Tus solicitudes activas:</h4>
                    <ul class="application-status-list">
                        <?php 
                        $activas_por_kit = [];
                        foreach ($solicitudes_activas as $solicitud) {
                            $activas_por_kit[$solicitud['FK_ID_Kit']] = $solicitud['status'];
                        }
                        
                        foreach ($activas_por_kit as $kit_id => $status): 
                        ?>
                        <li>Kit <?= $kit_id ?>: <span class="status-label status-<?= strtolower(str_replace(' ', '-', $status)) ?>"><?= htmlspecialchars($status) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <p><small>Puedes tener múltiples solicitudes activas simultáneamente.</small></p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h2>Perfil Incompleto</h2>
            <div class="profile-warning">
                <h3>⚠️ Perfil incompleto</h3>
                <p>Debes completar tu perfil antes de poder solicitar becas.</p>
                <p>Necesitas llenar todos los campos obligatorios en tu perfil.</p>
                <a href="perfil.php" class="btn">Ir a completar mi perfil</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($perfil_completo): ?>

    <!-- ========= LISTA DE BECAS ========= -->
    <div class="scholarship-list">

        <?php
        // Construir la consulta EXCLUYENDO kits ya solicitados
        $sql = "
            SELECT 
                k.ID_Kit,
                CONCAT('Kit #', k.ID_Kit, ' - ', s.Period, ' ', s.Year) AS Name,
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
        
        // Excluir todos los kits que ya solicitó (sin importar estado)
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
                
                // Verificar si es una beca activa actual
                $esBecaActiva = false;
                $estadoBecaActiva = '';
                foreach ($solicitudes_activas as $solicitud) {
                    if ($solicitud['FK_ID_Kit'] == $kitId) {
                        $esBecaActiva = true;
                        $estadoBecaActiva = $solicitud['status'];
                        break;
                    }
                }
        ?>

        <!-- ========= CARD INDIVIDUAL ========= -->
        <div class="scholarship-card">
            <div class="card-image"></div>

            <div class="card-body">
                <h3><?= htmlspecialchars($row['Name']) ?></h3>
                <p class="card-short-text"><?= htmlspecialchars($row['ShortDescription']) ?></p>
                <p class="card-long-text"><?= htmlspecialchars($row['LongDescription']) ?></p>

                <div class="card-footer">

                    <?php if (!$esKitSolicitado): ?>
                        <!-- PUEDE SOLICITAR - Nunca ha solicitado esta beca -->
                        <form method="POST" action="application.php">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">
                                 Solicitar beca
                            </button>
                        </form>

                    <?php elseif ($esBecaActiva): ?>
                        <!-- YA TIENE ESTA BECA ACTIVA -->
                        <?php if ($estadoBecaActiva === 'Entrega'): ?>
                            <span class="status-label entregada"> Entregada</span>
                        <?php elseif ($estadoBecaActiva === 'Aprobada'): ?>
                            <span class="status-label aprobada"> Aprobada</span>
                        <?php elseif ($estadoBecaActiva === 'En revisión'): ?>
                            <span class="status-label en-revision"> En revisión</span>
                        <?php else: ?>
                            <span class="status-label enviada"> <?= htmlspecialchars($estadoBecaActiva) ?></span>
                        <?php endif; ?>
                        
                        <?php if (!in_array($estadoBecaActiva, ['Entregada', 'Aprobada', 'Entrega'])): ?>
                            <form method="POST" action="application.php">
                                <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                                <button type="submit" name="cancel_application" class="cancel-btn" 
                                    onclick="return confirm('¿Cancelar esta solicitud?\n\nUna vez cancelada NO podrás volver a solicitarla.')">
                                     Cancelar
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- YA SOLICITÓ ESTA BECA PERO FUE CANCELADA/RECHAZADA -->
                        <span class="status-label ya-solicitada"> Ya solicitada</span>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endwhile;
        else:
            echo "<p class='alert alert-info'>No hay becas disponibles por el momento.</p>";
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