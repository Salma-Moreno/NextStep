<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

// Conexion a la base de datos
include '../Conexiones/db.php';

$conexion = $conn;
$usuario_id = $_SESSION['usuario_id'];

$alumno = []; $detalles = []; $tutor = []; $direccion = [];
$id_estudiante = null;
$mensaje = ''; $tipo_mensaje = ''; $errores = [];

function e($array, $key) {
    return htmlspecialchars($array[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

// Obtener datos del estudiante
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

// Cargar datos existentes
if ($id_estudiante) {
    $tablas = [
        'student_details' => &$detalles,
        'tutor_data' => &$tutor, 
        'address' => &$direccion
    ];
    
    foreach ($tablas as $tabla => &$datos) {
        $sql = "SELECT * FROM $tabla WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $datos = $row;
        }
        $stmt->close();
    }
}

// Variables para mantener los datos del formulario después del POST
$form_data = [
    'nombre' => $alumno['Name'] ?? '',
    'apellido' => $alumno['Last_Name'] ?? '',
    'telefono' => $alumno['Phone_Number'] ?? '',
    'correo' => $alumno['Email_Address'] ?? '',
    'foto_url' => $alumno['Profile_Image'] ?? '',
    'fecha_nacimiento' => $detalles['Birthdate'] ?? '',
    'preparatoria' => $detalles['High_school'] ?? '',
    'grado' => $detalles['Grade'] ?? '',
    'licencia' => $detalles['License'] ?? '',
    'promedio' => $detalles['Average'] ?? '',
    'tutor_nombre' => $tutor['Tutor_name'] ?? '',
    'tutor_apellidos' => $tutor['Tutor_lastname'] ?? '',
    'tutor_telefono' => $tutor['Phone_Number'] ?? '',
    'tutor_direccion' => $tutor['Address'] ?? '',
    'calle' => $direccion['Street'] ?? '',
    'ciudad' => $direccion['City'] ?? '',
    'codigo_postal' => $direccion['Postal_Code'] ?? ''
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos y actualizar form_data
    $form_data['nombre'] = $nombre = trim($_POST['nombre'] ?? '');
    $form_data['apellido'] = $apellido = trim($_POST['apellido'] ?? '');
    
    // Eliminar espacios en teléfonos y códigos postales
    $form_data['telefono'] = $telefono = str_replace(' ', '', trim($_POST['telefono'] ?? ''));
    $form_data['correo'] = $correo = trim($_POST['correo'] ?? '');
    $form_data['foto_url'] = $foto_url = trim($_POST['foto_url'] ?? '') ?: null;
    
    $form_data['fecha_nacimiento'] = $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    if ($fecha_nacimiento === '') $fecha_nacimiento = null;
    
    $form_data['preparatoria'] = $preparatoria = trim($_POST['preparatoria'] ?? '');
    $form_data['grado'] = $grado = trim($_POST['grado'] ?? '');
    $form_data['licencia'] = $licencia = trim($_POST['licencia'] ?? '');
    $form_data['promedio'] = $promedio = $_POST['promedio'] === '' ? null : (int)$_POST['promedio'];
    
    $form_data['tutor_nombre'] = $tutor_nombre = trim($_POST['tutor_nombre'] ?? '');
    $form_data['tutor_apellidos'] = $tutor_apellidos = trim($_POST['tutor_apellidos'] ?? '');
    
    // Eliminar espacios en teléfono del tutor
    $form_data['tutor_telefono'] = $tutor_telefono = str_replace(' ', '', trim($_POST['tutor_telefono'] ?? ''));
    $form_data['tutor_direccion'] = $tutor_direccion = trim($_POST['tutor_direccion'] ?? '');
    
    $form_data['calle'] = $calle = trim($_POST['calle'] ?? '');
    $form_data['ciudad'] = $ciudad = trim($_POST['ciudad'] ?? '');
    
    // Eliminar espacios en código postal
    $form_data['codigo_postal'] = $codigo_postal = str_replace(' ', '', trim($_POST['codigo_postal'] ?? ''));

    // Validaciones
    if ($nombre === '') {
        $errores['nombre'] = "El nombre es obligatorio.";
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre)) {
        $errores['nombre'] = "Solo letras y espacios.";
    }
    
    if ($apellido === '') {
        $errores['apellido'] = "El apellido es obligatorio.";
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellido)) {
        $errores['apellido'] = "Solo letras y espacios.";
    }
    
    if ($correo === '') {
        $errores['correo'] = "El correo es obligatorio.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = "Correo no válido.";
    }
    
    // Validación de teléfono sin espacios
    if ($telefono !== '' && !preg_match('/^\d{10}$/', $telefono)) {
        $errores['telefono'] = "Debe tener exactamente 10 dígitos (sin espacios).";
    }
    
    // Validación mejorada para fecha de nacimiento (mínimo 14 años)
    if ($fecha_nacimiento) {
        $fecha_actual = new DateTime();
        $fecha_nac = new DateTime($fecha_nacimiento);
        $edad = $fecha_actual->diff($fecha_nac)->y;
        
        if ($fecha_nacimiento >= date('Y-m-d')) {
            $errores['fecha_nacimiento'] = "No puede ser futura.";
        } elseif ($edad < 14) {
            $errores['fecha_nacimiento'] = "Debes tener al menos 14 años para solicitar beca.";
        }
    }
    
    // Validación para preparatoria (solo letras, números y espacios)
    if ($preparatoria !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\.\-]+$/', $preparatoria)) {
        $errores['preparatoria'] = "Solo letras, números, puntos y guiones.";
    }
    
    // Validación para grado (SOLO número y símbolo de grado °)
    if ($grado !== '' && !preg_match('/^[0-9º°]+$/', $grado)) {
        $errores['grado'] = "Solo número y símbolo de grado (Ej: 3°, 4º)";
    }
    
    // Validación para licencia (letras, números y guiones)
    if ($licencia !== '' && !preg_match('/^[a-zA-Z0-9\-\s]+$/', $licencia)) {
        $errores['licencia'] = "Solo letras, números y guiones.";
    }
    
    if ($promedio !== null && ($promedio < 0 || $promedio > 100)) {
        $errores['promedio'] = "Entre 0 y 100.";
    }
    
    // Validaciones para tutor
    if ($tutor_nombre !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $tutor_nombre)) {
        $errores['tutor_nombre'] = "Solo letras y espacios.";
    }
    
    if ($tutor_apellidos !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $tutor_apellidos)) {
        $errores['tutor_apellidos'] = "Solo letras y espacios.";
    }
    
    // Validación de teléfono del tutor sin espacios
    if ($tutor_telefono !== '' && !preg_match('/^\d{10}$/', $tutor_telefono)) {
        $errores['tutor_telefono'] = "Debe tener exactamente 10 dígitos (sin espacios).";
    }
    
    // Validación para dirección del tutor
    if ($tutor_direccion !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\#\.\,]+$/', $tutor_direccion)) {
        $errores['tutor_direccion'] = "Caracteres no válidos en la dirección.";
    }
    
    // Validaciones para domicilio
    if ($calle !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\#\.\,]+$/', $calle)) {
        $errores['calle'] = "Caracteres no válidos en la calle.";
    }
    
    if ($ciudad !== '' && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $ciudad)) {
        $errores['ciudad'] = "Solo letras y espacios.";
    }
    
    // Validación de código postal de Tijuana (22000-22699)
    if ($codigo_postal !== '') {
        if (!preg_match('/^\d{5}$/', $codigo_postal)) {
            $errores['codigo_postal'] = "Debe tener exactamente 5 dígitos (sin espacios).";
        } else {
            $cp_numero = (int)$codigo_postal;
            if ($cp_numero < 22000 || $cp_numero > 22699) {
                $errores['codigo_postal'] = "El código postal debe estar en el rango de Tijuana (22000-22699).";
            }
        }
    }
    
    if ($foto_url && !filter_var($foto_url, FILTER_VALIDATE_URL)) {
        $errores['foto_url'] = "URL no válida.";
    }

    if (empty($errores)) {
        // Verificar si hay cambios reales antes de guardar
        $hay_cambios = false;
        
        // Comparar datos del estudiante
        if ($nombre !== ($alumno['Name'] ?? '') ||
            $apellido !== ($alumno['Last_Name'] ?? '') ||
            $telefono !== ($alumno['Phone_Number'] ?? '') ||
            $correo !== ($alumno['Email_Address'] ?? '') ||
            $foto_url !== ($alumno['Profile_Image'] ?? null)) {
            $hay_cambios = true;
        }
        
        // Comparar detalles académicos
        if ($fecha_nacimiento !== ($detalles['Birthdate'] ?? null) ||
            $preparatoria !== ($detalles['High_school'] ?? '') ||
            $grado !== ($detalles['Grade'] ?? '') ||
            $licencia !== ($detalles['License'] ?? '') ||
            $promedio !== ($detalles['Average'] ?? null)) {
            $hay_cambios = true;
        }
        
        // Comparar datos del tutor
        if ($tutor_nombre !== ($tutor['Tutor_name'] ?? '') ||
            $tutor_apellidos !== ($tutor['Tutor_lastname'] ?? '') ||
            $tutor_telefono !== ($tutor['Phone_Number'] ?? '') ||
            $tutor_direccion !== ($tutor['Address'] ?? '')) {
            $hay_cambios = true;
        }
        
        // Comparar domicilio
        if ($calle !== ($direccion['Street'] ?? '') ||
            $ciudad !== ($direccion['City'] ?? '') ||
            $codigo_postal !== ($direccion['Postal_Code'] ?? '')) {
            $hay_cambios = true;
        }

        if (!$hay_cambios) {
            $mensaje = "No se detectaron cambios para guardar.";
            $tipo_mensaje = 'info';
        } else {
            try {
                // Obtener o crear dependency
                $sql_dep = "SELECT ID_Dependency FROM dependency LIMIT 1";
                $result_dep = $conexion->query($sql_dep);
                if ($result_dep && $result_dep->num_rows > 0) {
                    $row_dep = $result_dep->fetch_assoc();
                    $fk_dependency = (int)$row_dep['ID_Dependency'];
                } else {
                    $sql_ins = "INSERT INTO dependency (Type) VALUES ('Tutor')";
                    $conexion->query($sql_ins);
                    $fk_dependency = $conexion->insert_id;
                }

                // Guardar student
                if ($id_estudiante) {
                    $sql = "UPDATE student SET Name=?, Last_Name=?, Phone_Number=?, Email_Address=?, Profile_Image=? WHERE ID_Student=?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("sssssi", $nombre, $apellido, $telefono, $correo, $foto_url, $id_estudiante);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $sql = "INSERT INTO student (FK_ID_User, Name, Last_Name, Phone_Number, Email_Address, Profile_Image) VALUES (?,?,?,?,?,?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("isssss", $usuario_id, $nombre, $apellido, $telefono, $correo, $foto_url);
                    $stmt->execute();
                    $id_estudiante = $stmt->insert_id;
                    $stmt->close();
                }

                // Guardar student_details
                $sql = "SELECT ID_Details FROM student_details WHERE FK_ID_Student = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $id_estudiante);
                $stmt->execute();
                $stmt->bind_result($id_detalles);
                $stmt->fetch();
                $stmt->close();

                if ($id_detalles) {
                    $sql = "UPDATE student_details SET Birthdate=?, High_school=?, Grade=?, License=?, Average=? WHERE ID_Details=?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("ssssii", $fecha_nacimiento, $preparatoria, $grado, $licencia, $promedio, $id_detalles);
                    $stmt->execute();
                } else {
                    $sql = "INSERT INTO student_details (FK_ID_Student, Birthdate, High_school, Grade, License, Average) VALUES (?,?,?,?,?,?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("issssi", $id_estudiante, $fecha_nacimiento, $preparatoria, $grado, $licencia, $promedio);
                    $stmt->execute();
                }
                $stmt->close();

                // Guardar tutor_data
                $sql = "SELECT ID_Data FROM tutor_data WHERE FK_ID_Student = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $id_estudiante);
                $stmt->execute();
                $stmt->bind_result($id_tutor);
                $stmt->fetch();
                $stmt->close();

                if ($id_tutor) {
                    $sql = "UPDATE tutor_data SET Tutor_name=?, Tutor_lastname=?, Phone_Number=?, Address=? WHERE ID_Data=?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("ssssi", $tutor_nombre, $tutor_apellidos, $tutor_telefono, $tutor_direccion, $id_tutor);
                    $stmt->execute();
                } else {
                    $sql = "INSERT INTO tutor_data (FK_ID_Student, FK_ID_Dependency, Tutor_name, Tutor_lastname, Phone_Number, Address) VALUES (?,?,?,?,?,?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("iissss", $id_estudiante, $fk_dependency, $tutor_nombre, $tutor_apellidos, $tutor_telefono, $tutor_direccion);
                    $stmt->execute();
                }
                $stmt->close();

                // Guardar address
                $sql = "SELECT ID_Address FROM address WHERE FK_ID_Student = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $id_estudiante);
                $stmt->execute();
                $stmt->bind_result($id_address);
                $stmt->fetch();
                $stmt->close();

                if ($id_address) {
                    $sql = "UPDATE address SET Street=?, City=?, Postal_Code=? WHERE ID_Address=?";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("sssi", $calle, $ciudad, $codigo_postal, $id_address);
                    $stmt->execute();
                } else {
                    $sql = "INSERT INTO address (FK_ID_Student, Street, City, Postal_Code) VALUES (?,?,?,?)";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bind_param("isss", $id_estudiante, $calle, $ciudad, $codigo_postal);
                    $stmt->execute();
                }
                $stmt->close();

                header("Location: " . $_SERVER['PHP_SELF'] . "?guardado=1");
                exit;

            } catch (Exception $e) {
                $mensaje = "Error al guardar: " . $e->getMessage();
                $tipo_mensaje = 'error';
            }
        }
    } else {
        $mensaje = "Por favor corrige los errores en el formulario.";
        $tipo_mensaje = 'error';
    }
}

