<?php
session_start();

// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

include '../Conexiones/db.php';
$conexion = $conn;
$usuario_id = $_SESSION['usuario_id'];

$alumno    = [];
$detalles  = [];
$tutor     = [];
$direccion = [];
$id_estudiante = null;

// helper
function e($array, $key) {
    return htmlspecialchars($array[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

/* ================= OBTENER ESTUDIANTE ================= */
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

/* ================= CARGAR DETALLES EXISTENTES ================= */
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

/* ================= MENSAJES / MODAL ================= */
$mostrar_modal   = false;
$mensaje_modal   = '';
$tipo_modal      = ''; // 'success' o 'error'

/* ================= PROCESAR FORMULARIO (POST) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Datos del estudiante ---
    $nombre    = trim($_POST['nombre']    ?? '');
    $apellido  = trim($_POST['apellido']  ?? '');
    $telefono  = trim($_POST['telefono']  ?? '');
    $correo    = trim($_POST['correo']    ?? '');

    // ========== FOTO DE PERFIL ==========
// Primero tomamos lo que esté en el input de texto
$foto_url  = trim($_POST['foto_url']  ?? '');

// Si se subió un archivo, le damos prioridad
if (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../assets/uploads/';

    // Crear carpeta si no existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $tmpName      = $_FILES['profile_file']['tmp_name'];
    $originalName = basename($_FILES['profile_file']['name']);
    $ext          = pathinfo($originalName, PATHINFO_EXTENSION);

    // Nombre seguro/único
    $safeName = uniqid('profile_') . '.' . $ext;
    $destPath = $uploadDir . $safeName;

    if (move_uploaded_file($tmpName, $destPath)) {
        // Guardamos la ruta relativa que usará el <img src="...">
        // (por ejemplo "../assets/uploads/profile_xxx.png")
        $foto_url = $destPath;
    }
}

// Si sigue vacío, lo dejamos como null
if ($foto_url === '') {
    $foto_url = null;
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

    // --- Domicilio ---
    $calle         = trim($_POST['calle']         ?? '');
    $ciudad        = trim($_POST['ciudad']        ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');

    /* ---------- VALIDACIONES ---------- */
    $errores = [];

    if ($nombre === '' || $apellido === '' || $correo === '') {
        $errores[] = "Nombre, apellido y correo son obligatorios.";
    }

    // Sólo letras y espacios para nombre y apellido del alumno
    if ($nombre !== '' && !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
        $errores[] = "El nombre del estudiante sólo debe contener letras.";
    }
    if ($apellido !== '' && !preg_match('/^[\p{L}\s]+$/u', $apellido)) {
        $errores[] = "El apellido del estudiante sólo debe contener letras.";
    }

    // Tutor obligatorio + sólo letras
    if ($tutor_nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $tutor_nombre)) {
        $errores[] = "El nombre del tutor es obligatorio y sólo debe contener letras.";
    }
    if ($tutor_apellidos === '' || !preg_match('/^[\p{L}\s]+$/u', $tutor_apellidos)) {
        $errores[] = "Los apellidos del tutor son obligatorios y sólo deben contener letras.";
    }

    // Si hay errores, mostramos modal de error y NO guardamos
    if (!empty($errores)) {
        $tipo_modal    = 'error';
        // Unimos mensajes con salto de línea HTML
        $mensaje_modal = implode('<br>', array_map('htmlspecialchars', $errores));
        $mostrar_modal = true;
    } else {
        // Todo OK, guardamos en BD
        try {
            // student
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

            // student_details
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
                $stmt->bind_param("ssssii", $fecha_nacimiento, $preparatoria, $grado, $licencia, $promedio, $id_detalles);
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO student_details
                        (FK_ID_Student, Birthdate, High_school, Grade, License, Average)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("issssi", $id_estudiante, $fecha_nacimiento, $preparatoria, $grado, $licencia, $promedio);
                $stmt->execute();
                $stmt->close();
            }

            // tutor_data
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
                $stmt->bind_param("ssssi", $tutor_nombre, $tutor_apellidos, $tutor_telefono, $tutor_direccion, $id_tutor);
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO tutor_data
                        (FK_ID_Student, FK_ID_Dependency, Tutor_name, Tutor_lastname, Phone_Number, Address)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("iissss", $id_estudiante, $fk_dependency, $tutor_nombre, $tutor_apellidos, $tutor_telefono, $tutor_direccion);
                $stmt->execute();
                $stmt->close();
            }

            // address
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

            // Redirect sólo cuando TODO fue bien
            header("Location: " . $_SERVER['PHP_SELF'] . "?guardado=1");
            exit;

        } catch (Exception $e) {
            $tipo_modal    = 'error';
            $mensaje_modal = "Error al guardar la información.";
            $mostrar_modal = true;
        }
    }
}

