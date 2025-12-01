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

<<<<<<< HEAD
/// 1. Obtener ID del estudiante usando FK_ID_User
$sql_student = "SELECT ID_Student, Name, Last_Name, Phone_Number 
                FROM student 
                WHERE FK_ID_User = ?";
$stmt_student = $conn->prepare($sql_student);
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();

$tieneDatosPersonales = false;

if ($result_student->num_rows > 0) {
    $student = $result_student->fetch_assoc();
    $student_id = $student['ID_Student'];

    // Validar datos en tabla student
    $datosCompletosStudent =
        !empty($student['Name']) &&
        !empty($student['Last_Name']) &&
        !empty($student['Phone_Number']);

    // 2. Validar tabla address
    $sql_address = "SELECT Street, City, Postal_Code 
                    FROM address 
                    WHERE FK_ID_Student = ?";
    $stmt_address = $conn->prepare($sql_address);
    $stmt_address->bind_param("i", $student_id);
    $stmt_address->execute();
    $result_address = $stmt_address->get_result();

    $datosCompletosAddress = false;

    if ($result_address->num_rows > 0) {
        $address = $result_address->fetch_assoc();

        $datosCompletosAddress =
            !empty($address['Street']) &&
            !empty($address['City']) &&
            !empty($address['Postal_Code']);
    }

    // Resultado final
    if ($datosCompletosStudent && $datosCompletosAddress) {
        $tieneDatosPersonales = true;
    }
}

// Buscar registro en la tabla student
$sql = "SELECT 
            ID_Student, 
            Name, 
            Last_Name, 
            Email_Address
        FROM student
        WHERE FK_ID_User = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];
    // Aquí defines qué elementos mínimos debe tener el perfil
    if (!empty($row['Name']) &&
        !empty($row['Last_Name']) &&
        !empty($row['Email_Address'])) {
        $tieneDatosPersonales = true;
    }
}
$stmt->close();

/* ======== 2. Verificar si el estudiante ya tiene una solicitud ACTIVA y de un kit vigente ========= */
$tieneSolicitudActiva = false;
$solicitud_activa = null;

