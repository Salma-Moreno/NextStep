<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

// Conexion a la base de datos
include '../Conexiones/db.php';

// Usamos $conexion como alias de $conn (sin cambiar tus variables originales)
$conexion = $conn;

// ID del usuario logueado
$usuario_id = $_SESSION['usuario_id'];

// Arrays para rellenar el formulario
$alumno    = [];
$detalles  = [];
$tutor     = [];
$direccion = [];
$id_estudiante = null;

// --- Función helper para imprimir valores de forma segura ----
function e($array, $key) {
    return htmlspecialchars($array[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

// =============== OBTENER ID DEL ESTUDIANTE ===============
$sql = "SELECT * FROM student WHERE FK_ID_User = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $alumno = $row;
    $id_estudiante = (int)$row['ID_Student'];
}
$stmt->close();

// =============== SI YA EXISTE ESTUDIANTE, CARGAR DETALLES ===============
if ($id_estudiante) {
    // student_details
    $sql = "SELECT * FROM student_details WHERE FK_ID_Student = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_estudiante);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $detalles = $row;
    }
    $stmt->close();

    // tutor_data
    $sql = "SELECT * FROM tutor_data WHERE FK_ID_Student = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_estudiante);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $tutor = $row;
    }
    $stmt->close();

    // address
    $sql = "SELECT * FROM address WHERE FK_ID_Student = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_estudiante);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $direccion = $row;
    }
    $stmt->close();
}

// =============== PROCESAR FORMULARIO ===============
$mensaje_ok = '';
$mensaje_error = '';
$mostrar_modal = false; // <-- NUEVO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Datos del estudiante ---
    $nombre    = trim($_POST['nombre']    ?? '');
    $apellido  = trim($_POST['apellido']  ?? '');
    $telefono  = trim($_POST['telefono']  ?? '');
    $correo    = trim($_POST['correo']    ?? '');

    // URL de la foto de perfil
    $foto_url  = trim($_POST['foto_url']  ?? '');
    if ($foto_url === '') {
        $foto_url = null; // permite dejarla vacía
    }

    // --- Datos académicos ---
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    if ($fecha_nacimiento === '') $fecha_nacimiento = null;

    $preparatoria = trim($_POST['preparatoria'] ?? '');
    $grado        = trim($_POST['grado']        ?? '');
    $licencia     = trim($_POST['licencia']     ?? '');
    $promedio     = $_POST['promedio'] === '' ? null : (int)$_POST['promedio'];

    // --- Datos del tutor ---
    $tutor_nombre    = trim($_POST['tutor_nombre']    ?? '');
    $tutor_apellidos = trim($_POST['tutor_apellidos'] ?? '');
    $tutor_telefono  = trim($_POST['tutor_telefono']  ?? '');
    $tutor_direccion = trim($_POST['tutor_direccion'] ?? '');

    // --- Domicilio del estudiante ---
    $calle        = trim($_POST['calle']        ?? '');
    $ciudad       = trim($_POST['ciudad']       ?? '');
    $codigo_postal= trim($_POST['codigo_postal']?? '');

    // Validación sencilla
    if ($nombre === '' || $apellido === '' || $correo === '') {
        $mensaje_error = "Nombre, apellido y correo son obligatorios.";
    } else {
        // Empezamos a guardar
        try {
            // ---------- student (insert o update) ----------
            if ($id_estudiante) {
                $sql = "UPDATE student 
                        SET Name = ?, Last_Name = ?, Phone_Number = ?, Email_Address = ?, Profile_Image = ?
                        WHERE ID_Student = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sssssi", $nombre, $apellido, $telefono, $correo, $foto_url, $id_estudiante);
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO student (FK_ID_User, Name, Last_Name, Phone_Number, Email_Address, Profile_Image)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("isssss", $usuario_id, $nombre, $apellido, $telefono, $correo, $foto_url);
                $stmt->execute();
                $id_estudiante = $stmt->insert_id;
                $stmt->close();
            }

            // ---------- student_details ----------
            $id_detalles = null;
            $sql = "SELECT ID_Details FROM student_details WHERE FK_ID_Student = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_estudiante);
            $stmt->execute();
            $stmt->bind_result($id_detalles);
            $stmt->fetch();
            $stmt->close();

            if ($id_detalles) {
                $sql = "UPDATE student_details
                        SET Birthdate = ?, High_school = ?, Grade = ?, License = ?, Average = ?
                        WHERE ID_Details = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("ssssii",
                    $fecha_nacimiento,
                    $preparatoria,
                    $grado,
                    $licencia,
                    $promedio,
                    $id_detalles
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO student_details
                        (FK_ID_Student, Birthdate, High_school, Grade, License, Average)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("issssi",
                    $id_estudiante,
                    $fecha_nacimiento,
                    $preparatoria,
                    $grado,
                    $licencia,
                    $promedio
                );
                $stmt->execute();
                $stmt->close();
            }

            // ---------- tutor_data ----------
            // Asegúrate de tener en la tabla dependency un registro con ID_Dependency = 1
            $fk_dependency = 1;

            $id_tutor = null;
            $sql = "SELECT ID_Data FROM tutor_data WHERE FK_ID_Student = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_estudiante);
            $stmt->execute();
            $stmt->bind_result($id_tutor);
            $stmt->fetch();
            $stmt->close();

            if ($id_tutor) {
                $sql = "UPDATE tutor_data
                        SET Tutor_name = ?, Tutor_lastname = ?, Phone_Number = ?, Address = ?
                        WHERE ID_Data = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("ssssi",
                    $tutor_nombre,
                    $tutor_apellidos,
                    $tutor_telefono,
                    $tutor_direccion,
                    $id_tutor
                );
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO tutor_data
                        (FK_ID_Student, FK_ID_Dependency, Tutor_name, Tutor_lastname, Phone_Number, Address)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("iissss",
                    $id_estudiante,
                    $fk_dependency,
                    $tutor_nombre,
                    $tutor_apellidos,
                    $tutor_telefono,
                    $tutor_direccion
                );
                $stmt->execute();
                $stmt->close();
            }

            // ---------- address ----------
            $id_address = null;
            $sql = "SELECT ID_Address FROM address WHERE FK_ID_Student = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_estudiante);
            $stmt->execute();
            $stmt->bind_result($id_address);
            $stmt->fetch();
            $stmt->close();

            if ($id_address) {
                $sql = "UPDATE address
                        SET Street = ?, City = ?, Postal_Code = ?
                        WHERE ID_Address = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sssi", $calle, $ciudad, $codigo_postal, $id_address);
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO address (FK_ID_Student, Street, City, Postal_Code)
                        VALUES (?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("isss", $id_estudiante, $calle, $ciudad, $codigo_postal);
                $stmt->execute();
                $stmt->close();
            }

            // Redirigir para evitar reenvío del formulario
            header("Location: " . $_SERVER['PHP_SELF'] . "?guardado=1");
            exit;

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar la información: " . $e->getMessage();
        }
    }
}

// Si venimos de la redirección
if (isset($_GET['guardado']) && $_GET['guardado'] == '1') {
    $mensaje_ok = "La información se guardó correctamente.";
    $mostrar_modal = true; // <-- NUEVO

    // volver a cargar datos (por si cambiaron)
    if ($id_estudiante) {
        // student
        $sql = "SELECT * FROM student WHERE ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $alumno = $row;
        }
        $stmt->close();

        // detalles
        $sql = "SELECT * FROM student_details WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $detalles = $row;
        }
        $stmt->close();

        // tutor
        $sql = "SELECT * FROM tutor_data WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $tutor = $row;
        }
        $stmt->close();

        // address
        $sql = "SELECT * FROM address WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $direccion = $row;
        }
        $stmt->close();
    }
}

