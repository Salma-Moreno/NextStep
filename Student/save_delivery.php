<?php
session_start();
header('Content-Type: application/json');

require '../Conexiones/db.php';

// 1. Verificar sesión de estudiante
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión inválida. Inicia sesión nuevamente.'
    ]);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido.'
    ]);
    exit;
}

// 2. Validar point_id
$point_id = filter_input(INPUT_POST, 'point_id', FILTER_VALIDATE_INT);
if (!$point_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Punto de entrega inválido.'
    ]);
    exit;
}

$user_id = $_SESSION['usuario_id'];

// 3. Obtener ID_Student a partir del usuario
$sqlStudent = "SELECT ID_Student FROM student WHERE FK_ID_User = ? LIMIT 1";
$stmt = $conn->prepare($sqlStudent);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor (STMT1).'
    ]);
    exit;
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

if (!($row = $res->fetch_assoc())) {
    echo json_encode([
        'success' => false,
        'message' => 'No se encontró un estudiante asociado a tu cuenta.'
    ]);
    $stmt->close();
    exit;
}
$student_id = (int)$row['ID_Student'];
$stmt->close();

// 4. Verificar que el punto de entrega exista
$sqlPoint = "SELECT ID_Point, Name FROM collection_point WHERE ID_Point = ? LIMIT 1";
$stmt = $conn->prepare($sqlPoint);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor (STMT2).'
    ]);
    exit;
}
$stmt->bind_param('i', $point_id);
$stmt->execute();
$resPoint = $stmt->get_result();

if (!($rowPoint = $resPoint->fetch_assoc())) {
    echo json_encode([
        'success' => false,
        'message' => 'El punto de entrega seleccionado no existe.'
    ]);
    $stmt->close();
    exit;
}
$pointName = $rowPoint['Name'];
$stmt->close();

// 5. Buscar la solicitud de beca más reciente APROBADA
$sqlApp = "
    SELECT 
        A.FK_ID_Kit,
        A.ID_status,
        A.Application_date
    FROM aplication A
    WHERE A.FK_ID_Student = ?
      AND A.status = 'Aprobada'
    ORDER BY A.Application_date DESC, A.ID_status DESC
    LIMIT 1
";
$stmt = $conn->prepare($sqlApp);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor (STMT3).'
    ]);
    exit;
}
$stmt->bind_param('i', $student_id);
$stmt->execute();
$resApp = $stmt->get_result();

if (!($rowApp = $resApp->fetch_assoc())) {
    echo json_encode([
        'success' => false,
        'message' => 'Aún no tienes una solicitud de beca aprobada. Solo puedes elegir punto de entrega cuando tu beca esté aprobada.'
    ]);
    $stmt->close();
    exit;
}
$kit_id = (int)$rowApp['FK_ID_Kit'];
$stmt->close();

// 6. Revisar el último cambio de punto de entrega del estudiante (para la regla de 24h)
$sqlLastDelivery = "
    SELECT ID_Delivery, FK_ID_Point, Date
    FROM delivery
    WHERE FK_ID_Student = ?
    ORDER BY Date DESC
    LIMIT 1
";
$stmt = $conn->prepare($sqlLastDelivery);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor (STMT4).'
    ]);
    exit;
}
$stmt->bind_param('i', $student_id);
$stmt->execute();
$resLast = $stmt->get_result();
$lastDelivery = $resLast->fetch_assoc();
$stmt->close();

$now = new DateTime('now');

if ($lastDelivery) {
    $lastDate    = new DateTime($lastDelivery['Date']);
    $diffSeconds = $now->getTimestamp() - $lastDate->getTimestamp();
    $diffHours   = $diffSeconds / 3600;

    // Si intenta cambiar a OTRO punto y no han pasado 24h, NO se permite
    if ($diffHours < 24 && (int)$lastDelivery['FK_ID_Point'] !== $point_id) {
        $nextAllowed    = clone $lastDate;
        $nextAllowed->modify('+24 hours');
        $nextAllowedStr = $nextAllowed->format('d/m/Y H:i');

        echo json_encode([
            'success' => false,
            'message' => 'Solo puedes cambiar tu punto de entrega una vez cada 24 horas. ' .
                         'Podrás cambiarlo de nuevo después de: ' . $nextAllowedStr
        ]);
        exit;
    }

    // Si es el mismo punto y aún no pasan 24h, solo confirmamos (no cambiamos fecha)
    if ($diffHours < 24 && (int)$lastDelivery['FK_ID_Point'] === $point_id) {
        echo json_encode([
            'success'     => true,
            'message'     => 'Este ya es tu punto de entrega actual. No se realizaron cambios.',
            'pickup_date' => $lastDate->format('d/m/Y H:i'),
            'point_name'  => $pointName
        ]);
        exit;
    }
}

