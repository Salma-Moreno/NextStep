<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

$id = $_POST['id'] ?? '';

if ($id === '') {
    echo json_encode(["success" => false, "error" => "ID no recibido"]);
    exit;
}

$query = $conn->prepare("DELETE FROM collection_point WHERE ID_Point = ?");
$query->bind_param("i", $id);

if ($query->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>
