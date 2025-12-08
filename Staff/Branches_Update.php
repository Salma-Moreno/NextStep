<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

$id      = $_POST['id'] ?? '';
$name    = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$company = $_POST['company'] ?? '';
$lat     = $_POST['latitude'] ?? '';
$lon     = $_POST['longitude'] ?? '';

$errors = [];

// Validar campos mínimos
if ($id === '' || $name === '' || $address === '' || $company === '') {
    $errors[] = "Datos incompletos";
}

// Validar tipos básicos
if ($id !== '' && !is_numeric($id)) {
    $errors[] = "ID no válido";
} else {
    $id = (int)$id;
}

if (!is_numeric($company)) {
    $errors[] = "Compañía no válida";
} else {
    $company = (int)$company;
}

// lat/lon pueden venir vacíos si no los usas, pero si llegan, validar
if ($lat === '' || $lon === '') {
    $lat = null;
    $lon = null;
} else {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        $errors[] = "Coordenadas no válidas";
    } else {
        $lat = (float)$lat;
        $lon = (float)$lon;
    }
}

// Teléfono opcional: limpiar
if ($phone !== '') {
    $phone = trim($phone);
}

// Si hay errores, regresarlos
if (!empty($errors)) {
    echo json_encode(["success" => false, "error" => implode(". ", $errors)]);
    exit;
}

// Verificar que la sucursal exista
$check_branch = $conn->prepare("SELECT ID_Point FROM collection_point WHERE ID_Point = ?");
$check_branch->bind_param("i", $id);
$check_branch->execute();
$check_branch->store_result();

if ($check_branch->num_rows === 0) {
    $check_branch->close();
    echo json_encode(["success" => false, "error" => "La sucursal no existe"]);
    exit;
}
$check_branch->close();

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

/* ==========================================
   VALIDAR QUE LA DIRECCIÓN NO ESTÉ REPETIDA
   ========================================== */
/*
   Evita que haya otra sucursal (ID_Point distinto)
   con la misma dirección para la misma compañía.
*/
$check_address = $conn->prepare("
    SELECT ID_Point
    FROM collection_point
    WHERE address = ?
      AND FK_ID_Company = ?
      AND ID_Point <> ?
    LIMIT 1
");
$check_address->bind_param("sii", $address, $company, $id);
$check_address->execute();
$check_address->store_result();

if ($check_address->num_rows > 0) {
    $check_address->close();
    echo json_encode([
        "success" => false,
        "error"   => "Ya existe otra sucursal con esa dirección para esta compañía"
    ]);
    exit;
}
$check_address->close();

// Si todo está bien, actualizar
$query = $conn->prepare("
    UPDATE collection_point
    SET Name = ?, address = ?, Phone_number = ?, FK_ID_Company = ?, latitude = ?, longitude = ?
    WHERE ID_Point = ?
");

// Para lat/lon nulos, usamos 0.0 o ajusta según tu diseño de BD
if ($lat === null) $lat = 0.0;
if ($lon === null) $lon = 0.0;

$query->bind_param("sssiddi", $name, $address, $phone, $company, $lat, $lon, $id);

if ($query->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