if ($student_id) {
    $sql = "SELECT a.ID_status, a.FK_ID_Kit, a.status
            FROM aplication a
            INNER JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
            WHERE a.FK_ID_Student = ?
              AND a.status <> 'Cancelada'
              AND k.Start_date <= CURDATE()
              AND k.End_date   >= CURDATE()
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

/* ========= 3. Lógica al presionar "Cancelar" una beca ========== */
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

/* ========= 4. Lógica al presionar "Aplicar" en una beca ========== */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {

    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Enviada';

    // Si no tiene perfil lo mandamos a completarlo
    if (!$tieneDatosPersonales || !$student_id) {
        header('Location: perfil.php?completa=1');
        exit;
    }

    // Si ya tiene una solicitud activa no permitimos otra
    if ($tieneSolicitudActiva) {
        header('Location: application.php');
        exit;
    }

    /* --------- Calcular ID_status porque no es AUTO_INCREMENT ------- */
    $next_id_status = 1;
    $sql_max_id = "SELECT MAX(ID_status) AS max_id FROM aplication";
    $result_max = $conn->query($sql_max_id);
    if ($result_max && $row_max = $result_max->fetch_assoc()) {
        if (!is_null($row_max['max_id'])) {
            $next_id_status = (int)$row_max['max_id'] + 1;
        }
    }

    /* --------- Insertar solicitud en la tabla aplication ------- */
$sql_insert = "INSERT INTO aplication 
                    (ID_status, FK_ID_Student, FK_ID_Kit, status, Application_date)
               VALUES (?, ?, ?, ?, NOW())";
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
    
    <!-- ========= CARD SUPERIOR: TITULO Y DESCRIPCION GENERAL ========= -->
    <div class="scholarship-header-card">
        <h1>Solicitud de Beca</h1>
<<<<<<< HEAD
<?php if ($tieneDatosPersonales) { ?> 
        <div class="scholarship-section">
            <h2>Becas Disponibles</h2>
            <p>Selecciona una de las becas disponibles para ti:</p>


            <div class="scholarship-list">
                <?php
                // Consulta para obtener los kits (becas) activos
                $sql = "SELECT ID_Kit, Name, Description FROM kit WHERE End_date >= CURDATE()";
                $result = $conn->query($sql);


                if ($result && $result->num_rows > 0) {
                    // Iterar sobre los resultados
                    while($row = $result->fetch_assoc()) {
                ?>
                <div class="scholarship-card">
                    <h3><?php echo htmlspecialchars($row['Name']); ?></h3>
                    <p><?php echo htmlspecialchars($row['Description']); ?></p>
                   
                                        <form method="POST" action="application.php">
                        <input type="hidden" name="kit_id" value="<?php echo $row['ID_Kit']; ?>">
                        <button type="submit" name="apply_scholarship" class="apply-btn">Aplicar</button>
                    </form>


                </div>
                <?php
                    }
                } else {
                    echo "<p>No hay becas (kits) disponibles en este momento.</p>";
                }
                ?>
            </div>
             <?php } else { ?>
            <div class="no-data-section">
                <h2>Información del Estudiante</h2>
                <p class="warning">
                    Por el momento no se encuentra información para solicitud disponible.<br>
                    Completa tu perfil para poder aplicar a una beca.
                </p><a href="perfil.php" class="btn">Ir a mi perfil</a>
            </div>
        <?php } ?>
        </div>

=======
>>>>>>> 6ad622712420b4a0c76a1459f979f61f4d47c488

        <?php if ($tieneDatosPersonales): ?>
            <h2>Selecciona una de las becas disponibles para ti:</h2>
        <?php else: ?>
            <!-- Mensaje cuando el estudiante no tiene perfil completo -->
            <h2>Información del Estudiante</h2>
            <p class="warning">
                No hemos encontrado tu información personal.<br>
                Debes completar tu perfil para aplicar a una beca.
            </p>
            <a href="perfil.php" class="btn">Ir a mi perfil</a>
        <?php endif; ?>
    </div>

    <?php if ($tieneDatosPersonales): ?>
    <!-- ========= SECCION INFERIOR: CARDS DE BECAS ========= -->

        <div class="scholarship-list">
            <?php
            // Traer kits activos (becas disponibles)
            // La tabla kit no tiene Name ni Description, así que los creamos con alias
            $sql = "
                SELECT 
                    k.ID_Kit,
                    CONCAT('Kit ', s.Period, ' ', s.Year) AS Name,
                    CONCAT(
                        'Material escolar para el periodo ',
                        s.Period,
                        ' ',
                        s.Year
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
                    $esKitSolicitado = $tieneSolicitudActiva && $solicitud_activa && ((int)$solicitud_activa['FK_ID_Kit'] === $kitId);
            ?>
            <!-- Card de beca individual -->
            <div class="scholarship-card">
                <div class="card-image"></div>
                <div class="card-body">
                    <h3><?= htmlspecialchars($row['Name']) ?></h3>
                    <p class="card-short-text"><?= htmlspecialchars($row['ShortDescription']) ?></p>
                    <p class="card-long-text"><?= htmlspecialchars($row['LongDescription']) ?></p>

                    <div class="card-footer">
                        <?php if (!$tieneSolicitudActiva): ?>
                            <!-- No tiene ninguna solicitud activa, puede solicitar esta -->
                            <form method="POST" action="application.php">
                                <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                                <button type="submit" name="apply_scholarship" class="apply-btn">
                                    Solicitar beca
                                </button>
                            </form>
                        <?php else: ?>
                            <?php if ($esKitSolicitado): ?>
                                <!-- Esta es la beca que ya solicitó -->
                                <span class="status-label">Solicitud enviada</span>
                                <form method="POST" action="application.php">
                                    <button type="submit" name="cancel_application" class="cancel-btn">
                                        Cancelar solicitud
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- Tiene otra beca activa, esta se deshabilita -->
                                <button type="button" class="apply-btn disabled" disabled>
                                    No disponible
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
                endwhile;
            else:
                echo "<p>No hay becas disponibles por el momento.</p>";
            endif;
            ?>
        </div>
    <?php endif; ?>

</div> <!-- fin .container -->
</body>
</html>