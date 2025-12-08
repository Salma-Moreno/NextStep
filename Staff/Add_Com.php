<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    // Aquí sí puedes seguir usando die o redirigir al login
    die("Error: Acceso no autorizado");
}

// Helper para manejar errores y regresar a Company.php
function go_error($msg) {
    $_SESSION['error'] = $msg;
    header("Location: Company.php");
    exit;
}

// Habilitar excepciones de mysqli para depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Recoger POST
    $company_id   = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
    $address_id   = isset($_POST['address_id']) && $_POST['address_id'] !== '' ? (int)$_POST['address_id'] : null;
    $name         = trim($_POST['name'] ?? '');
    $rfc          = trim($_POST['rfc'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $street       = trim($_POST['street'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $postal_code  = trim($_POST['postal_code'] ?? '');

    // ==================== VALIDACIONES AGREGADAS ====================
    
    // 1. Validación de campos requeridos
    $required = ['name', 'rfc', 'email', 'street', 'city', 'state', 'postal_code'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            go_error("El campo " . ucfirst($field) . " es obligatorio");
        }
    }

    // 2. Validación de NOMBRE (no solo números/símbolos)
    if (strlen($name) < 3) {
        go_error("El nombre debe tener al menos 3 caracteres");
    }

    if (preg_match('/^[0-9\s\W]+$/', $name)) {
        go_error("El nombre debe contener letras");
    }

    if (!preg_match('/^[a-zA-ZÁÉÍÓÚáéíóúÑñ0-9\s&.,\-()]+$/', $name)) {
        go_error("El nombre contiene caracteres no permitidos. Use solo letras, números, espacios y los símbolos: & . , - ( )");
    }

    // 3. Validación de TELÉFONO (10 dígitos si se proporciona)
    if (!empty($phone)) {
        $clean_phone = preg_replace('/[^\d]/', '', $phone);
        
        if (strlen($clean_phone) !== 10) {
            go_error("El teléfono debe tener 10 dígitos");
        }
        
        if (!preg_match('/^[0-9]{10}$/', $clean_phone)) {
            go_error("Formato de teléfono inválido");
        }
        
        $phone = $clean_phone; // Guardar limpio
    }

    // 4. Validación de EMAIL
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        go_error("Email no válido. Ejemplo: contacto@empresa.com");
    }

    // 5. Validación de RFC
    $rfc_upper = strtoupper($rfc);
    if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', $rfc_upper)) {
        go_error("RFC no válido. Ejemplo para persona moral: XAXX010101000");
    }

    // 6. Validación de CÓDIGO POSTAL
    if (!preg_match('/^[0-9]{5}$/', $postal_code)) {
        go_error("El código postal debe tener 5 dígitos");
    }

    // 7. Validación de unicidad de NOMBRE (crear/editar)
    if ($company_id) {
        // Editando: no permitir que el nombre exista en otra compañía distinta
        $check_name = $conn->prepare("SELECT ID_Company FROM company WHERE Name = ? AND ID_Company <> ?");
        $check_name->bind_param("si", $name, $company_id);
    } else {
        // Nueva compañía: no permitir nombre ya usado
        $check_name = $conn->prepare("SELECT ID_Company FROM company WHERE Name = ?");
        $check_name->bind_param("s", $name);
    }

    $check_name->execute();
    $check_name->store_result();

    if ($check_name->num_rows > 0) {
        $check_name->close();
        go_error("Ya existe una compañía con ese nombre");
    }
    $check_name->close();

    // 8. Validación de unicidad de EMAIL (crear/editar)
    if ($company_id) {
        $check_email = $conn->prepare("SELECT ID_Company FROM company WHERE Email = ? AND ID_Company <> ?");
        $check_email->bind_param("si", $email, $company_id);
    } else {
        $check_email = $conn->prepare("SELECT ID_Company FROM company WHERE Email = ?");
        $check_email->bind_param("s", $email);
    }

    $check_email->execute();
    $check_email->store_result();

    if ($check_email->num_rows > 0) {
        $check_email->close();
        go_error("Este email ya está registrado");
    }
    $check_email->close();

    // 9. Validación de unicidad de RFC (crear/editar)
    if ($company_id) {
        $check_rfc = $conn->prepare("SELECT ID_Company FROM company WHERE RFC = ? AND ID_Company <> ?");
        $check_rfc->bind_param("si", $rfc_upper, $company_id);
    } else {
        $check_rfc = $conn->prepare("SELECT ID_Company FROM company WHERE RFC = ?");
        $check_rfc->bind_param("s", $rfc_upper);
    }

    $check_rfc->execute();
    $check_rfc->store_result();

    if ($check_rfc->num_rows > 0) {
        $check_rfc->close();
        go_error("Este RFC ya está registrado");
    }
    $check_rfc->close();

    // ==================== FIN DE VALIDACIONES ====================

    // Iniciar transacción
    mysqli_begin_transaction($conn);

    // ----- CREAR O EDITAR DIRECCIÓN -----
    if ($address_id) {
        // Editar dirección existente
        $stmt = $conn->prepare("UPDATE company_address SET Street=?, City=?, State=?, Postal_Code=? WHERE ID_Company_Address=?");
        $stmt->bind_param('ssssi', $street, $city, $state, $postal_code, $address_id);
        $stmt->execute();
    } else {
        // Insertar nueva dirección
        $stmt = $conn->prepare("INSERT INTO company_address (Street, City, State, Postal_Code) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $street, $city, $state, $postal_code);
        $stmt->execute();
        $address_id = $stmt->insert_id;
    }

    // ----- CREAR O EDITAR COMPAÑÍA -----
    if ($company_id) {
        // Editar compañía existente
        $stmt = $conn->prepare("UPDATE company SET Name=?, RFC=?, Email=?, Phone_Number=?, FK_ID_Company_Address=? WHERE ID_Company=?");
        $stmt->bind_param('ssssii', $name, $rfc_upper, $email, $phone, $address_id, $company_id);
        $stmt->execute();
    } else {
        // Insertar nueva compañía
        $stmt = $conn->prepare("INSERT INTO company (Name, RFC, Email, Phone_Number, FK_ID_Company_Address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $rfc_upper, $email, $phone, $address_id);
        $stmt->execute();
        $company_id = $stmt->insert_id;
    }

    // Commit
    mysqli_commit($conn);

    // Mensaje de éxito y redirección
    $_SESSION['success'] = $company_id ? "Compañía guardada correctamente." : "Compañía creada correctamente.";
    header("Location: Company.php");
    exit;

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    // En desarrollo puedes usar el mensaje real, en producción tal vez algo genérico
    go_error("Error de MySQL: " . $e->getMessage());
} catch (Exception $e) {
    mysqli_rollback($conn);
    go_error("Error: " . $e->getMessage());
}
