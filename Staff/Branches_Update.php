<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';
$phone = $_POST['phone'] ?? '';
$company = $_POST['company'] ?? '';
$lat = $_POST['latitude'] ?? '';
$lon = $_POST['longitude'] ?? '';

if ($id === '' || $name === '' || $address === '' || $company === '') {
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
    exit;
}

$query = $conn->prepare("
UPDATE collection_point
SET Name = ?, address = ?, Phone_number = ?, FK_ID_Company = ?, latitude = ?, longitude = ?
WHERE ID_Point = ?
");

$query->bind_param("sssiddi", $name, $address, $phone, $company, $lat, $lon, $id);

if ($query->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
