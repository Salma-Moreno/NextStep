<?php
session_start();
require '../Conexiones/db.php';

if (!isset($_SESSION["usuario_id"]) || $_SESSION["usuario_rol"] !== "Staff") {
    header("Location: StaffLogin.php"); exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Método inválido");
}

$name        = mysqli_real_escape_string($conn, $_POST["Name"]);
$address     = mysqli_real_escape_string($conn, $_POST["address"]);
$phone       = mysqli_real_escape_string($conn, $_POST["Phone_number"]);
$company     = intval($_POST["FK_ID_Company"]);
$lat         = $_POST["latitude"] ?: null;
$lng         = $_POST["longitude"] ?: null;

$query = "
INSERT INTO collection_point
(Name, address, Phone_number, FK_ID_Company, latitude, longitude)
VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "sssidd", $name, $address, $phone, $company, $lat, $lng);

if (mysqli_stmt_execute($stmt)) {
    header("Location: Branches.php");
    exit;
} else {
    echo "Error al guardar: " . mysqli_error($conn);
}
?>
