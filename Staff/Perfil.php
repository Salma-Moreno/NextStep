<?php
session_start();
// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

include '../Conexiones/db.php';
$conexion = $conn;
$usuario_id = $_SESSION['usuario_id'];
$staff = [];
$errores_campos = [];

// Variables para guardar los valores del POST cuando hay errores
$post_values = [];

// helper function
function e($array, $key) {
    return htmlspecialchars($array[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

// Nueva función para obtener valores del POST cuando hay errores
function getValue($field, $default = '') {
    global $post_values;
    return htmlspecialchars($post_values[$field] ?? $default, ENT_QUOTES, 'UTF-8');
}

// Función para comparar si los valores son realmente diferentes
function valoresDiferentes($valor1, $valor2) {
    // Convertir ambos a string y trim
    $v1 = trim((string)$valor1);
    $v2 = trim((string)$valor2);
    
    // Comparar
    return $v1 !== $v2;
}

// Función para verificar si un usuario ya existe
function usuarioExiste($conexion, $usuario, $excluir_usuario_id = null) {
    $sql = "SELECT ID_User FROM user WHERE Username = ?";
    $params = [$usuario];
    $types = "s";
    
    if ($excluir_usuario_id) {
        $sql .= " AND ID_User != ?";
        $params[] = $excluir_usuario_id;
        $types .= "i";
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $conexion->prepare($sql);
    if ($excluir_usuario_id) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param($types, $params[0]);
    }
    
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();
    
    return $existe;
}

// Función para verificar si un correo ya existe
function correoExiste($conexion, $correo, $excluir_staff_id = null, $excluir_usuario_id = null) {
    // Verificar en staff
    $sql_staff = "SELECT ID_Staff FROM staff WHERE Email = ?";
    $params_staff = [$correo];
    $types_staff = "s";
    
    if ($excluir_staff_id) {
        $sql_staff .= " AND ID_Staff != ?";
        $params_staff[] = $excluir_staff_id;
        $types_staff .= "i";
    }
    
    $sql_staff .= " LIMIT 1";
    
    $stmt = $conexion->prepare($sql_staff);
    if ($excluir_staff_id) {
        $stmt->bind_param($types_staff, ...$params_staff);
    } else {
        $stmt->bind_param($types_staff, $params_staff[0]);
    }
    
    $stmt->execute();
    $stmt->store_result();
    $existe_en_staff = $stmt->num_rows > 0;
    $stmt->close();
    
    // Verificar en students (solo si no existe en staff)
    if (!$existe_en_staff) {
        $sql_student = "SELECT ID_Student FROM student WHERE Email_Address = ?";
        $params_student = [$correo];
        $types_student = "s";
        
        if ($excluir_usuario_id) {
            // Obtener ID del estudiante basado en el usuario_id
            $sql_get_student_id = "SELECT ID_Student FROM student WHERE FK_ID_User = ?";
            $stmt_get = $conexion->prepare($sql_get_student_id);
            $stmt_get->bind_param("i", $excluir_usuario_id);
            $stmt_get->execute();
            $stmt_get->bind_result($estudiante_id);
            $stmt_get->fetch();
            $stmt_get->close();
            
            if ($estudiante_id) {
                $sql_student .= " AND ID_Student != ?";
                $params_student[] = $estudiante_id;
                $types_student .= "i";
            }
        }
        
        $sql_student .= " LIMIT 1";
        
        $stmt = $conexion->prepare($sql_student);
        if (count($params_student) > 1) {
            $stmt->bind_param($types_student, ...$params_student);
        } else {
            $stmt->bind_param($types_student, $params_student[0]);
        }
        
        $stmt->execute();
        $stmt->store_result();
        $existe_en_student = $stmt->num_rows > 0;
        $stmt->close();
        
        return $existe_en_student;
    }
    
    return $existe_en_staff;
}

/* ================= OBTENER STAFF ================= */
$sql = "SELECT s.*, u.Username, u.registration_date 
        FROM staff s 
        JOIN user u ON s.FK_ID_User = u.ID_User 
        WHERE s.FK_ID_User = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $staff = $row;
    $id_staff = (int)$row['ID_Staff'];
    $correo_actual = $row['Email']; // Guardar el correo actual para comparar
    $usuario_actual = $row['Username']; // Guardar el usuario actual para comparar
    $stmt->close();
} else {
    $stmt->close();
    die("Error: No se encontró tu perfil de staff. Esto no debería pasar. Contacta al administrador.");
}

/* ================= VERIFICAR SI EL PERFIL ESTÁ COMPLETO ================= */
$perfil_completo = false;
if (!empty($staff['Firstname']) && !empty($staff['Lastname']) && !empty($staff['Email']) && !empty($staff['Phone'])) {
    $perfil_completo = true;
}

/* ================= MENSAJES / MODAL ================= */
$mostrar_modal = false;
$mensaje_modal = '';
$tipo_modal = ''; // 'success', 'warning' o 'error'

/* ================= PROCESAR FORMULARIO (POST) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Datos del staff ---
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');
    $confirmar_contrasena = trim($_POST['confirmar_contrasena'] ?? '');
    $foto_url = trim($_POST['foto_url'] ?? '');

    // Guardar valores del POST
    $post_values['nombre'] = $nombre;
    $post_values['apellido'] = $apellido;
    $post_values['telefono'] = $telefono;
    $post_values['correo'] = $correo;
    $post_values['usuario'] = $usuario;
    $post_values['contrasena'] = $contrasena;
    $post_values['confirmar_contrasena'] = $confirmar_contrasena;
    $post_values['foto_url'] = $foto_url;

    /* ---------- VALIDACIONES ---------- */
    // Array para trackear qué campos son válidos
    $campos_validos = [
        'nombre' => true,
        'apellido' => true,
        'telefono' => true,
        'correo' => true,
        'usuario' => true,
        'contrasena' => true,
        'confirmar_contrasena' => true,
        'foto' => true
    ];
    
    // Nombre - Acepta letras y espacios, pero no solo espacios
    $nombre_sin_espacios = str_replace(' ', '', $nombre);
    if (empty($nombre_sin_espacios) || !preg_match('/^[\p{L}]+(?:[\s][\p{L}]+)*$/u', $nombre)) {
        $errores_campos['nombre'] = "Sólo letras y espacios permitidos entre palabras. No puede ser solo espacios.";
        $campos_validos['nombre'] = false;
    }
    
    // Apellido - Acepta letras y espacios, pero no solo espacios
    $apellido_sin_espacios = str_replace(' ', '', $apellido);
    if (empty($apellido_sin_espacios) || !preg_match('/^[\p{L}]+(?:[\s][\p{L}]+)*$/u', $apellido)) {
        $errores_campos['apellido'] = "Sólo letras y espacios permitidos entre palabras. No puede ser solo espacios.";
        $campos_validos['apellido'] = false;
    }
    
    // Teléfono
    if (!preg_match('/^\d{10}$/', $telefono)) {
        $errores_campos['telefono'] = "Debe contener exactamente 10 números, sin espacios.";
        $campos_validos['telefono'] = false;
    }
    
    // Correo
    if ($correo) {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores_campos['correo'] = "Correo inválido.";
            $campos_validos['correo'] = false;
        } else {
            // Verificar si el correo es diferente al actual
            if (strtolower($correo) !== strtolower($correo_actual)) {
                // Verificar si el correo ya existe
                if (correoExiste($conexion, $correo, $id_staff, $usuario_id)) {
                    $errores_campos['correo'] = "Este correo ya está registrado por otro usuario.";
                    $campos_validos['correo'] = false;
                }
            }
        }
    }
    
    // Usuario
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $usuario)) {
        $errores_campos['usuario'] = "Sólo letras, números y guiones bajos.";
        $campos_validos['usuario'] = false;
    } else {
        // Verificar si el usuario es diferente al actual
        if (strtolower($usuario) !== strtolower($usuario_actual)) {
            if (usuarioExiste($conexion, $usuario, $usuario_id)) {
                $errores_campos['usuario'] = "Este nombre de usuario ya está en uso.";
                $campos_validos['usuario'] = false;
            }
        }
    }
    
    // Contraseña
    if (!empty($contrasena)) {
        if (strlen($contrasena) < 6) {
            $errores_campos['contrasena'] = "La contraseña debe tener al menos 6 caracteres.";
            $campos_validos['contrasena'] = false;
        } elseif ($contrasena !== $confirmar_contrasena) {
            $errores_campos['confirmar_contrasena'] = "Las contraseñas no coinciden.";
            $campos_validos['confirmar_contrasena'] = false;
        }
    }
    
    // ========== PROCESAR FOTO DE PERFIL ==========
    if (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === UPLOAD_ERR_OK) {
        // Configuración de la subida
        $uploadDir = '../assets/uploads/staff/';
        
        // Crear directorio si no existe
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $tmpName = $_FILES['profile_file']['tmp_name'];
        $originalName = basename($_FILES['profile_file']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Extensiones permitidas
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowedExts)) {
            $errores_campos['foto'] = "Formato de imagen no permitido. Use JPG, PNG, GIF o WebP.";
            $campos_validos['foto'] = false;
        } else {
            // Validar tamaño (máximo 5MB)
            if ($_FILES['profile_file']['size'] > 5 * 1024 * 1024) {
                $errores_campos['foto'] = "La imagen es demasiado grande. Máximo 5MB.";
                $campos_validos['foto'] = false;
            } else {
                // Generar nombre único
                $safeName = 'staff_' . $id_staff . '_' . uniqid() . '.' . $ext;
                $destPath = $uploadDir . $safeName;
                
                // Mover archivo
                if (move_uploaded_file($tmpName, $destPath)) {
                    // Guardar ruta relativa
                    $post_values['foto_url'] = '../assets/uploads/staff/' . $safeName;
                    $foto_url = '../assets/uploads/staff/' . $safeName;
                } else {
                    $errores_campos['foto'] = "Error al subir la imagen.";
                    $campos_validos['foto'] = false;
                }
            }
        }
    } elseif (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Error en la subida
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido.',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo solo se subió parcialmente.',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe directorio temporal.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida.'
        ];
        
        $errorMsg = $errorMessages[$_FILES['profile_file']['error']] ?? 'Error desconocido en la subida.';
        $errores_campos['foto'] = $errorMsg;
        $campos_validos['foto'] = false;
    }
    
    // Contador de campos guardados exitosamente
    $campos_guardados = 0;
    $errores_guardado = [];
    
    // SOLO continuar si tenemos un ID de staff
    if ($id_staff && empty($errores_campos)) {
        // ACTUALIZAR CAMPOS DEL STAFF UNO POR UNO
        // 1. Actualizar staff table (solo campos válidos y si son diferentes)
        $update_staff_fields = [];
        $update_staff_values = [];
        $update_types = "";
        
        if ($campos_validos['nombre'] && $nombre !== '' && valoresDiferentes($nombre, e($staff, 'Firstname'))) {
            $update_staff_fields[] = "Firstname = ?";
            $update_staff_values[] = $nombre;
            $update_types .= "s";
        }
        
        if ($campos_validos['apellido'] && $apellido !== '' && valoresDiferentes($apellido, e($staff, 'Lastname'))) {
            $update_staff_fields[] = "Lastname = ?";
            $update_staff_values[] = $apellido;
            $update_types .= "s";
        }
        
        if ($campos_validos['telefono'] && $telefono !== '' && valoresDiferentes($telefono, e($staff, 'Phone'))) {
            $update_staff_fields[] = "Phone = ?";
            $update_staff_values[] = $telefono;
            $update_types .= "s";
        }
        
        if ($campos_validos['correo'] && $correo !== '' && valoresDiferentes($correo, $correo_actual)) {
            $update_staff_fields[] = "Email = ?";
            $update_staff_values[] = $correo;
            $update_types .= "s";
        }
        
        // Agregar foto al update si existe
        if (isset($post_values['foto_url']) && $post_values['foto_url'] !== '' && 
            valoresDiferentes($post_values['foto_url'], e($staff, 'Profile_Image'))) {
            $update_staff_fields[] = "Profile_Image = ?";
            $update_staff_values[] = $post_values['foto_url'];
            $update_types .= "s";
        }
        
        // Solo actualizar si hay campos para actualizar
        if (!empty($update_staff_fields)) {
            $update_types .= "i"; // Para el ID
            $update_staff_values[] = $id_staff;
            
            $sql = "UPDATE staff SET " . implode(", ", $update_staff_fields) . " WHERE ID_Staff = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($update_types, ...$update_staff_values);
            if ($stmt->execute()) {
                $campos_guardados++;
                // Actualizar el correo actual si se cambió
                if ($correo !== '' && $correo !== $correo_actual) {
                    $correo_actual = $correo;
                }
            } else {
                $errores_guardado[] = "Error al actualizar staff: " . $stmt->error;
            }
            $stmt->close();
        }
        
        // 2. Actualizar usuario (user table)
        $update_user_fields = [];
        $update_user_values = [];
        $update_user_types = "";
        
        if ($campos_validos['usuario'] && $usuario !== '' && valoresDiferentes($usuario, $usuario_actual)) {
            $update_user_fields[] = "Username = ?";
            $update_user_values[] = $usuario;
            $update_user_types .= "s";
        }
        
        if ($campos_validos['contrasena'] && !empty($contrasena)) {
            $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
            $update_user_fields[] = "Password = ?";
            $update_user_values[] = $hashed_password;
            $update_user_types .= "s";
        }
        
        if (!empty($update_user_fields)) {
            $update_user_types .= "i"; // Para el ID
            $update_user_values[] = $usuario_id;
            
            $sql = "UPDATE user SET " . implode(", ", $update_user_fields) . " WHERE ID_User = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($update_user_types, ...$update_user_values);
            if ($stmt->execute()) {
                $campos_guardados++;
                // Actualizar el nombre de usuario en la sesión si se cambió
                if ($usuario !== '' && $usuario !== $usuario_actual) {
                    $_SESSION['usuario_nombre'] = $usuario;
                    $usuario_actual = $usuario;
                }
            } else {
                $errores_guardado[] = "Error al actualizar usuario: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // Verificar si el perfil está completo (basado solo en campos válidos)
    $perfil_completo = (
        $campos_validos['nombre'] && !empty($nombre) &&
        $campos_validos['apellido'] && !empty($apellido) &&
        $campos_validos['correo'] && !empty($correo) &&
        $campos_validos['telefono'] && !empty($telefono)
    );
    
    // ========== MOSTRAR MENSAJE APROPIADO ==========
    if (!empty($errores_campos)) {
        $tipo_modal = 'warning';
        $mensaje_modal = "Algunos campos tienen errores y no se guardaron. ";
        $mensaje_modal .= "Los campos correctos se guardaron exitosamente.";
        
        if ($campos_guardados > 0) {
            $mensaje_modal .= " ($campos_guardados campos guardados correctamente)";
        }
        
        if (isset($errores_campos['foto'])) {
            $mensaje_modal .= " Error en foto: " . $errores_campos['foto'];
        }
        
        $mostrar_modal = true;
    } else if (!empty($errores_guardado)) {
        $tipo_modal = 'error';
        $mensaje_modal = "Error al guardar algunos datos en la base de datos: " . implode(", ", $errores_guardado);
        $mostrar_modal = true;
    } else {
        // Todo OK - Verificar si realmente hubo cambios que se guardaron
        $tipo_modal = 'success';
        
        if ($campos_guardados > 0) {
            // Sí hubo cambios guardados
            $mensaje_modal = 'La información se guardó correctamente.';
            
            if ($perfil_completo) {
                $mensaje_modal .= ' ¡Tu perfil está completo!';
            } else {
                $mensaje_modal .= ' Aún necesitas completar todos los campos.';
            }
            
            // Recargar los datos actualizados
            $sql = "SELECT s.*, u.Username, u.registration_date 
                    FROM staff s 
                    JOIN user u ON s.FK_ID_User = u.ID_User 
                    WHERE s.FK_ID_User = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $staff = $row;
                $correo_actual = $row['Email'];
                $usuario_actual = $row['Username'];
            }
            $stmt->close();
        } else {
            // NO hubo cambios guardados (todo ya estaba actualizado)
            $mensaje_modal = 'No se detectaron cambios para guardar. ';
            
            if ($perfil_completo) {
                $mensaje_modal .= 'Tu perfil ya está completo.';
            } else {
                $mensaje_modal .= 'Recuerda completar todos los campos.';
            }
        }
        
        $mostrar_modal = true;
    }
}

/* ========== MENSAJE DE ÉXITO DESPUÉS DEL REDIRECT (GET) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['guardado']) && $_GET['guardado'] == '1') {
        $tipo_modal = 'success';
        $mensaje_modal = 'La información se guardó correctamente.';
        if (isset($_GET['completo']) && $_GET['completo'] == '1') {
            $mensaje_modal .= ' ¡Tu perfil está completo!';
        } else {
            $mensaje_modal .= ' Aún necesitas completar todos los campos.';
        }
        $mostrar_modal = true;
    }
}

/* ========== FOTO PERFIL ========== */
$foto_perfil = !empty($staff['Profile_Image']) ? e($staff, 'Profile_Image') : 'https://via.placeholder.com/140?text=Foto+Staff';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil del Staff</title>
    <link rel="stylesheet" href="../assets/Staff/PerfilStaff.css">
    <!-- Agregar Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Variables de colores azules */
        :root {
            --azul-principal: #2c6ae5;
            --azul-oscuro: #1a4fb3;
            --azul-claro: #e8f0fe;
            --azul-hover: #3a7cff;
            --gris-claro: #f8f9fa;
            --gris-medio: #e9ecef;
            --gris-oscuro: #6c757d;
            --blanco: #ffffff;
            --verde: #28a745;
            --rojo: #dc3545;
            --amarillo: #ffc107;
        }
        
        /* Estilos generales del cuerpo */
        body {
            background-color: #f5f7ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        /* Contenedor principal */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Layout de página */
        .page-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .page-layout {
                grid-template-columns: 1fr;
            }
        }
        
        /* Tarjetas */
        .ns-card {
            background: var(--blanco);
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(44, 106, 229, 0.12);
            border: 1px solid rgba(44, 106, 229, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .ns-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(44, 106, 229, 0.18);
        }
        
        .ns-card-profile {
            padding: 30px;
        }
        
        .ns-card-section {
            padding: 40px;
        }
        
        .ns-card-footer {
            padding: 25px;
            background: var(--gris-claro);
            border-top: 1px solid var(--gris-medio);
        }
        
        /* Perfil */
        .profile-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .profile-pic {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--azul-principal);
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(44, 106, 229, 0.3);
        }
        
        .profile-pic:hover {
            border-color: var(--azul-hover);
            transform: scale(1.03);
            box-shadow: 0 6px 18px rgba(44, 106, 229, 0.4);
        }
        
        .change-photo-btn {
            background: linear-gradient(135deg, var(--azul-principal), var(--azul-oscuro));
            color: var(--blanco);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(44, 106, 229, 0.3);
        }
        
        .change-photo-btn:hover {
            background: linear-gradient(135deg, var(--azul-hover), var(--azul-principal));
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(44, 106, 229, 0.4);
        }
        
        .change-photo-btn i {
            font-size: 14px;
        }
        
        /* Estado del perfil */
        .profile-status {
            padding: 15px;
            margin: 20px 0;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .profile-complete {
            background: linear-gradient(135deg, #d4f8e8, #b8f1d5);
            color: #0a5c36;
            border: 2px solid #28a745;
        }
        
        .profile-incomplete {
            background: linear-gradient(135deg, #fff5e6, #ffeccc);
            color: #856404;
            border: 2px solid var(--amarillo);
        }
        
        .profile-status i {
            font-size: 18px;
        }
        
        /* Contador de campos */
        .campos-restantes {
            background: linear-gradient(135deg, var(--azul-principal), var(--azul-oscuro));
            color: var(--blanco);
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 15px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 12px rgba(44, 106, 229, 0.3);
        }
        
        #completedCount {
            font-weight: 700;
            font-size: 18px;
        }
        
        #totalCount {
            font-weight: 600;
        }
        
        /* Datos del sistema */
        .system-data h3 {
            color: var(--azul-principal);
            font-size: 18px;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--azul-claro);
            font-weight: 600;
        }
        
        /* Título principal */
        .main-title {
            color: var(--azul-principal);
            font-size: 32px;
            margin-bottom: 35px;
            text-align: center;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .main-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--azul-principal), var(--azul-hover));
            border-radius: 2px;
        }
        
        /* Grid del formulario */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Secciones del formulario */
        .form-section {
            background: var(--blanco);
            padding: 30px;
            border-radius: 16px;
            border: 2px solid var(--azul-claro);
            box-shadow: 0 4px 15px rgba(44, 106, 229, 0.08);
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            border-color: var(--azul-principal);
            box-shadow: 0 6px 20px rgba(44, 106, 229, 0.15);
        }
        
        .form-section h3 {
            color: var(--azul-principal);
            font-size: 22px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--azul-claro);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h3 i {
            font-size: 20px;
        }
        
        /* Grupos de formulario */
        .form-group {
            margin-bottom: 22px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--azul-oscuro);
            font-size: 15px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gris-medio);
            border-radius: 10px;
            font-size: 15px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: var(--blanco);
            color: #333;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--azul-principal);
            box-shadow: 0 0 0 3px rgba(44, 106, 229, 0.2);
        }
        
        .form-group input:disabled {
            background: var(--gris-claro);
            cursor: not-allowed;
            border-color: var(--gris-medio);
            color: var(--gris-oscuro);
        }
        
        /* Wrapper de contraseña */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-wrapper input {
            flex: 1;
            padding-right: 50px;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 0;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--azul-principal);
            transition: color 0.3s ease;
            border-radius: 50%;
        }
        
        .toggle-password:hover {
            color: var(--azul-oscuro);
            background: var(--azul-claro);
        }
        
        /* Botón principal */
        .btn {
            display: block;
            width: 220px;
            margin: 40px auto;
            padding: 16px 30px;
            background: linear-gradient(135deg, var(--azul-principal), var(--azul-oscuro));
            color: var(--blanco);
            border: none;
            border-radius: 50px;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 18px rgba(44, 106, 229, 0.3);
            letter-spacing: 0.5px;
        }
        
        .btn:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--azul-hover), var(--azul-principal));
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(44, 106, 229, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--gris-oscuro);
            box-shadow: none;
        }
        
        .btn:active:not(:disabled) {
            transform: translateY(-1px);
        }
        
        /* Errores */
        .input-error {
            border: 2px solid var(--rojo) !important;
            background-color: #fff5f5;
        }
        
        .input-error:focus {
            border-color: var(--rojo) !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2) !important;
        }
        
        .error-message {
            color: var(--rojo);
            font-size: 13px;
            margin-top: 6px;
            display: block;
            font-weight: 500;
        }
        
        /* Modal */
        .ns-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: none;
        }
        
        .ns-modal.is-visible {
            display: block;
        }
        
        .ns-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .ns-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--blanco);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        .ns-modal-success {
            border: 3px solid var(--verde);
            background: linear-gradient(135deg, #f8fff8, #e8f8f0);
        }
        
        .ns-modal-warning {
            border: 3px solid var(--amarillo);
            background: linear-gradient(135deg, #fff8f0, #fff5e6);
        }
        
        .ns-modal-error {
            border: 3px solid var(--rojo);
            background: linear-gradient(135deg, #fff5f5, #ffe6e6);
        }
        
        .ns-modal-content p {
            margin: 0;
            font-size: 16px;
            line-height: 1.5;
            color: #333;
            font-weight: 500;
        }
        
        /* Iconos decorativos */
        .form-group i {
            color: var(--azul-principal);
            margin-right: 8px;
        }
        
        /* Placeholders */
        ::placeholder {
            color: var(--gris-oscuro);
            opacity: 0.7;
        }
        
        /* Input file oculto */
        input[type="file"] {
            display: none;
        }
        
        /* Animación para el botón de guardar cuando está cargando */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .btn-loading {
            animation: pulse 1.5s infinite;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuStaff.php'; ?>

    <!-- ===== MODAL ÚNICO (éxito / error) ===== -->
    <div id="nsModal" class="ns-modal <?php echo $mostrar_modal ? 'is-visible' : ''; ?>">
        <div class="ns-modal-backdrop"></div>
        <div class="ns-modal-content <?php echo $tipo_modal === 'error' ? 'ns-modal-error' : ($tipo_modal === 'warning' ? 'ns-modal-warning' : 'ns-modal-success'); ?>">
            <p><?php echo $mensaje_modal; ?></p>
        </div>
    </div>

    <!-- === CONTENIDO PRINCIPAL === -->
    <div class="container">
        <form method="post" action="" id="staffForm" enctype="multipart/form-data">
            <div class="page-layout">
                <!-- Columna izquierda -->
                <div class="side-column">
                    <div class="ns-card ns-card-profile">
                        <div class="profile-section">
                            <!-- Input file oculto -->
                            <input type="file" id="profile_file" name="profile_file" accept="image/*">
                            
                            <!-- Imagen de perfil -->
                            <img src="<?php echo $foto_perfil; ?>" alt="Foto del staff" class="profile-pic" id="profileImage">
                            
                            <!-- Botón para cambiar foto -->
                            <button type="button" class="change-photo-btn" id="changePhotoBtn">
                                <i class="fas fa-camera"></i> Cambiar foto
                            </button>
                            
                            <!-- Campo oculto para URL de foto -->
                            <input type="hidden" name="foto_url" id="foto_url" value="<?php echo e($staff, 'Profile_Image'); ?>">
                            
                            <?php if (isset($errores_campos['foto'])): ?>
                                <div style="color: var(--rojo); font-size: 0.9em; margin-top: 10px; padding: 8px; background: #fff5f5; border-radius: 8px; border: 1px solid var(--rojo);">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['foto']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Indicador de estado del perfil -->
                        <div class="profile-status <?php echo $perfil_completo ? 'profile-complete' : 'profile-incomplete'; ?>">
                            <i class="fas <?php echo $perfil_completo ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                            <?php if ($perfil_completo): ?>
                                Perfil completo
                            <?php else: ?>
                                Perfil incompleto
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contador de campos completados -->
                        <div id="camposCounter" class="campos-restantes">
                            Campos completados: <span id="completedCount">0</span>/<span id="totalCount">0</span>
                        </div>
                        
                        <!-- Datos del sistema -->
                        <div class="system-data">
                            <h3><i class="fas fa-database"></i> Datos del sistema</h3>
                            <div class="form-group">
                                <label><i class="fas fa-id-badge"></i> ID del Staff:</label>
                                <input type="text" value="<?php echo e($staff, 'ID_Staff'); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> ID del Usuario:</label>
                                <input type="text" value="<?php echo e($staff, 'FK_ID_User'); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-calendar-alt"></i> Registrado desde:</label>
                                <input type="text" value="<?php echo date('d/m/Y H:i', strtotime(e($staff, 'registration_date'))); ?>" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha -->
                <div class="main-column">
                    <div class="ns-card ns-card-section">
                        <h2 class="main-title">Perfil del Staff</h2>
                        <div class="form-grid">
                            <!-- Información personal -->
                            <div class="form-section">
                                <h3><i class="fas fa-user-circle"></i> Información personal</h3>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Nombre:</label>
                                    <input type="text" name="nombre" 
                                           value="<?php echo !empty($post_values) ? getValue('nombre', e($staff, 'Firstname')) : e($staff, 'Firstname'); ?>" 
                                           placeholder="<?php echo $errores_campos['nombre'] ?? 'Ingresa tu nombre (puede contener espacios)'; ?>" 
                                           class="<?php echo isset($errores_campos['nombre']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['nombre'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['nombre'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['nombre']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Apellido:</label>
                                    <input type="text" name="apellido" 
                                           value="<?php echo !empty($post_values) ? getValue('apellido', e($staff, 'Lastname')) : e($staff, 'Lastname'); ?>" 
                                           placeholder="<?php echo $errores_campos['apellido'] ?? 'Ingresa tu apellido (puede contener espacios)'; ?>" 
                                           class="<?php echo isset($errores_campos['apellido']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['apellido'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['apellido'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['apellido']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Teléfono:</label>
                                    <input type="tel" name="telefono" 
                                           value="<?php echo !empty($post_values) ? getValue('telefono', e($staff, 'Phone')) : e($staff, 'Phone'); ?>" 
                                           placeholder="<?php echo $errores_campos['telefono'] ?? 'Ej. 6641740936'; ?>" 
                                           class="<?php echo isset($errores_campos['telefono']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['telefono'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['telefono'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['telefono']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Datos de acceso -->
                            <div class="form-section">
                                <h3><i class="fas fa-key"></i> Datos de acceso</h3>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Correo electrónico:</label>
                                    <input type="email" name="correo" 
                                           value="<?php echo !empty($post_values) ? getValue('correo', e($staff, 'Email')) : e($staff, 'Email'); ?>" 
                                           placeholder="correo@ejemplo.com" 
                                           class="<?php echo isset($errores_campos['correo']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['correo'] ?? 'Debe ser único y no estar registrado por otro usuario'; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['correo'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['correo']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user-tag"></i> Usuario:</label>
                                    <input type="text" name="usuario" 
                                           value="<?php echo !empty($post_values) ? getValue('usuario', e($staff, 'Username')) : e($staff, 'Username'); ?>" 
                                           placeholder="<?php echo $errores_campos['usuario'] ?? 'Nombre de usuario'; ?>" 
                                           class="<?php echo isset($errores_campos['usuario']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['usuario'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['usuario'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['usuario']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Nueva contraseña (dejar en blanco para no cambiar):</label>
                                    <div class="password-wrapper">
                                        <input type="password" name="contrasena" id="contrasena" 
                                               placeholder="<?php echo $errores_campos['contrasena'] ?? 'Mínimo 6 caracteres'; ?>" 
                                               class="<?php echo isset($errores_campos['contrasena']) ? 'input-error' : ''; ?>" 
                                               title="<?php echo $errores_campos['contrasena'] ?? ''; ?>">
                                        <button type="button" class="toggle-password" onclick="togglePassword('contrasena', this)" 
                                                aria-label="Mostrar/ocultar contraseña">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errores_campos['contrasena'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['contrasena']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Confirmar contraseña:</label>
                                    <div class="password-wrapper">
                                        <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" 
                                               placeholder="<?php echo $errores_campos['confirmar_contrasena'] ?? 'Repite la contraseña'; ?>" 
                                               class="<?php echo isset($errores_campos['confirmar_contrasena']) ? 'input-error' : ''; ?>" 
                                               title="<?php echo $errores_campos['confirmar_contrasena'] ?? ''; ?>">
                                        <button type="button" class="toggle-password" onclick="togglePassword('confirmar_contrasena', this)"
                                                aria-label="Mostrar/ocultar contraseña">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                    <?php if (isset($errores_campos['confirmar_contrasena'])): ?>
                                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $errores_campos['confirmar_contrasena']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Botón guardar -->
                        <div class="ns-card-footer">
                            <button type="submit" class="btn" id="saveBtn" disabled>
                                <i class="fas fa-save"></i> Guardar cambios
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('nsModal');
            if (!modal) return;
            
            // Si el modal está visible, lo ocultamos después de 3s
            if (modal.classList.contains('is-visible')) {
                setTimeout(function () {
                    modal.classList.remove('is-visible');
                    // Quitar parámetros de la URL si existen
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('guardado');
                        url.searchParams.delete('completo');
                        window.history.replaceState({}, document.title, url.toString());
                    }
                }, 3000);
            }
            
            // Botón y elementos para cambiar foto
            const changeBtn = document.getElementById('changePhotoBtn');
            const fileInput = document.getElementById('profile_file');
            const profileImage = document.getElementById('profileImage');
            const fotoUrlInput = document.getElementById('foto_url');
            
            if (changeBtn && fileInput && profileImage) {
                // Hacer clic en el botón abre el explorador de archivos
                changeBtn.addEventListener('click', function () {
                    fileInput.click();
                });
                
                // También hacer clic en la imagen abre el explorador
                profileImage.addEventListener('click', function () {
                    fileInput.click();
                });
                
                // Cuando se selecciona un archivo
                fileInput.addEventListener('change', function (e) {
                    if (fileInput.files && fileInput.files[0]) {
                        const file = fileInput.files[0];
                        
                        // Validar tamaño (máximo 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('La imagen es demasiado grande. Máximo 5MB.');
                            fileInput.value = '';
                            return;
                        }
                        
                        // Validar tipo
                        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!validTypes.includes(file.type)) {
                            alert('Formato no válido. Use JPG, PNG, GIF o WebP.');
                            fileInput.value = '';
                            return;
                        }
                        
                        // Mostrar preview inmediatamente
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            profileImage.src = e.target.result;
                            // Actualizar campo oculto con nombre del archivo
                            fotoUrlInput.value = file.name;
                            
                            // Habilitar el botón de guardar
                            document.getElementById('saveBtn').disabled = false;
                            
                            // Añadir animación sutil a la imagen
                            profileImage.style.transform = 'scale(1.05)';
                            setTimeout(() => {
                                profileImage.style.transform = 'scale(1)';
                            }, 300);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Función para mostrar/ocultar contraseña
            window.togglePassword = function(fieldId, button) {
                const field = document.getElementById(fieldId);
                const icon = button.querySelector('i');
                
                if (!field || !icon) return;
                
                if (field.type === "password") {
                    field.type = "text";
                    icon.className = "fa-solid fa-eye-slash";
                    button.setAttribute('aria-label', 'Ocultar contraseña');
                } else {
                    field.type = "password";
                    icon.className = "fa-solid fa-eye";
                    button.setAttribute('aria-label', 'Mostrar contraseña');
                }
            };
            
            // Verificar si todos los campos están llenos
            const requiredInputs = document.querySelectorAll('input[data-required="true"]');
            const saveBtn = document.getElementById('saveBtn');
            const completedCountSpan = document.getElementById('completedCount');
            const totalCountSpan = document.getElementById('totalCount');
            
            // Establecer el total de campos
            totalCountSpan.textContent = requiredInputs.length;
            
            // Función para verificar si todos los campos están llenos
            function checkAllFieldsFilled() {
                let filledCount = 0;
                
                requiredInputs.forEach(input => {
                    if (input.value.trim() !== '') {
                        filledCount++;
                    }
                });
                
                // Actualizar contador
                completedCountSpan.textContent = filledCount;
                
                // Actualizar color del contador basado en el progreso
                const progress = filledCount / requiredInputs.length;
                const camposCounter = document.getElementById('camposCounter');
                if (camposCounter) {
                    if (progress === 1) {
                        camposCounter.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    } else if (progress >= 0.5) {
                        camposCounter.style.background = 'linear-gradient(135deg, #ffc107, #fd7e14)';
                    } else {
                        camposCounter.style.background = 'linear-gradient(135deg, #2c6ae5, #1a4fb3)';
                    }
                }
                
                // Habilitar o deshabilitar el botón
                saveBtn.disabled = !(filledCount === requiredInputs.length);
            }
            
            // Verificar campos al cargar la página
            checkAllFieldsFilled();
            
            // Agregar event listeners a todos los campos obligatorios
            requiredInputs.forEach(input => {
                input.addEventListener('input', checkAllFieldsFilled);
                input.addEventListener('change', checkAllFieldsFilled);
            });
            
            // Validar contraseñas en tiempo real
            const contrasenaInput = document.getElementById('contrasena');
            const confirmarInput = document.getElementById('confirmar_contrasena');
            
            function validatePasswords() {
                const contrasena = contrasenaInput.value;
                const confirmar = confirmarInput.value;
                
                if (contrasena === '' && confirmar === '') {
                    // Ambos vacíos, no hay error
                    contrasenaInput.classList.remove('input-error');
                    confirmarInput.classList.remove('input-error');
                    return;
                }
                
                if (contrasena.length > 0 && contrasena.length < 6) {
                    contrasenaInput.classList.add('input-error');
                    contrasenaInput.title = "La contraseña debe tener al menos 6 caracteres";
                } else if (contrasena !== confirmar) {
                    contrasenaInput.classList.add('input-error');
                    confirmarInput.classList.add('input-error');
                    contrasenaInput.title = "Las contraseñas no coinciden";
                    confirmarInput.title = "Las contraseñas no coinciden";
                } else {
                    contrasenaInput.classList.remove('input-error');
                    confirmarInput.classList.remove('input-error');
                    contrasenaInput.title = "";
                    confirmarInput.title = "";
                }
            }
            
            if (contrasenaInput && confirmarInput) {
                contrasenaInput.addEventListener('input', validatePasswords);
                confirmarInput.addEventListener('input', validatePasswords);
            }
            
            // Validar formulario antes de enviar
            const form = document.getElementById('staffForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const contrasena = document.getElementById('contrasena').value;
                    const confirmar = document.getElementById('confirmar_contrasena').value;
                    
                    if (contrasena !== '' && contrasena !== confirmar) {
                        e.preventDefault();
                        alert('Las contraseñas no coinciden. Por favor, verifica.');
                        return false;
                    }
                    
                    // Mostrar mensaje de carga
                    const saveBtn = document.getElementById('saveBtn');
                    if (saveBtn) {
                        const originalText = saveBtn.innerHTML;
                        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                        saveBtn.classList.add('btn-loading');
                        saveBtn.disabled = true;
                        
                        // Restaurar después de 5 segundos (en caso de que algo falle)
                        setTimeout(() => {
                            saveBtn.innerHTML = originalText;
                            saveBtn.classList.remove('btn-loading');
                            saveBtn.disabled = false;
                        }, 5000);
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>