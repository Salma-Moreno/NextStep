
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

// --- LOGICA DE APLICACION DE BECA ---
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = $_POST['kit_id'];
    $initial_status = 'Enviada'; // Primer estado de la solicitud


    // 1. Obtener el ID del estudiante a partir del ID de usuario
    $sql_student_id = "SELECT ID_Student FROM student WHERE FK_ID_User = ?";
    $stmt_student = $conn->prepare($sql_student_id);
    $stmt_student->bind_param("i", $user_id);
    $stmt_student->execute();
    $result_student = $stmt_student->get_result();


    if ($result_student->num_rows > 0) {
        $student_row = $result_student->fetch_assoc();
        $student_id = $student_row['ID_Student'];
       
        // **IMPORTANTE: Lógica para ID_status si NO es AUTO_INCREMENT**
        // Si el campo ID_status NO es AUTO_INCREMENT, necesitamos calcular el siguiente ID
        $next_id_status = null;
        if (!true) { // Asume true si has hecho el AUTO_INCREMENT, sino usa la lógica manual
            $sql_max_id = "SELECT MAX(ID_status) AS max_id FROM aplication";
            $result_max = $conn->query($sql_max_id);
            $next_id_status = 1;
            if ($result_max && $row_max = $result_max->fetch_assoc()) {
                $next_id_status = $row_max['max_id'] + 1;
            }
        }
       
        // 2. Insertar la solicitud en la tabla aplication
        // Usa una sentencia preparada para mayor seguridad
        if ($next_id_status !== null) {
            // Si ID_status NO es AUTO_INCREMENT
            $sql_insert = "INSERT INTO aplication (ID_status, FK_ID_Student, FK_ID_Kit, status) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iiis", $next_id_status, $student_id, $kit_id, $initial_status);
        } else {
            // Si ID_status ES AUTO_INCREMENT (Recomendado)
            $sql_insert = "INSERT INTO aplication (FK_ID_Student, FK_ID_Kit, status) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iis", $student_id, $kit_id, $initial_status);
        }
       


        if ($stmt_insert->execute()) {
            // 3. Redirigir al estatus de la solicitud
            header('Location: Status.php'); // Redirige a la página Status.php
            exit;
        } else {
            // Manejar error de inserción
            // Opcional: Mostrar un error al usuario si falla la DB
            // echo "<script>alert('Error al registrar la solicitud: " . $stmt_insert->error . "');</script>";
        }
        $stmt_insert->close();
    } else {
         // Opcional: Mostrar un error si no se encontró el estudiante
         // echo "<script>alert('Error: No se encontró el registro del estudiante.');</script>";
    }
    $stmt_student->close();
}
// --- FIN LOGICA DE APLICACION ---
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
        <h1>Solicitud de Beca</h1>
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


    </div>
</body>
</html>





