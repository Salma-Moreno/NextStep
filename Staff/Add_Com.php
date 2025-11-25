<?php
session_start();
require '../Conexiones/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Company.php');
    exit;
}

// recolectar datos (sin validación exhaustiva)
$name = $_POST['name'] ?? '';
$rfc = $_POST['rfc'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';

$street = $_POST['street'] ?? '';
$city = $_POST['city'] ?? '';
$state = $_POST['state'] ?? '';
$postal = $_POST['postal_code'] ?? '';

$conn->begin_transaction();
try {
    // insertar dirección
    $stmt = $conn->prepare("INSERT INTO company_address (Street, City, State, Postal_Code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $street, $city, $state, $postal);
    $stmt->execute();
    $address_id = $conn->insert_id;
    $stmt->close();

    // insertar compañía apuntando a address
    $stmt2 = $conn->prepare("INSERT INTO company (FK_ID_Company_Address, Name, RFC, Email, Phone_Number) VALUES (?, ?, ?, ?, ?)");
    $stmt2->bind_param("issss", $address_id, $name, $rfc, $email, $phone);
    $stmt2->execute();
    $stmt2->close();

    $conn->commit();
    $_SESSION['success'] = "Compañía agregada correctamente.";
    header('Location: Company.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: Company.php');
    exit;
}
