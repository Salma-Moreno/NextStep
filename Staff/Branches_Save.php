<?php
session_start();
require '../Conexiones/db.php';

// Sólo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$phone = $_POST['phone'] ?? '';
$company = $_POST['company'] ?? '';
$lat = $_POST['latitude'] ?? '';
$lon = $_POST['longitude'] ?? '';

if ($name === '' || $address === '' || $company === '') {
    echo json_encode(["success" => false, "error" => "Campos obligatorios faltantes"]);
    exit;
}

$query = $conn->prepare("
INSERT INTO collection_point (Name, address, Phone_number, FK_ID_Company, latitude, longitude)
VALUES (?, ?, ?, ?, ?, ?)
");

$query->bind_param("sssidd", $name, $address, $phone, $company, $lat, $lon);

if ($query->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
