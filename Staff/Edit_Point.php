<?php
session_start();
require '../Conexiones/db.php';

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_rol"] !== "Staff") {
    header("Location: StaffLogin.php"); exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método inválido");
}

$id          = intval($_POST["ID_Point"]);
$name        = mysqli_real_escape_string($conn, $_POST["Name"]);
$address     = mysqli_real_escape_string($conn, $_POST["address"]);
$phone       = mysqli_real_escape_string($conn, $_POST["Phone_number"]);
$company     = intval($_POST["FK_ID_Company"]);
$lat         = $_POST["latitude"] ?: null;
$lng         = $_POST["longitude"] ?: null;

$query = "
UPDATE collection_point
SET Name = ?, address = ?, Phone_number = ?, FK_ID_Company = ?, latitude = ?, longitude = ?
WHERE ID_Point = ?
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sssiddi", $name, $address, $phone, $company, $lat, $lng, $id);

if (mysqli_stmt_execute($stmt)) {
    header("Location: Branches.php");
    exit;
} else {
    echo "Error al editar: " . mysqli_error($conn);
}
?>
