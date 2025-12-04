<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

include '../Conexiones/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$id_solicitud = $data['id_solicitud'] ?? null;
$nuevo_estado = $data['nuevo_estado'] ?? null;

if (!$id_solicitud || !$nuevo_estado) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Validar estado permitido
$estados_permitidos = ['Pending', 'Approved', 'Rejected', 'Delivered'];
if (!in_array($nuevo_estado, $estados_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Estado no válido']);
    exit;
}

try {
    // Actualizar estado
    $sql = "UPDATE aplication SET status = ? WHERE ID_status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nuevo_estado, $id_solicitud);
    
    if ($stmt->execute()) {
        // Si se marca como entregado, registrar en tabla delivery
        if ($nuevo_estado == 'Delivered') {
            // Obtener datos de la aplicación
            $sql_app = "SELECT FK_ID_Student, FK_ID_Kit FROM aplication WHERE ID_status = ?";
            $stmt_app = $conn->prepare($sql_app);
            $stmt_app->bind_param("i", $id_solicitud);
            $stmt_app->execute();
            $result_app = $stmt_app->get_result();
            
            if ($row_app = $result_app->fetch_assoc()) {
                // Verificar si ya existe en delivery
                $sql_check = "SELECT ID_Delivery FROM delivery WHERE FK_ID_Student = ? AND FK_ID_Kit = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param("ii", $row_app['FK_ID_Student'], $row_app['FK_ID_Kit']);
                $stmt_check->execute();
                $stmt_check->store_result();
                
                if ($stmt_check->num_rows == 0) {
                    // Insertar en delivery (asumiendo punto de entrega 1 por defecto)
                    $sql_delivery = "INSERT INTO delivery (FK_ID_Student, FK_ID_Kit, FK_ID_Point, Date) VALUES (?, ?, 1, NOW())";
                    $stmt_delivery = $conn->prepare($sql_delivery);
                    $stmt_delivery->bind_param("ii", $row_app['FK_ID_Student'], $row_app['FK_ID_Kit']);
                    $stmt_delivery->execute();
                    $stmt_delivery->close();
                }
                $stmt_check->close();
            }
            $stmt_app->close();
        }
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $stmt->error]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>