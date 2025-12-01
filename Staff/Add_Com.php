<?php
session_start();
require '../Conexiones/db.php';

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Habilitar excepciones de mysqli para depuración
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Recoger POST
    $company_id   = isset($_POST['company_id']) && $_POST['company_id'] !== '' ? (int)$_POST['company_id'] : null;
    $address_id   = isset($_POST['address_id']) && $_POST['address_id'] !== '' ? (int)$_POST['address_id'] : null;
    $name         = trim($_POST['name'] ?? '');
    $rfc          = trim($_POST['rfc'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $street       = trim($_POST['street'] ?? '');
    $city         = trim($_POST['city'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $postal_code  = trim($_POST['postal_code'] ?? '');

    // Validación mínima
    if (!$name || !$rfc || !$email || !$street || !$city || !$state || !$postal_code) {
        throw new Exception("Faltan datos obligatorios.");
    }

    // Iniciar transacción
    mysqli_begin_transaction($conn);

    // ----- CREAR O EDITAR DIRECCIÓN -----
    if ($address_id) {
        // Editar dirección existente
        $stmt = $conn->prepare("UPDATE company_address SET Street=?, City=?, State=?, Postal_Code=? WHERE ID_Company_Address=?");
        $stmt->bind_param('ssssi', $street, $city, $state, $postal_code, $address_id);
        $stmt->execute();
    } else {
        // Insertar nueva dirección
        $stmt = $conn->prepare("INSERT INTO company_address (Street, City, State, Postal_Code) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $street, $city, $state, $postal_code);
        $stmt->execute();
        $address_id = $stmt->insert_id;
    }

    // ----- CREAR O EDITAR COMPAÑÍA -----
    if ($company_id) {
        // Editar compañía existente
        $stmt = $conn->prepare("UPDATE company SET Name=?, RFC=?, Email=?, Phone_Number=?, FK_ID_Company_Address=? WHERE ID_Company=?");
        $stmt->bind_param('ssssii', $name, $rfc, $email, $phone, $address_id, $company_id);
        $stmt->execute();
    } else {
        // Insertar nueva compañía
        $stmt = $conn->prepare("INSERT INTO company (Name, RFC, Email, Phone_Number, FK_ID_Company_Address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $rfc, $email, $phone, $address_id);
        $stmt->execute();
        $company_id = $stmt->insert_id;
    }

    // Commit
    mysqli_commit($conn);

    // Redirigir de vuelta a la lista
    header("Location: ../Staff/Company.php");
    exit;

} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    die("Error de MySQL: " . $e->getMessage());
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Error: " . $e->getMessage());
}
