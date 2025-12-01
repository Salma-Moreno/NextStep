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
$alumno = [];
$detalles = [];
$tutor = [];
$direccion = [];
$id_estudiante = null;
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

/* ================= OBTENER ESTUDIANTE ================= */
$sql = "SELECT * FROM student WHERE FK_ID_User = ? LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $alumno = $row;
    $id_estudiante = (int)$row['ID_Student'];
    $stmt->close();
} else {
    $stmt->close();
    // ERROR: Si está logueado como Student pero no tiene perfil, es un error del sistema
    die("Error: No se encontró tu perfil de estudiante. Esto no debería pasar. Contacta al administrador.");
    // O alternativamente: header('Location: error.php?codigo=perfil_no_encontrado');
}

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

/* ================= VERIFICAR SI EL PERFIL ESTÁ COMPLETO ================= */
$perfil_completo = false;
if ($id_estudiante) {
    // Verificar que todas las tablas tengan datos esenciales
    $perfil_completo = (
        !empty($alumno['Name']) &&
        !empty($alumno['Last_Name']) &&
        !empty($alumno['Email_Address']) &&
        !empty($detalles['Birthdate']) &&
        !empty($detalles['High_school']) &&
        !empty($detalles['Grade']) &&
        !empty($tutor['Tutor_name']) &&
        !empty($tutor['Tutor_lastname']) &&
        !empty($tutor['Phone_Number']) &&
        !empty($direccion['Street']) &&
        !empty($direccion['City']) &&
        !empty($direccion['Postal_Code'])
    );
}

/* ================= MENSAJES / MODAL ================= */
$mostrar_modal = false;
$mensaje_modal = '';
$tipo_modal = ''; // 'success', 'warning' o 'error'

