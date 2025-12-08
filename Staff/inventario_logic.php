<?php
/* =======================
   Conexión y helpers
   ======================= */
require '../Conexiones/db.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$error = '';
$msg   = $_GET['msg'] ?? null;

/* Helper para redirigir con mensaje */
function go($msg, $extra = '')
{
    $qs = 'msg=' . urlencode($msg);
    if ($extra !== '') $qs .= '&' . ltrim($extra, '&?');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $qs);
    exit;
}

/* Límite de materiales según tipo de beca */
function kit_level_limit(string $level): ?int {
    switch ($level) {
        case 'Basica':
            return 5;   // máx. 5 artículos
        case 'Intermedia':
            return 10;  // máx. 10 artículos
        case 'Superior':
            return 15;  // máx. 15 artículos
        case 'PromedioMaximo':
            return 20;  // máx. 20 artículos
        default:
            return null;
    }
}

/* Helper para detectar peticiones AJAX */
function is_ajax_request(): bool {
    return (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
    );
}

/* =======================
   MANEJO DE ACCIONES POST
   ======================= */
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {

        /* --- Crear kit + materiales --- */
        case 'create_kit':
            $fk_semester = intval($_POST['fk_semester'] ?? 0);
            $start_date  = $_POST['start_date'] ?? null;
            $end_date    = $_POST['end_date'] ?? null;
            $kit_level   = $_POST['kit_level'] ?? '';

            if (!$fk_semester || !$start_date || !$end_date || $kit_level === '') {
                $error = "Todos los campos (semestre, tipo de beca y fechas) son obligatorios para crear un kit.";
                break;
            }

            $limit = kit_level_limit($kit_level);
            if ($limit === null) {
                $error = "Tipo de beca inválido.";
                break;
            }

            // Recolectar materiales del formulario
            $kitSupplies = $_POST['kit_supply_ids'] ?? [];
            $kitUnits    = $_POST['kit_units'] ?? [];

            $materialPairs = [];
            if (is_array($kitSupplies) && is_array($kitUnits)) {
                for ($i = 0; $i < count($kitSupplies); $i++) {
                    $sid = intval($kitSupplies[$i] ?? 0);
                    $qty = intval($kitUnits[$i] ?? 0);
                    if ($sid > 0 && $qty > 0) {
                        $materialPairs[] = ['sid' => $sid, 'qty' => $qty];
                    }
                }
            }

            $materialCount = count($materialPairs);
            if ($materialCount > $limit) {
                $error = "Para la beca de tipo $kit_level solo puedes agregar hasta $limit artículos (intentas agregar $materialCount).";
                break;
            }

            // ==== Generar nombre de la beca ====
            // Basica -> basica, Intermedia -> intermedia, Superior -> superior, PromedioMaximo -> promedio-maximo
            switch ($kit_level) {
                case 'Basica':
                    $slug = 'basica';
                    break;
                case 'Intermedia':
                    $slug = 'intermedia';
                    break;
                case 'Superior':
                    $slug = 'superior';
                    break;
                default:
                    $slug = strtolower(preg_replace('/\s+/', '-', $kit_level));
                    break;
            }

            // Contar cuántas becas de este tipo ya existen
            $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM kit WHERE Name LIKE ?");
            $like = "beca-$slug-%";
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $resCount = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $num = intval($resCount['total'] ?? 0) + 1;
            $kitName = "beca-$slug-$num";

            // Insertar kit
            $stmt = $conn->prepare("
                INSERT INTO kit (Name, FK_ID_Semester, Start_date, End_date)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("siss", $kitName, $fk_semester, $start_date, $end_date);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            // Insertar materiales si hay
            if (!empty($materialPairs)) {
                $insMat = $conn->prepare(
                    "INSERT INTO kit_material (FK_ID_Kit, FK_ID_Supply, Unit) 
                     VALUES (?, ?, ?)"
                );
                foreach ($materialPairs as $pair) {
                    $sid = $pair['sid'];
                    $qty = $pair['qty'];
                    $insMat->bind_param("iii", $newId, $sid, $qty);
                    $insMat->execute();
                }
                $insMat->close();
            }

            go('kit_created', 'action=view_materials&id=' . $newId);
            break;

        /* --- Actualizar kit (solo semestre/fechas) --- */
        case 'update_kit':
            $id          = intval($_POST['id_kit'] ?? 0);
            $fk_semester = intval($_POST['fk_semester'] ?? 0);
            $start_date  = $_POST['start_date'] ?? null;
            $end_date    = $_POST['end_date'] ?? null;

            if ($id && $fk_semester && $start_date && $end_date) {
                $stmt = $conn->prepare(
                    "UPDATE kit 
                     SET FK_ID_Semester = ?, Start_date = ?, End_date = ? 
                     WHERE ID_Kit = ?"
                );
                $stmt->bind_param("issi", $fk_semester, $start_date, $end_date, $id);
                $stmt->execute();
                $stmt->close();

                go('kit_updated', 'action=view_materials&id=' . $id);
            } else {
                $error = "Todos los campos son obligatorios para editar un kit.";
            }
            break;

        /* --- Agregar material a un kit (sección inferior) --- */
        case 'add_material':
            $fk_kit    = intval($_POST['fk_kit'] ?? 0);
            $fk_supply = intval($_POST['fk_supply'] ?? 0);
            $unit      = intval($_POST['unit'] ?? 0);

            if ($fk_kit && $fk_supply && $unit >= 0) {
                $stmt = $conn->prepare(
                    "SELECT ID_KitMaterial, Unit 
                     FROM kit_material 
                     WHERE FK_ID_Kit = ? AND FK_ID_Supply = ?"
                );
                $stmt->bind_param("ii", $fk_kit, $fk_supply);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    $newUnit = intval($row['Unit']) + $unit;
                    $stmt->close();

                    $up = $conn->prepare(
                        "UPDATE kit_material SET Unit = ? WHERE ID_KitMaterial = ?"
                    );
                    $up->bind_param("ii", $newUnit, $row['ID_KitMaterial']);
                    $up->execute();
                    $up->close();
                } else {
                    $stmt->close();
                    $ins = $conn->prepare(
                        "INSERT INTO kit_material (FK_ID_Kit, FK_ID_Supply, Unit) 
                         VALUES (?, ?, ?)"
                    );
                    $ins->bind_param("iii", $fk_kit, $fk_supply, $unit);
                    $ins->execute();
                    $ins->close();
                }

                go('material_added', 'action=view_materials&id=' . $fk_kit);
            } else {
                $error = "Datos inválidos al intentar agregar material.";
            }
            break;

        /* --- Actualizar material --- */
        case 'update_material':
            $id   = intval($_POST['id_material'] ?? 0);
            $unit = intval($_POST['unit'] ?? 0);

            if ($id && $unit >= 0) {
                $stmt = $conn->prepare(
                    "SELECT FK_ID_Kit FROM kit_material WHERE ID_KitMaterial = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $res    = $stmt->get_result();
                $rowKit = $res->fetch_assoc();
                $fk_kit = $rowKit['FK_ID_Kit'] ?? 0;
                $stmt->close();

                if ($fk_kit) {
                    $up = $conn->prepare(
                        "UPDATE kit_material SET Unit = ? WHERE ID_KitMaterial = ?"
                    );
                    $up->bind_param("ii", $unit, $id);
                    $up->execute();
                    $up->close();

                    go('material_updated', 'action=view_materials&id=' . $fk_kit);
                } else {
                    $error = "No se pudo identificar el kit al que pertenece este material.";
                }
            } else {
                $error = "Datos inválidos al intentar editar material.";
            }
            break;

        /* --- Crear / actualizar suministro --- */
        case 'create_supply':
            $name     = trim($_POST['s_name']     ?? '');
            $features = trim($_POST['s_features'] ?? '');
            $marca    = trim($_POST['s_marca']    ?? '');
            $unit     = trim($_POST['s_unit']     ?? '');

            $quantity = intval($unit);

            if ($name !== '' && $quantity > 0) {
                $stmt = $conn->prepare(
                    "SELECT ID_Supply, Unit 
                     FROM supplies 
                     WHERE Name = ? AND marca = ? AND features = ?"
                );
                $stmt->bind_param("sss", $name, $marca, $features);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    $current = intval($row['Unit']);
                    $newUnit = $current + $quantity;
                    $stmt->close();

                    $up = $conn->prepare(
                        "UPDATE supplies 
                         SET Unit = ? 
                         WHERE ID_Supply = ?"
                    );
                    $up->bind_param("ii", $newUnit, $row['ID_Supply']);
                    $up->execute();
                    $up->close();

                    go('supply_updated');
                } else {
                    $stmt->close();
                    $ins = $conn->prepare(
                        "INSERT INTO supplies (Name, features, marca, Unit) 
                         VALUES (?, ?, ?, ?)"
                    );
                    $ins->bind_param("sssi", $name, $features, $marca, $quantity);
                    $ins->execute();
                    $ins->close();

                    go('supply_created');
                }
            } else {
                $error = "Nombre y cantidad deben ser válidos para crear/actualizar un suministro.";
            }
            break;
    }
}

