<?php
session_start();
require '../Conexiones/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: Company.php');
    exit;
}

$company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
$address_id = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;

$name   = trim($_POST['name'] ?? '');
$rfc    = trim($_POST['rfc'] ?? '');
$email  = trim($_POST['email'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$street = trim($_POST['street'] ?? '');
$city   = trim($_POST['city'] ?? '');
$state  = trim($_POST['state'] ?? '');
$postal = trim($_POST['postal_code'] ?? '');

/* =========================
   VALIDACIONES DE UNICIDAD
   ========================= */

if ($company_id > 0) {

    // 1) Nombre único
    $stmtCheckName = $conn->prepare("
        SELECT ID_Company 
        FROM company 
        WHERE Name = ? 
          AND ID_Company <> ?
        LIMIT 1
    ");
    $stmtCheckName->bind_param("si", $name, $company_id);
    $stmtCheckName->execute();
    $stmtCheckName->store_result();

    if ($stmtCheckName->num_rows > 0) {
        $stmtCheckName->close();
        $_SESSION['error'] = "Ya existe una compañía con ese nombre.";
        header('Location: Company.php');
        exit;
    }
    $stmtCheckName->close();

    // 2) Email único
    if ($email !== '') {
        $stmtCheckEmail = $conn->prepare("
            SELECT ID_Company 
            FROM company 
            WHERE Email = ? 
              AND ID_Company <> ?
            LIMIT 1
        ");
        $stmtCheckEmail->bind_param("si", $email, $company_id);
        $stmtCheckEmail->execute();
        $stmtCheckEmail->store_result();

        if ($stmtCheckEmail->num_rows > 0) {
            $stmtCheckEmail->close();
            $_SESSION['error'] = "Ya existe una compañía con ese email.";
            header('Location: Company.php');
            exit;
        }
        $stmtCheckEmail->close();
    }

    // 3) RFC único
    if ($rfc !== '') {
        $stmtCheckRFC = $conn->prepare("
            SELECT ID_Company 
            FROM company 
            WHERE RFC = ? 
              AND ID_Company <> ?
            LIMIT 1
        ");
        $stmtCheckRFC->bind_param("si", $rfc, $company_id);
        $stmtCheckRFC->execute();
        $stmtCheckRFC->store_result();

        if ($stmtCheckRFC->num_rows > 0) {
            $stmtCheckRFC->close();
            $_SESSION['error'] = "Ya existe una compañía con ese RFC.";
            header('Location: Company.php');
            exit;
        }
        $stmtCheckRFC->close();
    }
}

$conn->begin_transaction();
try {
    // actualizar company
    $stmt = $conn->prepare("UPDATE company 
                            SET Name = ?, RFC = ?, Email = ?, Phone_Number = ? 
                            WHERE ID_Company = ?");
    $stmt->bind_param("ssssi", $name, $rfc, $email, $phone, $company_id);
    $stmt->execute();
    $stmt->close();

    // si viene address_id actualizar, si no insertar nueva y actualizar FK
    if (!empty($address_id)) {
        $stmt2 = $conn->prepare("UPDATE company_address 
                                 SET Street = ?, City = ?, State = ?, Postal_Code = ? 
                                 WHERE ID_Company_Address = ?");
        $stmt2->bind_param("ssssi", $street, $city, $state, $postal, $address_id);
        $stmt2->execute();
        $stmt2->close();
    } else {
        $stmt3 = $conn->prepare("INSERT INTO company_address (Street, City, State, Postal_Code) 
                                 VALUES (?, ?, ?, ?)");
        $stmt3->bind_param("ssss", $street, $city, $state, $postal);
        $stmt3->execute();
        $new_addr = $conn->insert_id;
        $stmt3->close();

        $stmt4 = $conn->prepare("UPDATE company 
                                 SET FK_ID_Company_Address = ? 
                                 WHERE ID_Company = ?");
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
