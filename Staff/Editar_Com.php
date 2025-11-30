<?php
session_start();
require '../Conexiones/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Company.php');
    exit;
}

$company_id = $_POST['company_id'] ?? '';
$address_id = $_POST['address_id'] ?? '';

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
    // actualizar company
    $stmt = $conn->prepare("UPDATE company SET Name=?, RFC=?, Email=?, Phone_Number=? WHERE ID_Company=?");
    $stmt->bind_param("ssssi", $name, $rfc, $email, $phone, $company_id);
    $stmt->execute();
    $stmt->close();

    // si viene address_id actualizar, si no insertar nueva y actualizar FK
    if (!empty($address_id)) {
        $stmt2 = $conn->prepare("UPDATE company_address SET Street=?, City=?, State=?, Postal_Code=? WHERE ID_Company_Address=?");
        $stmt2->bind_param("ssssi", $street, $city, $state, $postal, $address_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt3 = $conn->prepare("INSERT INTO company_address (Street, City, State, Postal_Code) VALUES (?, ?, ?, ?)");
        $stmt3->bind_param("ssss", $street, $city, $state, $postal);
        $stmt3->execute();
        $new_addr = $conn->insert_id;
        $stmt3->close();

        $stmt4 = $conn->prepare("UPDATE company SET FK_ID_Company_Address = ? WHERE ID_Company = ?");
        $stmt4->bind_param("ii", $new_addr, $company_id);
        $stmt4->execute();
        $stmt4->close();
    }

    $conn->commit();
    $_SESSION['success'] = "Compañía actualizada.";
    header('Location: Company.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error: " . $e->getMessage();
    header('Location: Company.php');
    exit;
}