/* =======================
   ACCIONES GET
   ======================= */
$getAction = $_GET['action'] ?? '';

/* --- Endpoint AJAX: detalles de kit para el modal "Ver" --- */
if ($getAction === 'kit_details' && isset($_GET['id']) && is_ajax_request()) {
    $id = intval($_GET['id']);
    $response = ['ok' => false];

    if ($id > 0) {
        // Info del kit
        $stmt = $conn->prepare(
            "SELECT k.ID_Kit, k.Name, k.Start_date, k.End_date,
                    s.Period, s.Year
             FROM kit k
             LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
             WHERE k.ID_Kit = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resKit = $stmt->get_result();
        $kit = $resKit->fetch_assoc() ?: null;
        $stmt->close();

        if ($kit) {
            // Materiales
            $materials = [];
            $qm = $conn->prepare(
                "SELECT km.ID_KitMaterial, km.Unit,
                        sp.Name AS supply_name, sp.features, sp.marca
                 FROM kit_material km
                 JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply
                 WHERE km.FK_ID_Kit = ?
                 ORDER BY sp.Name"
            );
            $qm->bind_param("i", $id);
            $qm->execute();
            $rm = $qm->get_result();
            while ($row = $rm->fetch_assoc()) {
                $materials[] = $row;
            }
            $qm->close();

            $response['ok']        = true;
            $response['kit']       = $kit;
            $response['materials'] = $materials;
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    exit;
}

/* --- Eliminar kit --- */
if ($getAction === 'delete_kit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM kit_material WHERE FK_ID_Kit = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM kit WHERE ID_Kit = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        go('kit_deleted');
    }
}

/* --- Eliminar material --- */
if ($getAction === 'delete_material' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id) {
        $stmt = $conn->prepare(
            "SELECT FK_ID_Kit FROM kit_material WHERE ID_KitMaterial = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res    = $stmt->get_result();
        $rowKit = $res->fetch_assoc();
        $fk_kit = $rowKit['FK_ID_Kit'] ?? 0;
        $stmt->close();

        $del = $conn->prepare("DELETE FROM kit_material WHERE ID_KitMaterial = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        if ($fk_kit) {
            go('material_deleted', 'action=view_materials&id=' . $fk_kit);
        } else {
            go('material_deleted');
        }
    }
}

/* =======================
   PAGINACIÓN DE KITS
   ======================= */
$registrosPorPagina = 5;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;

$offset = ($paginaActual - 1) * $registrosPorPagina;

// Total de kits
$stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM kit");
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalKits = $resultTotal->fetch_assoc()['total'] ?? 0;
$stmtTotal->close();

$totalPaginas = ($totalKits > 0)
    ? (int)ceil($totalKits / $registrosPorPagina)
    : 1;

if ($paginaActual > $totalPaginas && $totalPaginas > 0) {
    $paginaActual = $totalPaginas;
    $offset = ($paginaActual - 1) * $registrosPorPagina;
}

/* =======================
   CONSULTAS AUXILIARES
   ======================= */

/* Semestres */
$semesters = [];
$res = $conn->query(
    "SELECT ID_Semester, Period, Year 
     FROM semester 
     ORDER BY Year DESC, Period ASC"
);
while ($r = $res->fetch_assoc()) $semesters[] = $r;
$res->free();

/* Supplies (TODOS - para catálogo y select de materiales) */
$supplies_all = [];
$resSup = $conn->query(
    "SELECT ID_Supply, Name, features, marca, Unit 
     FROM supplies 
     ORDER BY Name"
);
while ($r = $resSup->fetch_assoc()) $supplies_all[] = $r;
$resSup->free();

$supplies = $supplies_all;

/* Kits paginados */
$kits = [];
$q = "SELECT k.ID_Kit, k.Name, k.FK_ID_Semester, k.Start_date, k.End_date, 
             s.Period, s.Year
      FROM kit k
      LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
      ORDER BY k.ID_Kit DESC
      LIMIT ? OFFSET ?";
$stmt = $conn->prepare($q);
$stmt->bind_param("ii", $registrosPorPagina, $offset);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $kits[] = $r;
$stmt->close();

/* =======================
   Datos del kit seleccionado (para ver_materials)
   ======================= */
$selectedKitId     = null;
$selectedKitInfo   = null;
$selectedMaterials = [];

if ($getAction === 'view_materials' && isset($_GET['id'])) {
    $selectedKitId = intval($_GET['id']);

    // Info del kit
    $stmt = $conn->prepare(
        "SELECT k.ID_Kit, k.Name, k.FK_ID_Semester, k.Start_date, k.End_date, 
                s.Period, s.Year
         FROM kit k
         LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
         WHERE k.ID_Kit = ?"
    );
    $stmt->bind_param("i", $selectedKitId);
    $stmt->execute();
    $res = $stmt->get_result();
    $selectedKitInfo = $res->fetch_assoc() ?: null;
    $stmt->close();

    // Materiales del kit
    $qm = $conn->prepare(
        "SELECT km.ID_KitMaterial, km.FK_ID_Supply, km.Unit, 
                sp.Name AS supply_name, sp.features, sp.marca, sp.Unit AS stock 
         FROM kit_material km 
         JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply 
         WHERE km.FK_ID_Kit = ?
         ORDER BY sp.Name"
    );
    $qm->bind_param("i", $selectedKitId);
    $qm->execute();
    $rm = $qm->get_result();
    while ($r = $rm->fetch_assoc()) $selectedMaterials[] = $r;
    $qm->close();
}