/* ========== MENSAJE DE ÉXITO DESPUÉS DEL REDIRECT (GET) ========== */
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['guardado']) &&
    $_GET['guardado'] == '1'
) {
    $tipo_modal    = 'success';
    $mensaje_modal = 'La información se guardó correctamente.';
    $mostrar_modal = true;
}

/* ========== FOTO PERFIL ========== */
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

<!-- ===== MODAL ÚNICO (éxito / error) ===== -->
<div id="nsModal" class="ns-modal <?php echo $mostrar_modal ? 'is-visible' : ''; ?>">
    <div class="ns-modal-backdrop"></div>
    <div class="ns-modal-content <?php echo $tipo_modal === 'error' ? 'ns-modal-error' : 'ns-modal-success'; ?>">
        <p><?php echo $mensaje_modal; /* ya viene escapado */ ?></p>
    </div>
</div>

<!-- === CONTENIDO PRINCIPAL === -->
<div class="container">
    <form method="post" action="" enctype="multipart/form-data">
        <div class="page-layout">

            <!-- Columna izquierda -->
            <div class="side-column">
                <div class="ns-card ns-card-profile">
                    <div class="profile-section">
                        <img src="<?php echo $foto_perfil; ?>" alt="Foto del estudiante" class="profile-pic">
                        <!-- input file oculto -->
                         <input type="file"
                         id="profile_file"
                         name="profile_file"
                         accept="image/*"
                         style="display:none">
                         <button type="button" class="change-photo-btn" id="changePhotoBtn">
                            Cambiar foto
                        </button>
                    </div>
                </div>
            </div>

            <!-- Columna derecha -->
            <div class="main-column">
                <div class="ns-card ns-card-section">
                    <h2 class="main-title">Ficha del Estudiante</h2>

                    <div class="form-grid">
                        <!-- Datos estudiante -->
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
                                    type="text"
                                    id="foto_url"
                                    name="foto_url"
                                    value="<?php echo e($alumno, 'Profile_Image'); ?>"
                                    placeholder="Nombre de la imagen o URL"
                                >
                                <small>Usa un enlace directo a una imagen (JPG, PNG, etc.).</small>
                            </div>
                        </div>

                        <!-- Detalles académicos -->
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

                        <!-- Datos del tutor -->
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

                <!-- Domicilio -->
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

                <!-- Botón guardar -->
                <div class="ns-card ns-card-footer">
                    <button type="submit" class="btn">Guardar información</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('nsModal');
    if (!modal) return;

    // Si el modal está visible, lo ocultamos después de 2.5s
    if (modal.classList.contains('is-visible')) {
        setTimeout(function () {
            modal.classList.remove('is-visible');

            // Quitar ?guardado=1 de la URL si existe
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('guardado');
                window.history.replaceState({}, document.title, url.toString());
            }
        }, 2500);
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const changeBtn   = document.getElementById('changePhotoBtn');
    const fileInput   = document.getElementById('profile_file');
    const fotoUrlInput = document.getElementById('foto_url');
    const previewImg  = document.querySelector('.profile-pic');

    if (!changeBtn || !fileInput || !fotoUrlInput || !previewImg) return;

    // Al hacer clic en "Cambiar foto" abrimos el explorador
    changeBtn.addEventListener('click', function () {
        fileInput.click();
    });

    // Cuando el usuario selecciona un archivo
    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];

            // 1) Mostrar el nombre del archivo en el campo URL de la foto de perfil
            fotoUrlInput.value = file.name;

            // 2) Mostrar una previsualización de la imagen elegida
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
</body>
</html>
