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

if ($student_id) {
    $sql = "SELECT ID_status, FK_ID_Kit, status
            FROM aplication
            WHERE FK_ID_Student = ?
              AND status <> 'Cancelada'
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

/* ========= 3. Cancelar solicitud ========= */
if (isset($_POST['cancel_application']) && $student_id) {

    $sql = "UPDATE aplication
            SET status = 'Cancelada'
            WHERE FK_ID_Student = ?
              AND status <> 'Cancelada'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();

    header('Location: application.php');
    exit;
}

/* ========= 4. Aplicar a beca ========= */
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

    $next_id_status = 1;

    $sql_max_id = "SELECT MAX(ID_status) AS max_id FROM aplication";
    $result_max = $conn->query($sql_max_id);

    if ($result_max && $row_max = $result_max->fetch_assoc()) {
        if (!is_null($row_max['max_id'])) {
            $next_id_status = (int)$row_max['max_id'] + 1;
        }
    }

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
        header('Location: application.php');
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
</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">

    <!-- ========= CARD SUPERIOR ========= -->
    <div class="scholarship-header-card">
        <h1>Solicitud de Beca</h1>

        <?php if ($tieneDatosPersonales): ?>
            <h2>Selecciona una de las becas disponibles para ti:</h2>

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

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):

            while ($row = $result->fetch_assoc()):
                $kitId = (int)$row['ID_Kit'];
                $esKitSolicitado = $tieneSolicitudActiva &&
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

                    <?php if (!$tieneSolicitudActiva): ?>
                        <form method="POST" action="application.php">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">
                                Solicitar beca
                            </button>
                        </form>

                    <?php else: ?>

                        <?php if ($esKitSolicitado): ?>
                            <span class="status-label">Solicitud enviada</span>

                            <form method="POST" action="application.php">
                                <button type="submit" name="cancel_application" class="cancel-btn">
                                    Cancelar solicitud
                                </button>
                            </form>

                        <?php else: ?>
                            <button type="button" class="apply-btn disabled" disabled>
                                No disponible
                            </button>
                        <?php endif; ?>

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
</body>
</html>