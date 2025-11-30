<?php
session_start();
require '../Conexiones/db.php';

header("Content-Type: application/json");

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_rol"] !== "Staff") {
    echo json_encode(["success" => false, "error" => "Sin autorización"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data["id"] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "error" => "ID inválido"]);
    exit;
}

$query = "DELETE FROM collection_point WHERE ID_Point = ?";
$stmt = mysqli_prepare($conn, $query);

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
}
?>
