<?php
session_start();
require '../Conexiones/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
if (!$id) {
    echo json_encode(['success'=>false,'error'=>'ID faltante']);
    exit;
}

// Para seguridad podrías comprobar permisos, etc.
try {
    // Obtener FK de address para posible eliminación (opcional)
    $q = $conn->prepare("SELECT FK_ID_Company_Address FROM company WHERE ID_Company = ?");
    $q->bind_param("i",$id);
    $q->execute();
    $q->bind_result($fk_addr);
    $q->fetch();
    $q->close();

    $conn->begin_transaction();

    // Eliminar company (debe quitar referencias en collection_point si aplica)
    $stmt = $conn->prepare("DELETE FROM company WHERE ID_Company = ?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();

    // Opcional: eliminar la dirección relacionada si no es usada por otros
    if ($fk_addr) {
        // verificar si otra compañía usa la misma dirección
        $chk = $conn->prepare("SELECT COUNT(*) FROM company WHERE FK_ID_Company_Address = ?");
        $chk->bind_param("i",$fk_addr);
        $chk->execute();
        $chk->bind_result($count);
        $chk->fetch();
        $chk->close();

        if ($count == 0) {
            $del = $conn->prepare("DELETE FROM company_address WHERE ID_Company_Address = ?");
            $del->bind_param("i",$fk_addr);
            $del->execute();
            $del->close();
        }
    }

    $conn->commit();
    echo json_encode(['success'=>true]);
    exit;

} catch(Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}