// URL que se mostrará en la imagen de perfil
$foto_perfil = !empty($alumno['Profile_Image'])
    ? e($alumno, 'Profile_Image')
    : 'https://via.placeholder.com/140?text=Foto+del+estudiante';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Personales - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/viewStudent.css">
</head>
<body>

    <?php include '../Includes/HeaderMenuE.php'; ?>

    <!-- ===== MODAL DE ÉXITO ===== -->
<div id="successModal" class="ns-modal <?php echo ($mostrar_modal && $mensaje_ok) ? 'is-visible' : ''; ?>">
    <div class="ns-modal-backdrop"></div>
    <div class="ns-modal-content">
        <p><?php echo htmlspecialchars($mensaje_ok, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</div>

    <!-- === CONTENIDO PRINCIPAL === -->
    <div class="container">

        <?php if ($mensaje_error): ?>
            <div class="alert error"><?php echo $mensaje_error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">

    <div class="page-layout">

        <!-- COLUMNA IZQUIERDA: CARD DE PERFIL -->
        <div class="side-column">
            <div class="ns-card ns-card-profile">
                <div class="profile-section">
                    <img src="<?php echo $foto_perfil; ?>" alt="Foto del estudiante" class="profile-pic">
                    <button type="button" class="change-photo-btn" onclick="document.getElementById('foto_url').focus();">
                        Cambiar foto
                    </button>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CARDS CON LA INFORMACIÓN -->
        <div class="main-column">
            <!-- CARD 1: Ficha + datos del estudiante / académicos / tutor -->
             <div class="ns-card ns-card-section">

                <!-- TÍTULO CENTRADO DENTRO DE LA CARD -->
                 <h2 class="main-title">Ficha del Estudiante</h2>

                <div class="form-grid">
                    <!-- Columna 1 - Datos del estudiante -->
                    <div class="form-section">
                        <h3>Datos del estudiante</h3>

                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="nombre" value="<?php echo e($alumno, 'Name'); ?>" placeholder="Nombre">
                        </div>

                        <div class="form-group">
                            <label>Apellido:</label>
                            <input type="text" name="apellido" value="<?php echo e($alumno, 'Last_Name'); ?>" placeholder="Apellido">
                        </div>

                        <div class="form-group">
                            <label>Teléfono:</label>
                            <input type="tel" name="telefono" value="<?php echo e($alumno, 'Phone_Number'); ?>" placeholder="Ej. 5512345678">
                        </div>

                        <div class="form-group">
                            <label>Correo electrónico:</label>
                            <input type="email" name="correo" value="<?php echo e($alumno, 'Email_Address'); ?>" placeholder="correo@ejemplo.com">
                        </div>

                        <div class="form-group">
                            <label>URL de la foto de perfil:</label>
                            <input
                                type="url"
                                id="foto_url"
                                name="foto_url"
                                value="<?php echo e($alumno, 'Profile_Image'); ?>"
                                placeholder="https://ejemplo.com/mi-foto.jpg"
                            >
                            <small>Usa un enlace directo a una imagen (JPG, PNG, etc.).</small>
                        </div>
                    </div>

                    <!-- Columna 2 - Detalles académicos -->
                    <div class="form-section">
                        <h3>Detalles académicos</h3>

                        <div class="form-group">
                            <label>Fecha de nacimiento:</label>
                            <input type="date" name="fecha_nacimiento" value="<?php echo e($detalles, 'Birthdate'); ?>">
                        </div>

                        <div class="form-group">
                            <label>Preparatoria:</label>
                            <input type="text" name="preparatoria" value="<?php echo e($detalles, 'High_school'); ?>" placeholder="Nombre de la preparatoria">
                        </div>

                        <div class="form-group">
                            <label>Grado:</label>
                            <input type="text" name="grado" value="<?php echo e($detalles, 'Grade'); ?>" placeholder="Ej. 3°">
                        </div>

                        <div class="form-group">
                            <label>Licencia:</label>
                            <input type="text" name="licencia" value="<?php echo e($detalles, 'License'); ?>" placeholder="Ej. B-12345">
                        </div>

                        <div class="form-group">
                            <label>Promedio:</label>
                            <input type="number" name="promedio" value="<?php echo e($detalles, 'Average'); ?>" placeholder="0 - 100" min="0" max="100">
                        </div>
                    </div>

                    <!-- Columna 3 - Datos del tutor -->
                    <div class="form-section">
                        <h3>Datos del tutor</h3>

                        <div class="form-group">
                            <label>Nombre:</label>
                            <input type="text" name="tutor_nombre" value="<?php echo e($tutor, 'Tutor_name'); ?>" placeholder="Nombre del tutor">
                        </div>

                        <div class="form-group">
                            <label>Apellidos:</label>
                            <input type="text" name="tutor_apellidos" value="<?php echo e($tutor, 'Tutor_lastname'); ?>" placeholder="Apellido del tutor">
                        </div>

                        <div class="form-group">
                            <label>Teléfono:</label>
                            <input type="tel" name="tutor_telefono" value="<?php echo e($tutor, 'Phone_Number'); ?>" placeholder="Teléfono del tutor">
                        </div>

                        <div class="form-group">
                            <label>Dirección:</label>
                            <input type="text" name="tutor_direccion" value="<?php echo e($tutor, 'Address'); ?>" placeholder="Domicilio del tutor">
                        </div>
                    </div>
                </div>
            </div>

                <!-- CARD 2: Domicilio del estudiante -->
                 <div class="ns-card ns-card-section ns-address-card">
                    <div class="form-section address">
                        <h3>Domicilio del estudiante</h3>

                    <div class="form-group">
                        <label>Calle:</label>
                        <input type="text" name="calle" value="<?php echo e($direccion, 'Street'); ?>" placeholder="Ej. Reforma #123">
                    </div>

                    <div class="form-group">
                        <label>Ciudad:</label>
                        <input type="text" name="ciudad" value="<?php echo e($direccion, 'City'); ?>" placeholder="Ej. Guadalajara">
                    </div>

                    <div class="form-group">
                        <label>Código postal:</label>
                        <input type="text" name="codigo_postal" value="<?php echo e($direccion, 'Postal_Code'); ?>" placeholder="Ej. 44100">
                    </div>
                </div>
            </div>
            
                <!-- CARD 3: Botón Guardar -->
                 <div class="ns-card ns-card-footer">
                    <button type="submit" class="btn">Guardar información</button>
                </div>
            </div> <!-- /main-column -->
        </div> <!-- /page-layout -->
</form>
    </div>
    <?php if ($mostrar_modal && $mensaje_ok): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('successModal');
    if (!modal) return;

    modal.classList.add('is-visible');

    // Ocultar después de 2.5 segundos
    setTimeout(function () {
        modal.classList.remove('is-visible');

        // Opcional: quitar ?guardado=1 de la URL
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('guardado');
            window.history.replaceState({}, document.title, url.toString());
        }
    }, 2500);
});
</script>
<?php endif; ?>
</body>
</html>

