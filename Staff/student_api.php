<?php
// Staff/student_api.php
session_start();
require '../Conexiones/db.php';

header('Content-Type: application/json; charset=utf-8');

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Estados permitidos
$VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción no especificada.']);
    exit;
}

/* ============================
   ACCIÓN: datos básicos
   GET student_api.php?action=basic&id=ID_STUDENT
   ============================ */
if ($method === 'GET' && $action === 'basic') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID inválido']);
        exit;
    }

    $sql = "SELECT 
                s.ID_Student, 
                s.Name, 
                s.Last_Name, 
                s.Email_Address, 
                sd.License, 
                sd.Average,
                a.status
            FROM student s
            LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
            LEFT JOIN aplication a      ON s.ID_Student = a.FK_ID_Student
            WHERE s.ID_Student = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la preparación de la consulta']);
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    $stmt->close();

    if ($student) {
        echo json_encode($student);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Estudiante no encontrado']);
    }
    exit;
}

/* ============================
   ACCIÓN: solicitudes de beca
   GET student_api.php?action=applications&student_id=ID_STUDENT
   ============================ */
if ($method === 'GET' && $action === 'applications') {
    $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de estudiante inválido']);
        exit;
    }

    $sql = "
        SELECT 
            k.ID_Kit,
            k.Name AS KitName,
            k.Description AS KitDescription,
            a.status AS ApplicationStatus,
            a.Application_date,
            k.Start_date,
            k.End_date,
            a.ID_status
        FROM aplication a
        JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
        WHERE a.FK_ID_Student = ?
        ORDER BY a.Application_date DESC, a.ID_status DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la preparación de la consulta']);
        exit;
    }

    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $applications = [];
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['Application_date'])) {
            $row['Application_date'] = date('d/m/Y H:i', strtotime($row['Application_date']));
        }
        if (!empty($row['Start_date'])) {
            $row['Start_date'] = date('d/m/Y', strtotime($row['Start_date']));
        }
        if (!empty($row['End_date'])) {
            $row['End_date'] = date('d/m/Y', strtotime($row['End_date']));
        }
        $applications[] = $row;
    }
    $stmt->close();

    echo json_encode(['applications' => $applications]);
    exit;
}

/* ============================
   ACCIÓN: actualizar estatus
   POST student_api.php (action=update_status)
   ============================ */
if ($method === 'POST' && $action === 'update_status') {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $kit_id     = filter_input(INPUT_POST, 'kit_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

    if (!$student_id || !$kit_id || !in_array($new_status, $VALID_STATUSES, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos o estatus no permitido.']);
        exit;
    }

    $update_query = "UPDATE aplication SET status = ? WHERE FK_ID_Student = ? AND FK_ID_Kit = ?";
    $stmt = $conn->prepare($update_query);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta.']);
        exit;
    }

    $stmt->bind_param("sii", $new_status, $student_id, $kit_id);

    if ($stmt->execute()) {
        $msg = "Estatus actualizado a: " . $new_status;
        if ($new_status === 'Cancelada') {
            $msg = "Solicitud cancelada exitosamente.";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conn->error]);
    }
    $stmt->close();
    exit;
}

// Si llega aquí, la acción no coincide
http_response_code(400);
echo json_encode(['error' => 'Acción no válida.']);