// Solo mostrar mensaje de guardado si viene por GET (evita que se muestre al refrescar)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['guardado']) && $_GET['guardado'] == '1') {
    $mensaje = "Información guardada correctamente.";
    $tipo_mensaje = 'success';
}

$foto_perfil = !empty($form_data['foto_url']) ? $form_data['foto_url'] : 'https://via.placeholder.com/140?text=Foto+del+estudiante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Personales - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/viewStudent.css">
    <style>
        .error-field { border: 2px solid #ff4444 !important; }
        .error-message { color: #ff4444; font-size: 12px; margin-top: 5px; display: block; }
        .form-group { margin-bottom: 15px; }
        .alert { 
            padding: 12px 16px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
            font-weight: bold;
        }
        .alert.success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 2px solid #c3e6cb;
        }
        .alert.error { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 2px solid #f5c6cb;
        }
        .alert.info { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            border: 2px solid #bee5eb;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>
    <div class="container">
        <?php if (!empty($mensaje)): ?>
            <div class="alert <?php echo $tipo_mensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="profile-section">
                <img src="<?php echo $foto_perfil; ?>" alt="Foto del estudiante" class="profile-pic">
                <button type="button" class="change-photo-btn" onclick="document.getElementById('foto_url').focus();">
                    Cambiar foto
                </button>
            </div>

            <h2>Ficha del Estudiante</h2>

            <div class="form-grid">
                <div class="form-section">
                    <h3>Datos del estudiante</h3>
                    <div class="form-group">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($form_data['nombre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre" 
                               class="<?php echo isset($errores['nombre']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['nombre'])): ?>
                            <span class="error-message"><?php echo $errores['nombre']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Apellido:</label>
                        <input type="text" name="apellido" value="<?php echo htmlspecialchars($form_data['apellido'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Apellido"
                               class="<?php echo isset($errores['apellido']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['apellido'])): ?>
                            <span class="error-message"><?php echo $errores['apellido']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Teléfono:</label>
                        <input type="tel" name="telefono" value="<?php echo htmlspecialchars($form_data['telefono'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="10 dígitos sin espacios"
                               class="<?php echo isset($errores['telefono']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['telefono'])): ?>
                            <span class="error-message"><?php echo $errores['telefono']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Correo electrónico:</label>
                        <input type="email" name="correo" value="<?php echo htmlspecialchars($form_data['correo'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="correo@ejemplo.com"
                               class="<?php echo isset($errores['correo']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['correo'])): ?>
                            <span class="error-message"><?php echo $errores['correo']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>URL de la foto:</label>
                        <input type="url" id="foto_url" name="foto_url" value="<?php echo htmlspecialchars($form_data['foto_url'], ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="https://ejemplo.com/foto.jpg"
                               class="<?php echo isset($errores['foto_url']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['foto_url'])): ?>
                            <span class="error-message"><?php echo $errores['foto_url']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Detalles académicos</h3>
                    <div class="form-group">
                        <label>Fecha de nacimiento:</label>
                        <input type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars($form_data['fecha_nacimiento'], ENT_QUOTES, 'UTF-8'); ?>"
                               class="<?php echo isset($errores['fecha_nacimiento']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['fecha_nacimiento'])): ?>
                            <span class="error-message"><?php echo $errores['fecha_nacimiento']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Preparatoria:</label>
                        <input type="text" name="preparatoria" value="<?php echo htmlspecialchars($form_data['preparatoria'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre de la preparatoria"
                               class="<?php echo isset($errores['preparatoria']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['preparatoria'])): ?>
                            <span class="error-message"><?php echo $errores['preparatoria']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Grado:</label>
                        <input type="text" name="grado" value="<?php echo htmlspecialchars($form_data['grado'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. 3° o 4º"
                               class="<?php echo isset($errores['grado']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['grado'])): ?>
                            <span class="error-message"><?php echo $errores['grado']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Licencia:</label>
                        <input type="text" name="licencia" value="<?php echo htmlspecialchars($form_data['licencia'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. B-12345"
                               class="<?php echo isset($errores['licencia']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['licencia'])): ?>
                            <span class="error-message"><?php echo $errores['licencia']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Promedio:</label>
                        <input type="number" name="promedio" value="<?php echo htmlspecialchars($form_data['promedio'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="0-100" min="0" max="100"
                               class="<?php echo isset($errores['promedio']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['promedio'])): ?>
                            <span class="error-message"><?php echo $errores['promedio']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Datos del tutor</h3>
                    <div class="form-group">
                        <label>Nombre:</label>
                        <input type="text" name="tutor_nombre" value="<?php echo htmlspecialchars($form_data['tutor_nombre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre del tutor"
                               class="<?php echo isset($errores['tutor_nombre']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['tutor_nombre'])): ?>
                            <span class="error-message"><?php echo $errores['tutor_nombre']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Apellidos:</label>
                        <input type="text" name="tutor_apellidos" value="<?php echo htmlspecialchars($form_data['tutor_apellidos'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Apellido del tutor"
                               class="<?php echo isset($errores['tutor_apellidos']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['tutor_apellidos'])): ?>
                            <span class="error-message"><?php echo $errores['tutor_apellidos']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Teléfono:</label>
                        <input type="tel" name="tutor_telefono" value="<?php echo htmlspecialchars($form_data['tutor_telefono'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="10 dígitos sin espacios"
                               class="<?php echo isset($errores['tutor_telefono']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['tutor_telefono'])): ?>
                            <span class="error-message"><?php echo $errores['tutor_telefono']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Dirección:</label>
                        <input type="text" name="tutor_direccion" value="<?php echo htmlspecialchars($form_data['tutor_direccion'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Domicilio del tutor"
                               class="<?php echo isset($errores['tutor_direccion']) ? 'error-field' : ''; ?>">
                        <?php if (isset($errores['tutor_direccion'])): ?>
                            <span class="error-message"><?php echo $errores['tutor_direccion']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section address">
                <h3>Domicilio del estudiante</h3>
                <div class="form-group">
                    <label>Calle:</label>
                    <input type="text" name="calle" value="<?php echo htmlspecialchars($form_data['calle'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Reforma #123"
                           class="<?php echo isset($errores['calle']) ? 'error-field' : ''; ?>">
                    <?php if (isset($errores['calle'])): ?>
                        <span class="error-message"><?php echo $errores['calle']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Ciudad:</label>
                    <input type="text" name="ciudad" value="<?php echo htmlspecialchars($form_data['ciudad'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ej. Guadalajara"
                           class="<?php echo isset($errores['ciudad']) ? 'error-field' : ''; ?>">
                    <?php if (isset($errores['ciudad'])): ?>
                        <span class="error-message"><?php echo $errores['ciudad']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Código postal:</label>
                    <input type="text" name="codigo_postal" value="<?php echo htmlspecialchars($form_data['codigo_postal'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="22000-22699 (Tijuana)"
                           class="<?php echo isset($errores['codigo_postal']) ? 'error-field' : ''; ?>">
                    <?php if (isset($errores['codigo_postal'])): ?>
                        <span class="error-message"><?php echo $errores['codigo_postal']; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn">Guardar información</button>
        </form>
    </div>
</body>
</html>
