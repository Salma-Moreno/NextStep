<?php
session_start();
require '../Conexiones/db.php';

// Sólo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

// Sanitizar y validar datos
$name    = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$company = trim($_POST['company'] ?? '');
$lat     = trim($_POST['latitude'] ?? '');
$lon     = trim($_POST['longitude'] ?? '');

$errors = [];

// Validación de nombre
if (empty($name)) {
    $errors[] = "El nombre es obligatorio";
} elseif (strlen($name) < 3) {
    $errors[] = "El nombre debe tener al menos 3 caracteres";
} elseif (!preg_match('/^[a-zA-ZÁÉÍÓÚáéíóúÑñ0-9\s&.,\-()]+$/', $name)) {
    $errors[] = "El nombre contiene caracteres no permitidos";
}

// Validación de dirección
if (empty($address)) {
    $errors[] = "La dirección es obligatoria";
} elseif (strlen($address) < 10) {
    $errors[] = "La dirección debe tener al menos 10 caracteres";
}

// Validación de teléfono (opcional)
if (!empty($phone)) {
    $clean_phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($clean_phone) !== 10) {
        $errors[] = "El teléfono debe tener 10 dígitos";
    } elseif (!preg_match('/^[0-9]{10}$/', $clean_phone)) {
        $errors[] = "Formato de teléfono inválido";
    } else {
        $phone = $clean_phone; // Guardar limpio
    }
}

// Validación de compañía
if (empty($company)) {
    $errors[] = "Debe seleccionar una compañía";
} elseif (!is_numeric($company)) {
    $errors[] = "Compañía no válida";
} else {
    $company = (int)$company;
}

// Validación de coordenadas
if (empty($lat) || empty($lon)) {
    $errors[] = "Las coordenadas son obligatorias";
} elseif (!is_numeric($lat) || !is_numeric($lon)) {
    $errors[] = "Coordenadas no válidas";
} elseif ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    $errors[] = "Coordenadas fuera de rango";
} else {
    $lat = (float)$lat;
    $lon = (float)$lon;
}

// Si hay errores, retornarlos
if (!empty($errors)) {
    echo json_encode(["success" => false, "error" => implode(". ", $errors)]);
    exit;
}

// Verificar que la compañía exista
$check_company = $conn->prepare("SELECT ID_Company FROM company WHERE ID_Company = ?");
$check_company->bind_param("i", $company);
$check_company->execute();
$check_company->store_result();

if ($check_company->num_rows === 0) {
    $check_company->close();
    echo json_encode(["success" => false, "error" => "La compañía no existe"]);
    exit;
}
$check_company->close();

/* ================================
   VALIDAR DIRECCIÓN NO DUPLICADA
   ================================ */
// Evitar que se registre la misma dirección para la misma compañía
$check_address = $conn->prepare("
    SELECT ID_Collection_Point 
    FROM collection_point 
    WHERE address = ? AND FK_ID_Company = ?
    LIMIT 1
");
$check_address->bind_param("si", $address, $company);
$check_address->execute();
$check_address->store_result();

if ($check_address->num_rows > 0) {
    $check_address->close();
    echo json_encode([
        "success" => false,
        "error"   => "Ya existe una sucursal con esa dirección para esta compañía"
    ]);
    exit;
}
$check_address->close();

// Insertar en base de datos
$query = $conn->prepare("
    INSERT INTO collection_point (Name, address, Phone_number, FK_ID_Company, latitude, longitude)
    VALUES (?, ?, ?, ?, ?, ?)
");

$query->bind_param("sssidd", $name, $address, $phone, $company, $lat, $lon);

if ($query->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Error de base de datos: " . $conn->error]);
}
?>