/* ================= PROCESAR FORMULARIO (POST) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Datos del estudiante ---
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $correo = trim($_POST['correo'] ?? '');

    // Guardar valores del POST
    $post_values['nombre'] = $nombre;
    $post_values['apellido'] = $apellido;
    $post_values['telefono'] = $telefono;
    $post_values['correo'] = $correo;

    // ========== FOTO DE PERFIL ==========
    $foto_url = trim($_POST['foto_url'] ?? '');
    $post_values['foto_url'] = $foto_url;
    
    if (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $tmpName = $_FILES['profile_file']['tmp_name'];
        $originalName = basename($_FILES['profile_file']['name']);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = uniqid('profile_') . '.' . $ext;
        $destPath = $uploadDir . $safeName;
        
        if (move_uploaded_file($tmpName, $destPath)) {
            $foto_url = '../assets/uploads/' . $safeName;
            $post_values['foto_url'] = $foto_url;
        }
    }
    
    if ($foto_url === '') {
        $foto_url = null;
    }

    // --- Datos académicos ---
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    if ($fecha_nacimiento === '') $fecha_nacimiento = null;
    $preparatoria = trim($_POST['preparatoria'] ?? '');
    $grado = trim($_POST['grado'] ?? '');
    $licencia = trim($_POST['licencia'] ?? '');
    $promedio = $_POST['promedio'] === '' ? null : (int)$_POST['promedio'];

    $post_values['fecha_nacimiento'] = $fecha_nacimiento;
    $post_values['preparatoria'] = $preparatoria;
    $post_values['grado'] = $grado;
    $post_values['licencia'] = $licencia;
    $post_values['promedio'] = $promedio;

    // --- Datos del tutor ---
    $tutor_nombre = trim($_POST['tutor_nombre'] ?? '');
    $tutor_apellidos = trim($_POST['tutor_apellidos'] ?? '');
    $tutor_telefono = trim($_POST['tutor_telefono'] ?? '');
    $tutor_direccion = trim($_POST['tutor_direccion'] ?? '');

    $post_values['tutor_nombre'] = $tutor_nombre;
    $post_values['tutor_apellidos'] = $tutor_apellidos;
    $post_values['tutor_telefono'] = $tutor_telefono;
    $post_values['tutor_direccion'] = $tutor_direccion;

    // --- Domicilio ---
    $calle = trim($_POST['calle'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');

    $post_values['calle'] = $calle;
    $post_values['ciudad'] = $ciudad;
    $post_values['codigo_postal'] = $codigo_postal;

    /* ---------- VALIDACIONES ---------- */
    // Array para trackear qué campos son válidos
    $campos_validos = [
        'nombre' => true,
        'apellido' => true,
        'telefono' => true,
        'correo' => true,
        'fecha_nacimiento' => true,
        'preparatoria' => true,
        'grado' => true,
        'promedio' => true,
        'licencia' => true,
        'tutor_nombre' => true,
        'tutor_apellidos' => true,
        'tutor_telefono' => true,
        'tutor_direccion' => true,
        'calle' => true,
        'ciudad' => true,
        'codigo_postal' => true
    ];
    
    // Validación individual de cada campo
    // Nombre
    if (!preg_match('/^[\p{L}]+$/u', $nombre)) {
        $errores_campos['nombre'] = "Sólo letras, sin espacios ni números.";
        $campos_validos['nombre'] = false;
    }
    
    // Apellido
    if (!preg_match('/^[\p{L}]+$/u', $apellido)) {
        $errores_campos['apellido'] = "Sólo letras, sin espacios ni números.";
        $campos_validos['apellido'] = false;
    }
    
    // Teléfono
    if (!preg_match('/^\d{10}$/', $telefono)) {
        $errores_campos['telefono'] = "Debe contener exactamente 10 números, sin espacios.";
        $campos_validos['telefono'] = false;
    }
    
    // Correo
    if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores_campos['correo'] = "Correo inválido.";
        $campos_validos['correo'] = false;
    }
    
    // Fecha nacimiento
    if ($fecha_nacimiento) {
        $edad = (int)((time() - strtotime($fecha_nacimiento)) / (365.25*24*60*60));
        if ($edad < 14) {
            $errores_campos['fecha_nacimiento'] = "Debes tener al menos 14 años.";
            $campos_validos['fecha_nacimiento'] = false;
        }
    }
    
    // Preparatoria
    if ($preparatoria && !preg_match('/^[\p{L}\d\s]+$/u', $preparatoria)) {
        $errores_campos['preparatoria'] = "Sólo letras, números y espacios.";
        $campos_validos['preparatoria'] = false;
    }
    
    // Grado
    if ($grado && !preg_match('/^\d+°?$/u', $grado)) {
        $errores_campos['grado'] = "Sólo números o número con símbolo °.";
        $campos_validos['grado'] = false;
    }
    
    // Promedio
    if ($promedio !== null && !is_numeric($promedio)) {
        $errores_campos['promedio'] = "Número entero válido.";
        $campos_validos['promedio'] = false;
    }
    
    // Licencia
    if ($licencia && !preg_match('/^[\p{L}\d-]+$/u', $licencia)) {
        $errores_campos['licencia'] = "Sólo letras, números o guion.";
        $campos_validos['licencia'] = false;
    }
    
    // Nombre tutor
    if ($tutor_nombre && !preg_match('/^[\p{L}]+$/u', $tutor_nombre)) {
        $errores_campos['tutor_nombre'] = "Sólo letras, sin espacios.";
        $campos_validos['tutor_nombre'] = false;
    }
    
    // Apellidos tutor
    if ($tutor_apellidos && !preg_match('/^[\p{L}\s]+$/u', $tutor_apellidos)) {
        $errores_campos['tutor_apellidos'] = "Sólo letras y espacios.";
        $campos_validos['tutor_apellidos'] = false;
    }
    
    // Teléfono tutor
    if ($tutor_telefono && !preg_match('/^\d{10}$/', $tutor_telefono)) {
        $errores_campos['tutor_telefono'] = "Debe contener exactamente 10 números, sin espacios.";
        $campos_validos['tutor_telefono'] = false;
    }
    
    // Dirección tutor
    if ($tutor_direccion && !preg_match('/^[\p{L}\d#\s]+$/u', $tutor_direccion)) {
        $errores_campos['tutor_direccion'] = "Sólo letras, números y #.";
        $campos_validos['tutor_direccion'] = false;
    }
    
    // Calle
    if ($calle && !preg_match('/^[\p{L}\d#\s]+$/u', $calle)) {
        $errores_campos['calle'] = "Sólo letras, números y #.";
        $campos_validos['calle'] = false;
    }
    
    // Ciudad
    if ($ciudad && !preg_match('/^[\p{L}\s]+$/u', $ciudad)) {
        $errores_campos['ciudad'] = "Sólo letras.";
        $campos_validos['ciudad'] = false;
    }
    
    // Código postal
    if ($codigo_postal && (!preg_match('/^\d{5}$/', $codigo_postal) || (intval($codigo_postal) < 22000 || intval($codigo_postal) > 22599))) {
        $errores_campos['codigo_postal'] = "Debe ser un número de 5 dígitos válido de Tijuana (22000-22599).";
        $campos_validos['codigo_postal'] = false;
    }
    
    // Verificar si se subió una foto nueva
    if (!empty($foto_url) && $foto_url !== $post_values['foto_url']) {
        $foto_perfil = $foto_url;
    }
    
    // Contador de campos guardados exitosamente
    $campos_guardados = 0;
    $errores_guardado = [];
    
    // SOLO continuar si tenemos un ID de estudiante (SIEMPRE deberíamos tenerlo)
    if ($id_estudiante) {
        // ACTUALIZAR CAMPOS DEL ESTUDIANTE UNO POR UNO
        // 1. Actualizar student table (solo campos válidos)
        $update_student_fields = [];
        $update_student_values = [];
        $update_types = "";
        
        if ($campos_validos['nombre'] && $nombre !== '') {
            $update_student_fields[] = "Name = ?";
            $update_student_values[] = $nombre;
            $update_types .= "s";
        }
        
        if ($campos_validos['apellido'] && $apellido !== '') {
            $update_student_fields[] = "Last_Name = ?";
            $update_student_values[] = $apellido;
            $update_types .= "s";
        }
        
        if ($campos_validos['telefono'] && $telefono !== '') {
            $update_student_fields[] = "Phone_Number = ?";
            $update_student_values[] = $telefono;
            $update_types .= "s";
        }
        
        if ($campos_validos['correo'] && $correo !== '') {
            $update_student_fields[] = "Email_Address = ?";
            $update_student_values[] = $correo;
            $update_types .= "s";
        }
        
        if ($foto_url !== null) {
            $update_student_fields[] = "Profile_Image = ?";
            $update_student_values[] = $foto_url;
            $update_types .= "s";
        }
        
        // Solo actualizar si hay campos para actualizar
        if (!empty($update_student_fields)) {
            $update_types .= "i"; // Para el ID
            $update_student_values[] = $id_estudiante;
            
            $sql = "UPDATE student SET " . implode(", ", $update_student_fields) . " WHERE ID_Student = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param($update_types, ...$update_student_values);
            if ($stmt->execute()) {
                $campos_guardados++;
            } else {
                $errores_guardado[] = "Error al actualizar estudiante: " . $stmt->error;
            }
            $stmt->close();
        }
        
        // 2. student_details (insertar o actualizar)
        $id_detalles = null;
        $sql = "SELECT ID_Details FROM student_details WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $stmt->bind_result($id_detalles);
        $stmt->fetch();
        $stmt->close();
        
        if ($id_detalles) {
            // Actualizar solo campos válidos
            $update_details_fields = [];
            $update_details_values = [];
            $update_details_types = "";
            
            if ($campos_validos['fecha_nacimiento'] && $fecha_nacimiento !== null) {
                $update_details_fields[] = "Birthdate = ?";
                $update_details_values[] = $fecha_nacimiento;
                $update_details_types .= "s";
            }
            
            if ($campos_validos['preparatoria'] && $preparatoria !== '') {
                $update_details_fields[] = "High_school = ?";
                $update_details_values[] = $preparatoria;
                $update_details_types .= "s";
            }
            
            if ($campos_validos['grado'] && $grado !== '') {
                $update_details_fields[] = "Grade = ?";
                $update_details_values[] = $grado;
                $update_details_types .= "s";
            }
            
            if ($campos_validos['licencia'] && $licencia !== '') {
                $update_details_fields[] = "License = ?";
                $update_details_values[] = $licencia;
                $update_details_types .= "s";
            }
            
            if ($campos_validos['promedio'] && $promedio !== null) {
                $update_details_fields[] = "Average = ?";
                $update_details_values[] = $promedio;
                $update_details_types .= "i";
            }
            
            if (!empty($update_details_fields)) {
                $update_details_types .= "i"; // Para el ID
                $update_details_values[] = $id_detalles;
                
                $sql = "UPDATE student_details SET " . implode(", ", $update_details_fields) . " WHERE ID_Details = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param($update_details_types, ...$update_details_values);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al actualizar detalles: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // Insertar solo si tenemos datos válidos
            $tiene_datos_detalles = ($fecha_nacimiento !== null || $preparatoria !== '' || $grado !== '' || $licencia !== '' || $promedio !== null);
            
            if ($tiene_datos_detalles) {
                // Usar valores válidos o null
                $fecha_ins = $campos_validos['fecha_nacimiento'] ? $fecha_nacimiento : null;
                $prep_ins = $campos_validos['preparatoria'] ? $preparatoria : '';
                $grado_ins = $campos_validos['grado'] ? $grado : '';
                $licencia_ins = $campos_validos['licencia'] ? $licencia : '';
                $promedio_ins = $campos_validos['promedio'] ? $promedio : null;
                
                $sql = "INSERT INTO student_details (FK_ID_Student, Birthdate, High_school, Grade, License, Average) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("issssi", $id_estudiante, $fecha_ins, $prep_ins, $grado_ins, $licencia_ins, $promedio_ins);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al insertar detalles: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // 3. tutor_data (insertar o actualizar)
        $id_tutor = null;
        $sql = "SELECT ID_Data FROM tutor_data WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $stmt->bind_result($id_tutor);
        $stmt->fetch();
        $stmt->close();
        
        $fk_dependency = 1; // Valor por defecto
        
        if ($id_tutor) {
            // Actualizar solo campos válidos
            $update_tutor_fields = [];
            $update_tutor_values = [];
            $update_tutor_types = "";
            
            if ($campos_validos['tutor_nombre'] && $tutor_nombre !== '') {
                $update_tutor_fields[] = "Tutor_name = ?";
                $update_tutor_values[] = $tutor_nombre;
                $update_tutor_types .= "s";
            }
            
            if ($campos_validos['tutor_apellidos'] && $tutor_apellidos !== '') {
                $update_tutor_fields[] = "Tutor_lastname = ?";
                $update_tutor_values[] = $tutor_apellidos;
                $update_tutor_types .= "s";
            }
            
            if ($campos_validos['tutor_telefono'] && $tutor_telefono !== '') {
                $update_tutor_fields[] = "Phone_Number = ?";
                $update_tutor_values[] = $tutor_telefono;
                $update_tutor_types .= "s";
            }
            
            if ($campos_validos['tutor_direccion'] && $tutor_direccion !== '') {
                $update_tutor_fields[] = "Address = ?";
                $update_tutor_values[] = $tutor_direccion;
                $update_tutor_types .= "s";
            }
            
            if (!empty($update_tutor_fields)) {
                $update_tutor_types .= "i"; // Para el ID
                $update_tutor_values[] = $id_tutor;
                
                $sql = "UPDATE tutor_data SET " . implode(", ", $update_tutor_fields) . " WHERE ID_Data = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param($update_tutor_types, ...$update_tutor_values);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al actualizar tutor: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // Insertar solo si tenemos datos válidos
            $tiene_datos_tutor = ($tutor_nombre !== '' || $tutor_apellidos !== '' || $tutor_telefono !== '' || $tutor_direccion !== '');
            
            if ($tiene_datos_tutor) {
                // Usar valores válidos o vacíos
                $tutor_nombre_ins = $campos_validos['tutor_nombre'] ? $tutor_nombre : '';
                $tutor_apellidos_ins = $campos_validos['tutor_apellidos'] ? $tutor_apellidos : '';
                $tutor_telefono_ins = $campos_validos['tutor_telefono'] ? $tutor_telefono : '';
                $tutor_direccion_ins = $campos_validos['tutor_direccion'] ? $tutor_direccion : '';
                
                $sql = "INSERT INTO tutor_data (FK_ID_Student, FK_ID_Dependency, Tutor_name, Tutor_lastname, Phone_Number, Address) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("iissss", $id_estudiante, $fk_dependency, $tutor_nombre_ins, $tutor_apellidos_ins, $tutor_telefono_ins, $tutor_direccion_ins);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al insertar tutor: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // 4. address (insertar o actualizar)
        $id_address = null;
        $sql = "SELECT ID_Address FROM address WHERE FK_ID_Student = ? LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id_estudiante);
        $stmt->execute();
        $stmt->bind_result($id_address);
        $stmt->fetch();
        $stmt->close();
        
        if ($id_address) {
            // Actualizar solo campos válidos
            $update_address_fields = [];
            $update_address_values = [];
            $update_address_types = "";
            
            if ($campos_validos['calle'] && $calle !== '') {
                $update_address_fields[] = "Street = ?";
                $update_address_values[] = $calle;
                $update_address_types .= "s";
            }
            
            if ($campos_validos['ciudad'] && $ciudad !== '') {
                $update_address_fields[] = "City = ?";
                $update_address_values[] = $ciudad;
                $update_address_types .= "s";
            }
            
            if ($campos_validos['codigo_postal'] && $codigo_postal !== '') {
                $update_address_fields[] = "Postal_Code = ?";
                $update_address_values[] = $codigo_postal;
                $update_address_types .= "s";
            }
            
            if (!empty($update_address_fields)) {
                $update_address_types .= "i"; // Para el ID
                $update_address_values[] = $id_address;
                
                $sql = "UPDATE address SET " . implode(", ", $update_address_fields) . " WHERE ID_Address = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param($update_address_types, ...$update_address_values);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al actualizar dirección: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            // Insertar solo si tenemos datos válidos
            $tiene_datos_direccion = ($calle !== '' || $ciudad !== '' || $codigo_postal !== '');
            
            if ($tiene_datos_direccion) {
                // Usar valores válidos o vacíos
                $calle_ins = $campos_validos['calle'] ? $calle : '';
                $ciudad_ins = $campos_validos['ciudad'] ? $ciudad : '';
                $codigo_ins = $campos_validos['codigo_postal'] ? $codigo_postal : '';
                
                $sql = "INSERT INTO address (FK_ID_Student, Street, City, Postal_Code) VALUES (?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("isss", $id_estudiante, $calle_ins, $ciudad_ins, $codigo_ins);
                if ($stmt->execute()) {
                    $campos_guardados++;
                } else {
                    $errores_guardado[] = "Error al insertar dirección: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
    
    // Verificar si el perfil está completo (basado solo en campos válidos)
    $perfil_completo = (
        $campos_validos['nombre'] && !empty($nombre) &&
        $campos_validos['apellido'] && !empty($apellido) &&
        $campos_validos['correo'] && !empty($correo) &&
        $campos_validos['fecha_nacimiento'] && !empty($fecha_nacimiento) &&
        $campos_validos['preparatoria'] && !empty($preparatoria) &&
        $campos_validos['grado'] && !empty($grado) &&
        $campos_validos['tutor_nombre'] && !empty($tutor_nombre) &&
        $campos_validos['tutor_apellidos'] && !empty($tutor_apellidos) &&
        $campos_validos['tutor_telefono'] && !empty($tutor_telefono) &&
        $campos_validos['calle'] && !empty($calle) &&
        $campos_validos['ciudad'] && !empty($ciudad) &&
        $campos_validos['codigo_postal'] && !empty($codigo_postal)
    );
    
    // Mostrar mensaje apropiado
    if (!empty($errores_campos)) {
        $tipo_modal = 'warning';
        $mensaje_modal = "Algunos campos tienen errores y no se guardaron. ";
        $mensaje_modal .= "Los campos correctos se guardaron exitosamente.";
        
        if ($campos_guardados > 0) {
            $mensaje_modal .= " ($campos_guardados campos guardados correctamente)";
        }
        
        $mostrar_modal = true;
    } else if (!empty($errores_guardado)) {
        $tipo_modal = 'error';
        $mensaje_modal = "Error al guardar algunos datos en la base de datos: " . implode(", ", $errores_guardado);
        $mostrar_modal = true;
    } else {
        // Todo OK
        $tipo_modal = 'success';
        $mensaje_modal = 'La información se guardó correctamente.';
        
        if ($perfil_completo) {
            $mensaje_modal .= ' ¡Tu perfil está completo! Ya puedes aplicar a becas.';
        } else {
            $mensaje_modal .= ' Aún necesitas completar todos los campos para aplicar a becas.';
        }
        
        $mostrar_modal = true;
        
        // Recargar los datos actualizados
        if ($id_estudiante) {
            // Recargar datos del estudiante
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
            
            // Recargar detalles
            $sql = "SELECT * FROM student_details WHERE FK_ID_Student = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_estudiante);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $detalles = $row;
            }
            $stmt->close();
            
            // Recargar tutor
            $sql = "SELECT * FROM tutor_data WHERE FK_ID_Student = ? LIMIT 1";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_estudiante);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $tutor = $row;
            }
            $stmt->close();
            
            // Recargar dirección
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
}

/* ========== MENSAJE DE ÉXITO DESPUÉS DEL REDIRECT (GET) ========== */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['guardado']) && $_GET['guardado'] == '1') {
        $tipo_modal = 'success';
        $mensaje_modal = 'La información se guardó correctamente.';
        // Si el perfil ahora está completo, mostrar mensaje adicional
        if (isset($_GET['completo']) && $_GET['completo'] == '1') {
            $mensaje_modal .= ' ¡Tu perfil está completo! Ya puedes aplicar a becas.';
        } else {
            $mensaje_modal .= ' Aún necesitas completar todos los campos para aplicar a becas.';
        }
        $mostrar_modal = true;
    }
    // Si viene de application.php porque necesita completar perfil
    if (isset($_GET['completa']) && $_GET['completa'] == '1') {
        $tipo_modal = 'warning';
        $mensaje_modal = 'Debes completar tu perfil antes de aplicar a una beca.';
        $mostrar_modal = true;
    }
}

/* ========== FOTO PERFIL ========== */
$foto_perfil = !empty($alumno['Profile_Image']) ? e($alumno, 'Profile_Image') : 'https://via.placeholder.com/140?text=Foto+del+estudiante';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Personales - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/viewStudent.css">
    <style>
        /* Borde rojo y mensaje dentro del campo */
        .input-error {
            border: 2px solid red !important;
        }
        .input-error::placeholder {
            color: red !important;
        }
        
        /* Indicador de perfil completo */
       .profile-status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .profile-complete {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .profile-incomplete {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        /* Botón deshabilitado */
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
            background-color: #ccc !important;
        }
        
        /* Contador de campos */
        .campos-restantes {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-top: 10px;
            display: inline-block;
        }
        
        /* Mensaje de error debajo del campo */
        .error-message {
            color: red;
            font-size: 0.8em;
            margin-top: 3px;
            display: block;
        }
        
        /* Tooltip para errores */
        .error-tooltip {
            position: absolute;
            background: #ff4444 !important;
            color: white !important;
            padding: 5px 10px !important;
            border-radius: 4px !important;
            font-size: 0.8em !important;
            z-index: 10000 !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important;
            pointer-events: none !important;
            max-width: 300px;
            white-space: normal;
        }
        
        /* Para campos con error en focus */
        .input-error:focus {
            border-color: #ff4444 !important;
            box-shadow: 0 0 0 2px rgba(255, 68, 68, 0.2) !important;
        }
        
        /* Estilo para mensaje modal warning */
        .ns-modal-warning .ns-modal-content {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        /* Para mantener el layout */
        .form-grid {
            display: grid;
            gap: 20px;
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuE.php'; ?>

    <!-- ===== MODAL ÚNICO (éxito / error) ===== -->
    <div id="nsModal" class="ns-modal <?php echo $mostrar_modal ? 'is-visible' : ''; ?>">
        <div class="ns-modal-backdrop"></div>
        <div class="ns-modal-content <?php echo $tipo_modal === 'error' ? 'ns-modal-error' : ($tipo_modal === 'warning' ? 'ns-modal-warning' : 'ns-modal-success'); ?>">
            <p><?php echo $mensaje_modal; ?></p>
        </div>
    </div>

    <!-- === CONTENIDO PRINCIPAL === -->
    <div class="container">
        <form method="post" action="" enctype="multipart/form-data" id="studentForm">
            <div class="page-layout">
                <!-- Columna izquierda -->
                <div class="side-column">
                    <div class="ns-card ns-card-profile">
                        <div class="profile-section">
                            <img src="<?php echo $foto_perfil; ?>" alt="Foto del estudiante" class="profile-pic">
                            <!-- input file oculto -->
                            <input type="file" id="profile_file" name="profile_file" accept="image/*" style="display:none">
                            <button type="button" class="change-photo-btn" id="changePhotoBtn">
                                Cambiar foto
                            </button>
                        </div>
                        
                        <!-- Indicador de estado del perfil -->
                        <div class="profile-status <?php echo $perfil_completo ? 'profile-complete' : 'profile-incomplete'; ?>">
                            <?php if ($perfil_completo): ?>
                                ✅ Perfil completo - Puedes aplicar a becas
                            <?php else: ?>
                                ⚠️ Perfil incompleto - Completa todos los campos para aplicar a becas
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contador de campos completados -->
                        <div id="camposCounter" class="campos-restantes">
                            Campos completados: <span id="completedCount">0</span>/<span id="totalCount">0</span>
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
                                    <input type="text" name="nombre" value="<?php echo !empty($post_values) ? getValue('nombre', e($alumno, 'Name')) : e($alumno, 'Name'); ?>" 
                                           placeholder="<?php echo $errores_campos['nombre'] ?? 'Nombre'; ?>" 
                                           class="<?php echo isset($errores_campos['nombre']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['nombre'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['nombre'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['nombre']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Apellido:</label>
                                    <input type="text" name="apellido" value="<?php echo !empty($post_values) ? getValue('apellido', e($alumno, 'Last_Name')) : e($alumno, 'Last_Name'); ?>" 
                                           placeholder="<?php echo $errores_campos['apellido'] ?? 'Apellido'; ?>" 
                                           class="<?php echo isset($errores_campos['apellido']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['apellido'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['apellido'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['apellido']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <!-- Teléfono -->
                                <div class="form-group">
                                    <label>Teléfono:</label>
                                    <input type="tel" name="telefono" value="<?php echo !empty($post_values) ? getValue('telefono', e($alumno, 'Phone_Number')) : e($alumno, 'Phone_Number'); ?>" 
                                           placeholder="<?php echo $errores_campos['telefono'] ?? 'Ej. 5512345678'; ?>" 
                                           class="<?php echo isset($errores_campos['telefono']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['telefono'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['telefono'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['telefono']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <!-- Correo -->
                                <div class="form-group">
                                    <label>Correo electrónico:</label>
                                    <input type="email" name="correo" value="<?php echo !empty($post_values) ? getValue('correo', e($alumno, 'Email_Address')) : e($alumno, 'Email_Address'); ?>" 
                                           readonly placeholder="correo@ejemplo.com" 
                                           title="No se puede modificar el correo" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['correo'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['correo']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <!-- URL de Foto de Perfil -->
                                <div class="form-group">
                                    <label>URL de la foto de perfil:</label>
                                    <input type="text" id="foto_url" name="foto_url" 
                                           value="<?php echo !empty($post_values) ? getValue('foto_url', e($alumno, 'Profile_Image')) : e($alumno, 'Profile_Image'); ?>" 
                                           placeholder="Nombre de la imagen o URL pública">
                                    <small>Usa un enlace directo a una imagen (JPG, PNG, etc.).</small>
                                </div>
                            </div>

                            <!-- DETALLES ACADÉMICOS -->
                            <div class="form-section">
                                <h3>Detalles académicos</h3>
                                <div class="form-group">
                                    <label>Fecha de nacimiento:</label>
                                    <input type="date" name="fecha_nacimiento" value="<?php echo !empty($post_values) ? getValue('fecha_nacimiento', e($detalles, 'Birthdate')) : e($detalles, 'Birthdate'); ?>" 
                                           class="<?php echo isset($errores_campos['fecha_nacimiento']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['fecha_nacimiento'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['fecha_nacimiento'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['fecha_nacimiento']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Preparatoria:</label>
                                    <input type="text" name="preparatoria" value="<?php echo !empty($post_values) ? getValue('preparatoria', e($detalles, 'High_school')) : e($detalles, 'High_school'); ?>" 
                                           placeholder="<?php echo $errores_campos['preparatoria'] ?? 'Nombre de la preparatoria'; ?>" 
                                           class="<?php echo isset($errores_campos['preparatoria']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['preparatoria'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['preparatoria'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['preparatoria']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Grado:</label>
                                    <input type="text" name="grado" value="<?php echo !empty($post_values) ? getValue('grado', e($detalles, 'Grade')) : e($detalles, 'Grade'); ?>" 
                                           placeholder="<?php echo $errores_campos['grado'] ?? 'Ej. 3°'; ?>" 
                                           class="<?php echo isset($errores_campos['grado']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['grado'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['grado'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['grado']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Licencia:</label>
                                    <input type="text" name="licencia" value="<?php echo !empty($post_values) ? getValue('licencia', e($detalles, 'License')) : e($detalles, 'License'); ?>" 
                                           placeholder="<?php echo $errores_campos['licencia'] ?? 'Ej. B-12345'; ?>" 
                                           class="<?php echo isset($errores_campos['licencia']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['licencia'] ?? ''; ?>">
                                    <?php if (isset($errores_campos['licencia'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['licencia']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Promedio:</label>
                                    <input type="number" name="promedio" value="<?php echo !empty($post_values) ? getValue('promedio', e($detalles, 'Average')) : e($detalles, 'Average'); ?>" 
                                           placeholder="<?php echo $errores_campos['promedio'] ?? '0 - 100'; ?>" 
                                           class="<?php echo isset($errores_campos['promedio']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['promedio'] ?? ''; ?>" 
                                           min="0" max="100">
                                    <?php if (isset($errores_campos['promedio'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['promedio']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- TUTOR -->
                            <div class="form-section">
                                <h3>Datos del tutor</h3>
                                <div class="form-group">
                                    <label>Nombre:</label>
                                    <input type="text" name="tutor_nombre" value="<?php echo !empty($post_values) ? getValue('tutor_nombre', e($tutor, 'Tutor_name')) : e($tutor, 'Tutor_name'); ?>" 
                                           placeholder="<?php echo $errores_campos['tutor_nombre'] ?? 'Nombre del tutor'; ?>" 
                                           class="<?php echo isset($errores_campos['tutor_nombre']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['tutor_nombre'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['tutor_nombre'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['tutor_nombre']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Apellidos:</label>
                                    <input type="text" name="tutor_apellidos" value="<?php echo !empty($post_values) ? getValue('tutor_apellidos', e($tutor, 'Tutor_lastname')) : e($tutor, 'Tutor_lastname'); ?>" 
                                           placeholder="<?php echo $errores_campos['tutor_apellidos'] ?? 'Apellido del tutor'; ?>" 
                                           class="<?php echo isset($errores_campos['tutor_apellidos']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['tutor_apellidos'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['tutor_apellidos'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['tutor_apellidos']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono:</label>
                                    <input type="tel" name="tutor_telefono" value="<?php echo !empty($post_values) ? getValue('tutor_telefono', e($tutor, 'Phone_Number')) : e($tutor, 'Phone_Number'); ?>" 
                                           placeholder="<?php echo $errores_campos['tutor_telefono'] ?? 'Teléfono del tutor'; ?>" 
                                           class="<?php echo isset($errores_campos['tutor_telefono']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['tutor_telefono'] ?? ''; ?>" 
                                           required 
                                           data-required="true">
                                    <?php if (isset($errores_campos['tutor_telefono'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['tutor_telefono']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Dirección:</label>
                                    <input type="text" name="tutor_direccion" value="<?php echo !empty($post_values) ? getValue('tutor_direccion', e($tutor, 'Address')) : e($tutor, 'Address'); ?>" 
                                           placeholder="<?php echo $errores_campos['tutor_direccion'] ?? 'Domicilio del tutor'; ?>" 
                                           class="<?php echo isset($errores_campos['tutor_direccion']) ? 'input-error' : ''; ?>" 
                                           title="<?php echo $errores_campos['tutor_direccion'] ?? ''; ?>">
                                    <?php if (isset($errores_campos['tutor_direccion'])): ?>
                                        <span class="error-message"><?php echo $errores_campos['tutor_direccion']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                    </div>
                            <!-- DOMICILIO -->
                            <div class="ns-card ns-card-section ns-address-card">
                                <div class="form-section address">
                                    <h3>Domicilio del estudiante</h3>
                                    <div class="form-group">
                                        <label>Calle:</label>
                                        <input type="text" name="calle" value="<?php echo !empty($post_values) ? getValue('calle', e($direccion, 'Street')) : e($direccion, 'Street'); ?>" 
                                               placeholder="<?php echo $errores_campos['calle'] ?? 'Ej. Reforma #123'; ?>" 
                                               class="<?php echo isset($errores_campos['calle']) ? 'input-error' : ''; ?>" 
                                               title="<?php echo $errores_campos['calle'] ?? ''; ?>" 
                                               required 
                                               data-required="true">
                                        <?php if (isset($errores_campos['calle'])): ?>
                                            <span class="error-message"><?php echo $errores_campos['calle']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label>Ciudad:</label>
                                        <input type="text" name="ciudad" value="<?php echo !empty($post_values) ? getValue('ciudad', e($direccion, 'City')) : e($direccion, 'City'); ?>" 
                                               placeholder="<?php echo $errores_campos['ciudad'] ?? 'Ej. Guadalajara'; ?>" 
                                               class="<?php echo isset($errores_campos['ciudad']) ? 'input-error' : ''; ?>" 
                                               title="<?php echo $errores_campos['ciudad'] ?? ''; ?>" 
                                               required 
                                               data-required="true">
                                        <?php if (isset($errores_campos['ciudad'])): ?>
                                            <span class="error-message"><?php echo $errores_campos['ciudad']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label>Código postal:</label>
                                        <input type="text" name="codigo_postal" value="<?php echo !empty($post_values) ? getValue('codigo_postal', e($direccion, 'Postal_Code')) : e($direccion, 'Postal_Code'); ?>" 
                                               placeholder="<?php echo $errores_campos['codigo_postal'] ?? 'Ej. 22000'; ?>" 
                                               class="<?php echo isset($errores_campos['codigo_postal']) ? 'input-error' : ''; ?>" 
                                               title="<?php echo $errores_campos['codigo_postal'] ?? ''; ?>" 
                                               required 
                                               data-required="true">
                                        <?php if (isset($errores_campos['codigo_postal'])): ?>
                                            <span class="error-message"><?php echo $errores_campos['codigo_postal']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botón guardar -->
                        <div class="ns-card ns-card-footer">
                            <button type="submit" class="btn" id="saveBtn" disabled>Guardar información</button>
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
            
            // Si el modal está visible, lo ocultamos después de 2.5s
            if (modal.classList.contains('is-visible')) {
                setTimeout(function () {
                    modal.classList.remove('is-visible');
                    // Quitar parámetros de la URL si existen
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('guardado');
                        url.searchParams.delete('completo');
                        url.searchParams.delete('completa');
                        window.history.replaceState({}, document.title, url.toString());
                    }
                }, 4000);
            }
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const changeBtn = document.getElementById('changePhotoBtn');
            const fileInput = document.getElementById('profile_file');
            const fotoUrlInput = document.getElementById('foto_url');
            const previewImg = document.querySelector('.profile-pic');
            
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Obtener todos los campos obligatorios
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
                
                // Habilitar o deshabilitar el botón
                if (filledCount === requiredInputs.length) {
                    saveBtn.disabled = false;
                } else {
                    saveBtn.disabled = true;
                }
            }
            
            // Verificar campos al cargar la página
            checkAllFieldsFilled();
            
            // Agregar event listeners a todos los campos obligatorios
            requiredInputs.forEach(input => {
                input.addEventListener('input', checkAllFieldsFilled);
                input.addEventListener('change', checkAllFieldsFilled);
            });
        });
    </script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Después de un error, verificar qué campos tienen errores
            const errorInputs = document.querySelectorAll('.input-error');
            
            if (errorInputs.length > 0) {
                // Habilitar el botón si hay errores pero todos los campos están llenos
                const requiredInputs = document.querySelectorAll('input[data-required="true"]');
                const saveBtn = document.getElementById('saveBtn');
                
                let allFilled = true;
                requiredInputs.forEach(input => {
                    if (input.value.trim() === '') {
                        allFilled = false;
                    }
                });
                
                if (allFilled) {
                    saveBtn.disabled = false;
                }
                
                // Agregar tooltips a los campos con error
                errorInputs.forEach(input => {
                    input.addEventListener('mouseenter', function() {
                        const title = this.getAttribute('title');
                        if (title) {
                            // Mostrar tooltip personalizado
                            const tooltip = document.createElement('div');
                            tooltip.className = 'error-tooltip';
                            tooltip.textContent = title;
                            tooltip.style.position = 'absolute';
                            tooltip.style.background = '#ff4444';
                            tooltip.style.color = 'white';
                            tooltip.style.padding = '5px';
                            tooltip.style.borderRadius = '3px';
                            tooltip.style.zIndex = '1000';
                            
                            const rect = this.getBoundingClientRect();
                            tooltip.style.top = (rect.top - 30) + 'px';
                            tooltip.style.left = rect.left + 'px';
                            
                            document.body.appendChild(tooltip);
                            this._tooltip = tooltip;
                        }
                    });
                    
                    input.addEventListener('mouseleave', function() {
                        if (this._tooltip) {
                            document.body.removeChild(this._tooltip);
                            delete this._tooltip;
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>