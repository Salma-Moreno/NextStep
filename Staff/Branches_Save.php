<?php
session_start();
require '../Conexiones/db.php';

// Sólo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode([
        "success" => false,
        "error"   => "Acceso no autorizado",
        "msg"     => "Acceso no autorizado"
    ]);
    exit;
}

$name    = trim($_POST['name'] ?? '');
$address = trim($_POST['address'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$company = $_POST['company'] ?? '';
$lat     = $_POST['latitude'] ?? '';
$lon     = $_POST['longitude'] ?? '';

$errors = [];

// Validar campos obligatorios
if ($name === '' || $address === '' || $company === '') {
    $errors[] = "Campos obligatorios faltantes";
}

// Validar compañía numérica
if (!is_numeric($company)) {
    $errors[] = "Compañía no válida";
} else {
    $company = (int)$company;
}

// Validar coordenadas opcionales
if ($lat !== '' && $lon !== '') {
    if (!is_numeric($lat) || !is_numeric($lon)) {
        $errors[] = "Coordenadas inválidas";
    } else {
        $lat = (float)$lat;
        $lon = (float)$lon;
    }
} else {
    $lat = null;
    $lon = null;
}

// Si hay errores, retornarlos
if (!empty($errors)) {
    $msg = implode(". ", $errors);
    echo json_encode([
        "success" => false,
        "error"   => $msg,
        "msg"     => $msg
    ]);
    exit;
}

/* ==========================================
   VALIDACIÓN DE DIRECCIÓN ÚNICA
   ========================================== */
/*
   No se puede registrar una sucursal con la misma dirección
   para la misma compañía.
*/

$check_address = $conn->prepare("
    SELECT ID_Point
    FROM collection_point
    WHERE address = ?
      AND FK_ID_Company = ?
    LIMIT 1
");
$check_address->bind_param("si", $address, $company);
$check_address->execute();
$check_address->store_result();

if ($check_address->num_rows > 0) {
    $check_address->close();
    $msg = "Ya existe una sucursal con esa dirección para esta compañía";
    echo json_encode([
        "success" => false,
        "error"   => $msg,
        "msg"     => $msg
    ]);
    exit;
}
$check_address->close();

/* ==========================================
   INSERTAR LA NUEVA SUCURSAL
   ========================================== */

$query = $conn->prepare("
    INSERT INTO collection_point (Name, address, Phone_number, FK_ID_Company, latitude, longitude)
    VALUES (?, ?, ?, ?, ?, ?)
");

// Si tu BD NO acepta NULL en latitude/longitude, usa 0.0
if ($lat === null) $lat = 0.0;
if ($lon === null) $lon = 0.0;

$query->bind_param("sssidd", $name, $address, $phone, $company, $lat, $lon);

if ($query->execute()) {
    echo json_encode([
        "success" => true,
        "msg"     => "Sucursal guardada correctamente"
    ]);
} else {
    $msg = "Error de base de datos: " . $conn->error;
    echo json_encode([
        "success" => false,
        "error"   => $msg,
        "msg"     => $msg
    ]);
}
?>