// 7. Calcular nueva fecha de entrega = ahora + 4 días (si pasó el control de 24h)
$pickupDateObj      = new DateTime('+4 days');
$pickupDateForDB    = $pickupDateObj->format('Y-m-d H:i:s');
$pickupDateForUser  = $pickupDateObj->format('d/m/Y H:i');

// 8. Ver si ya existe delivery para este estudiante y ESTE kit
$sqlCheckThisKit = "
    SELECT ID_Delivery 
    FROM delivery
    WHERE FK_ID_Student = ? AND FK_ID_Kit = ?
    LIMIT 1
";
$stmt = $conn->prepare($sqlCheckThisKit);
if (!$stmt) {
    echo json_encode([
        'success'   => false,
        'message'   => 'Error en el servidor (STMT5).'
    ]);
    exit;
}
$stmt->bind_param('ii', $student_id, $kit_id);
$stmt->execute();
$resCheckKit = $stmt->get_result();
$deliveryThisKit = $resCheckKit->fetch_assoc();
$stmt->close();

if ($deliveryThisKit) {
    // 9.a Actualizar delivery de este kit
    $sqlUpdate = "
        UPDATE delivery
        SET FK_ID_Point = ?, Date = ?
        WHERE ID_Delivery = ?
    ";
    $stmt = $conn->prepare($sqlUpdate);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Error en el servidor (STMT6).'
        ]);
        exit;
    }
    $delivery_id = (int)$deliveryThisKit['ID_Delivery'];
    $stmt->bind_param('isi', $point_id, $pickupDateForDB, $delivery_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo actualizar el punto de entrega.'
        ]);
        exit;
    }

} else {
    // 9.b Insertar nuevo delivery para este kit/aplicación
    $sqlInsert = "
        INSERT INTO delivery (FK_ID_Student, FK_ID_Kit, FK_ID_Point, Date)
        VALUES (?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sqlInsert);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Error en el servidor (STMT7).'
        ]);
        exit;
    }
    $stmt->bind_param('iiis', $student_id, $kit_id, $point_id, $pickupDateForDB);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo guardar el punto de entrega.'
        ]);
        exit;
    }
}

/*
 * 10. Mismo punto para TODAS las entregas “vigentes” de ese estudiante:
 *     - No tocamos becas Canceladas/Rechazadas
 *     - No tocamos becas de kits expirados (End_date < hoy)
 */
$sqlUpdateAll = "
    UPDATE delivery d
    JOIN aplication a 
      ON a.FK_ID_Student = d.FK_ID_Student
     AND a.FK_ID_Kit     = d.FK_ID_Kit
    JOIN kit k 
      ON k.ID_Kit = d.FK_ID_Kit
    SET d.FK_ID_Point = ?, 
        d.Date       = ?
    WHERE d.FK_ID_Student = ?
      AND a.status NOT IN ('Rechazada', 'Cancelada')
      AND k.End_date >= CURDATE()
";
$stmt = $conn->prepare($sqlUpdateAll);
if ($stmt) {
    $stmt->bind_param('isi', $point_id, $pickupDateForDB, $student_id);
    $stmt->execute();
    $stmt->close();
}

// Respuesta final
echo json_encode([
    'success'     => true,
    'message'     => "Punto de entrega guardado: '{$pointName}'. " .
                     "Podrás recoger tu beca a partir del $pickupDateForUser.",
    'pickup_date' => $pickupDateForUser,
    'point_name'  => $pointName
]);
exit;
