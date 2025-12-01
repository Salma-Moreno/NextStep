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

/* ========= Verificar solicitud activa ========= */
$tieneSolicitudActiva = false;
$solicitud_activa = null;

if ($student_id) {
    $sql = "SELECT a.ID_status, a.FK_ID_Kit, a.status
            FROM aplication a
            INNER JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
            WHERE a.FK_ID_Student = ?
              AND a.status <> 'Cancelada'
              AND k.Start_date <= CURDATE()
              AND k.End_date >= CURDATE()
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

/* ========= Cancelar solicitud ========= */
if (isset($_POST['cancel_application']) && $student_id) {
    $sql = "UPDATE aplication SET status = 'Cancelada' WHERE FK_ID_Student = ? AND status <> 'Cancelada'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();

    header('Location: application.php');
    exit;
}

/* ========= Aplicar a beca ========= */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Enviada';

    if (!$tieneDatosPersonales || !$student_id) {
        header('Location: perfil.php?completa=1');
        exit;
    }

    if ($tieneSolicitudActiva) {
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
            // Traer becas activas
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
                    $esKitSolicitado = $tieneSolicitudActiva && $solicitud_activa && ((int)$solicitud_activa['FK_ID_Kit'] === $kitId);
            ?>
                <div class="scholarship-card">
                    <h3><?= htmlspecialchars($row['Name']) ?></h3>
                    <p><?= htmlspecialchars($row['ShortDescription']) ?></p>
                    <p><?= htmlspecialchars($row['LongDescription']) ?></p>

                    <?php if (!$tieneSolicitudActiva): ?>
                        <form method="POST" action="application.php">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">Solicitar beca</button>
                        </form>
                    <?php else: ?>
                        <?php if ($esKitSolicitado): ?>
                            <span>Solicitud enviada</span>
                            <form method="POST" action="application.php">
                                <button type="submit" name="cancel_application">Cancelar solicitud</button>
                            </form>
                        <?php else: ?>
                            <button disabled>No disponible</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php
                endwhile;
            else:
                echo "<p>No hay becas disponibles.</p>";
            endif;
            ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
