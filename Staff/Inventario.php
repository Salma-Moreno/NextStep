<?php
/* =======================
   Conexión y helpers
   ======================= */
require '../Conexiones/db.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function limpiarCadena($cadena) {
    $cadena = trim($cadena);
    $cadena = preg_replace('/\s+/', ' ', $cadena);
    return $cadena;
}

$error = '';
$msg   = $_GET['msg'] ?? null;

/* Helper para redirigir con mensaje */
function go($msg, $extra = ''){
    $qs = 'msg=' . urlencode($msg);
    if ($extra !== '') $qs .= '&' . ltrim($extra, '&?');
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $qs);
    exit;
}

/* =======================
   MANEJO DE ACCIONES POST
   ======================= */
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        /* --- Crear kit --- */
        case 'create_kit':
            $fk_semester = intval($_POST['fk_semester'] ?? 0);
            $start_date  = $_POST['start_date'] ?? null;
            $end_date    = $_POST['end_date'] ?? null;

            if ($fk_semester && $start_date && $end_date) {
                // Permite múltiples kits en el mismo semestre y período
                // Solo verifica duplicados exactos de kit (mismo semestre + mismas fechas)
                $stmtCheck = $conn->prepare(
                    "SELECT ID_Kit FROM kit 
                     WHERE FK_ID_Semester = ? 
                     AND Start_date = ? 
                     AND End_date = ?"
                );
                $stmtCheck->bind_param("iss", $fk_semester, $start_date, $end_date);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                
                if ($resultCheck->num_rows > 0) {
                    $error = "Ya existe un kit con exactamente el mismo semestre y fechas.";
                    $stmtCheck->close();
                } else {
                    $stmtCheck->close();
                    $stmt = $conn->prepare(
                        "INSERT INTO kit (FK_ID_Semester, Start_date, End_date) VALUES (?, ?, ?)"
                    );
                    $stmt->bind_param("iss", $fk_semester, $start_date, $end_date);
                    $stmt->execute();
                    $stmt->close();
                    go('kit_created');
                }
            } else {
                $error = "Todos los campos son obligatorios para crear un kit.";
            }
            break;

        /* --- Actualizar kit --- */
        case 'update_kit':
            $id          = intval($_POST['id_kit'] ?? 0);
            $fk_semester = intval($_POST['fk_semester'] ?? 0);
            $start_date  = $_POST['start_date'] ?? null;
            $end_date    = $_POST['end_date'] ?? null;

            if ($id && $fk_semester && $start_date && $end_date) {
                // Verificar duplicados excluyendo el kit actual
                $stmtCheck = $conn->prepare(
                    "SELECT ID_Kit FROM kit 
                     WHERE FK_ID_Semester = ? 
                     AND Start_date = ? 
                     AND End_date = ?
                     AND ID_Kit != ?"
                );
                $stmtCheck->bind_param("issi", $fk_semester, $start_date, $end_date, $id);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                
                if ($resultCheck->num_rows > 0) {
                    $error = "Ya existe otro kit con exactamente el mismo semestre y fechas.";
                    $stmtCheck->close();
                } else {
                    $stmtCheck->close();
                    $stmt = $conn->prepare(
                        "UPDATE kit 
                         SET FK_ID_Semester = ?, Start_date = ?, End_date = ? 
                         WHERE ID_Kit = ?"
                    );
                    $stmt->bind_param("issi", $fk_semester, $start_date, $end_date, $id);
                    $stmt->execute();
                    $stmt->close();
                    go('kit_updated');
                }
            } else {
                $error = "Todos los campos son obligatorios para editar un kit.";
            }
            break;

        /* --- Agregar material a un kit --- */
        case 'add_material':
            $fk_kit    = intval($_POST['fk_kit'] ?? 0);
            $fk_supply = intval($_POST['fk_supply'] ?? 0);
            $unit      = intval($_POST['unit'] ?? 0);

            if ($fk_kit && $fk_supply && $unit >= 0) {
                // Verificar si este material ya existe en ESTE kit
                $stmtCheck = $conn->prepare(
                    "SELECT ID_KitMaterial, Unit 
                     FROM kit_material 
                     WHERE FK_ID_Kit = ? AND FK_ID_Supply = ?"
                );
                $stmtCheck->bind_param("ii", $fk_kit, $fk_supply);
                $stmtCheck->execute();
                $res = $stmtCheck->get_result();

                if ($row = $res->fetch_assoc()) {
                    // Material ya existe en este kit - solo actualizar cantidad
                    $newUnit = intval($row['Unit']) + $unit;
                    $stmtCheck->close();

                    $up = $conn->prepare(
                        "UPDATE kit_material SET Unit = ? WHERE ID_KitMaterial = ?"
                    );
                    $up->bind_param("ii", $newUnit, $row['ID_KitMaterial']);
                    $up->execute();
                    $up->close();
                    
                    go('material_updated_in_kit', 'action=view_materials&id=' . $fk_kit);
                } else {
                    $stmtCheck->close();
                    
                    // Agregar el material sin verificar duplicados en otros kits
                    $ins = $conn->prepare(
                        "INSERT INTO kit_material (FK_ID_Kit, FK_ID_Supply, Unit) 
                         VALUES (?, ?, ?)"
                    );
                    $ins->bind_param("iii", $fk_kit, $fk_supply, $unit);
                    $ins->execute();
                    $ins->close();
                    
                    go('material_added', 'action=view_materials&id=' . $fk_kit);
                }
            } else {
                $error = "Datos inválidos al intentar agregar material.";
            }
            break;

        /* --- Crear suministro --- */
        case 'create_supply':
            $name = limpiarCadena($_POST['s_name'] ?? '');
            $unit = limpiarCadena($_POST['s_unit'] ?? '');
            
            $nombreValido = preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s]+$/', $name) && 
                            strlen(trim($name)) >= 2 && 
                            !preg_match('/^\s+$/', $name);
            
            $unidadValida = preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\/\-\s]+$/', $unit) && 
                            strlen(trim($unit)) >= 1 && 
                            !preg_match('/^\s+$/', $unit);
            
            if ($nombreValido && $unidadValida) {
                $nameNormalized = preg_replace('/\s+/', ' ', trim($name));
                $unitNormalized = preg_replace('/\s+/', ' ', trim($unit));
                
                $stmtCheck = $conn->prepare(
                    "SELECT ID_Supply, Name, Unit 
                     FROM supplies 
                     WHERE LOWER(REPLACE(Name, ' ', '')) = LOWER(REPLACE(?, ' ', '')) 
                     AND LOWER(REPLACE(Unit, ' ', '')) = LOWER(REPLACE(?, ' ', ''))"
                );
                $stmtCheck->bind_param("ss", $nameNormalized, $unitNormalized);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                
                if ($resultCheck->num_rows > 0) {
                    $existing = $resultCheck->fetch_assoc();
                    $error = "Ya existe un suministro con el mismo nombre y unidad: '" . 
                             h($existing['Name']) . "' (" . h($existing['Unit']) . ")";
                    $stmtCheck->close();
                } else {
                    $stmtCheck->close();
                    
                    $stmtCheckName = $conn->prepare(
                        "SELECT ID_Supply, Name, Unit 
                         FROM supplies 
                         WHERE LOWER(REPLACE(Name, ' ', '')) = LOWER(REPLACE(?, ' ', ''))"
                    );
                    $stmtCheckName->bind_param("s", $nameNormalized);
                    $stmtCheckName->execute();
                    $resultCheckName = $stmtCheckName->get_result();
                    
                    if ($resultCheckName->num_rows > 0) {
                        $existing = $resultCheckName->fetch_assoc();
                        $error = "Ya existe un suministro con nombre similar: '" . 
                                 h($existing['Name']) . "' (" . h($existing['Unit']) . "). 
                                 Si es el mismo producto con diferente unidad, edítelo desde el catálogo.";
                        $stmtCheckName->close();
                    } else {
                        $stmtCheckName->close();
                        $stmt = $conn->prepare(
                            "INSERT INTO supplies (Name, Unit) VALUES (?, ?)"
                        );
                        $stmt->bind_param("ss", $nameNormalized, $unitNormalized);
                        $stmt->execute();
                        $stmt->close();
                        go('supply_created');
                    }
                }
            } else {
                if (!$nombreValido) {
                    $error = "Nombre inválido. Solo se permiten letras, números y espacios. Mínimo 2 caracteres.";
                } elseif (!$unidadValida) {
                    $error = "Unidad inválida. Use letras, números, espacios, guiones o barras. Mínimo 1 caracter.";
                }
            }
            break;
    }
}

/* =======================
   ACCIONES GET (delete / view)
   ======================= */
$getAction = $_GET['action'] ?? '';

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

// Calcular offset
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Obtener el total de kits
$stmtTotal = $conn->prepare("SELECT COUNT(*) as total FROM kit");
$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$totalKits = $resultTotal->fetch_assoc()['total'];
$stmtTotal->close();

$totalPaginas = ceil($totalKits / $registrosPorPagina);
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

/* Supplies (TODOS, paginamos con JS) */
$supplies = [];
$resSup = $conn->query(
    "SELECT ID_Supply, Name, Unit 
     FROM supplies 
     ORDER BY Name"
);
while ($r = $resSup->fetch_assoc()) $supplies[] = $r;
$resSup->free();

/* Kits PAGINADOS */
$kits = [];
$q = "SELECT k.ID_Kit, k.FK_ID_Semester, k.Start_date, k.End_date, 
             s.Period, s.Year,
             (SELECT COUNT(*) FROM kit_material WHERE FK_ID_Kit = k.ID_Kit) as material_count
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario — Kits de Materiales</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../assets/staff/inventario.css">
    <style>
        /* Estilos para la tabla principal */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .main-table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        .main-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .main-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Estilos para acciones */
        .actions {
            white-space: nowrap;
            text-align: center;
        }
        
        .actions a {
            display: inline-block;
            padding: 4px 8px;
            margin: 0 2px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 0.85em;
            transition: all 0.2s;
        }
        
        .actions a.view {
            background-color: #17a2b8;
            color: white;
        }
        
        .actions a.view:hover {
            background-color: #138496;
        }
        
        .actions a.edit {
            background-color: #28a745;
            color: white;
        }
        
        .actions a.edit:hover {
            background-color: #218838;
        }
        
        .actions a.del {
            background-color: #dc3545;
            color: white;
        }
        
        .actions a.del:hover {
            background-color: #c82333;
        }
        
        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            padding: 10px;
        }
        
        .pagination a, .pagination span {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            text-decoration: none;
            color: #007bff;
            background: white;
            font-size: 0.9em;
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination .disabled {
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Estilos para cards */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Grid layout */
        .grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
            margin-top: 20px;
        }
        
        @media (max-width: 1024px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Header limpio */
        .clean-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .clean-header h1 {
            margin: 0;
            color: #333;
            font-size: 1.8em;
        }
        
        /* Badge para contador de materiales */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 0.75em;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 10px;
            background-color: #6c757d;
            color: white;
        }
        
        /* Estilos para formularios */
        select, input[type="date"], input[type="text"], input[type="number"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9em;
            box-sizing: border-box;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
            font-size: 0.9em;
        }
        
        button[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }
        
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        
        /* Estilos para mensajes */
        .msg {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }
        
        .msg.ok {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .msg.err {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Estilos responsivos */
        @media (max-width: 768px) {
            .main-table {
                font-size: 0.85em;
            }
            
            .main-table th,
            .main-table td {
                padding: 8px 10px;
            }
            
            .actions a {
                padding: 3px 6px;
                font-size: 0.8em;
                margin: 1px;
            }
        }
        
        /* Eliminar estilos no deseados */
        .small {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .material-count {
            display: none; /* Ocultamos el contador si no se quiere mostrar */
        }
    </style>
</head>
<body>
<?php include '../Includes/HeaderMenuStaff.php'; ?>
<div class="wrap">
    <div class="clean-header">
        <h1>Kits de Materiales</h1>
        <div class="small">
            Total: <strong><?php echo $totalKits; ?> kits</strong>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="msg err"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="msg ok">
            <?php
                $map = [
                    'kit_created'           => 'Kit creado correctamente',
                    'kit_updated'           => 'Kit actualizado correctamente',
                    'kit_deleted'           => 'Kit eliminado correctamente',
                    'material_added'        => 'Material agregado al kit',
                    'material_updated'      => 'Material actualizado',
                    'material_updated_in_kit' => 'Cantidad actualizada',
                    'material_deleted'      => 'Material eliminado',
                    'supply_created'        => 'Suministro agregado'
                ];
                echo h($map[$msg] ?? $msg);
            ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Columna principal: Tabla de kits -->
        <div>
            <div class="card">
                <table class="main-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Semestre</th>
                            <th>Inicio — Fin</th>
                            <th class="actions">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kits)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #6c757d; padding: 30px;">
                                    No hay kits registrados. Crea uno nuevo en el panel de la derecha.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kits as $k): ?>
                                <tr>
                                    <td><strong>#<?php echo h($k['ID_Kit']); ?></strong></td>
                                    <td><?php echo h(($k['Period'] ?? '-') . ' ' . ($k['Year'] ?? '')); ?></td>
                                    <td>
                                        <?php echo h($k['Start_date']); ?> — <?php echo h($k['End_date']); ?>
                                        <?php if ($k['material_count'] > 0): ?>
                                            <span class="badge"><?php echo h($k['material_count']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <a class="view" 
                                           href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=view_materials&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>">
                                           Ver
                                        </a>
                                        <a class="edit"
                                           href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=edit_kit&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>">
                                           Editar
                                        </a>
                                        <a class="del"
                                           href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=delete_kit&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>"
                                           onclick="return confirm('¿Eliminar kit y sus materiales?')">
                                           Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Paginación simple -->
                <?php if ($totalPaginas > 1): ?>
                    <div class="pagination">
                        <?php if ($paginaActual > 1): ?>
                            <a href="?pagina=<?php echo $paginaActual - 1; ?>">
                                ‹ Anterior
                            </a>
                        <?php else: ?>
                            <span class="disabled">‹ Anterior</span>
                        <?php endif; ?>

                        <span class="small">
                            Página <?php echo $paginaActual; ?> de <?php echo $totalPaginas; ?>
                        </span>

                        <?php if ($paginaActual < $totalPaginas): ?>
                            <a href="?pagina=<?php echo $paginaActual + 1; ?>">
                                Siguiente ›
                            </a>
                        <?php else: ?>
                            <span class="disabled">Siguiente ›</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Materiales de un kit (se muestra solo cuando se selecciona Ver) -->
            <?php if ($getAction === 'view_materials' && isset($_GET['id'])): ?>
                <?php
                $id_kit = intval($_GET['id']);
                $stmt = $conn->prepare(
                    "SELECT k.ID_Kit, k.Start_date, k.End_date, s.Period, s.Year 
                     FROM kit k 
                     LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester 
                     WHERE k.ID_Kit = ?"
                );
                $stmt->bind_param("i", $id_kit);
                $stmt->execute();
                $res      = $stmt->get_result();
                $kit_info = $res->fetch_assoc() ?: null;
                $stmt->close();

                $materials = [];
                $qm = $conn->prepare(
                    "SELECT km.ID_KitMaterial, km.FK_ID_Supply, km.Unit, 
                            sp.Name AS supply_name, sp.Unit AS supply_unit 
                     FROM kit_material km 
                     JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply 
                     WHERE km.FK_ID_Kit = ?
                     ORDER BY sp.Name"
                );
                $qm->bind_param("i", $id_kit);
                $qm->execute();
                $rm = $qm->get_result();
                while ($r = $rm->fetch_assoc()) $materials[] = $r;
                $qm->close();
                ?>
                
                <div class="card" style="margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #dee2e6;">
                        <h3 style="margin: 0; font-size: 1.2em;">
                            Materiales del Kit #<?php echo h($id_kit); ?>
                            <span class="small">(<?php echo h($kit_info['Period'] ?? ''); ?> <?php echo h($kit_info['Year'] ?? ''); ?>)</span>
                        </h3>
                        <a href="<?php echo h($_SERVER['PHP_SELF']); ?>?pagina=<?php echo $paginaActual; ?>" 
                           style="color: #6c757d; text-decoration: none; font-size: 1.5em;">×</a>
                    </div>
                    
                    <div style="margin-bottom: 15px; color: #6c757d;">
                        <strong>Período:</strong> <?php echo h($kit_info['Start_date'] ?? '-'); ?> — <?php echo h($kit_info['End_date'] ?? '-'); ?>
                    </div>

                    <?php if (empty($materials)): ?>
                        <div style="text-align: center; padding: 20px; color: #6c757d;">
                            No hay materiales en este kit.
                        </div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
                            <thead>
                                <tr>
                                    <th style="text-align: left; padding: 8px; border-bottom: 2px solid #dee2e6;">Material</th>
                                    <th style="text-align: left; padding: 8px; border-bottom: 2px solid #dee2e6;">Unidad</th>
                                    <th style="text-align: left; padding: 8px; border-bottom: 2px solid #dee2e6;">Cantidad</th>
                                    <th style="text-align: center; padding: 8px; border-bottom: 2px solid #dee2e6;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $m): ?>
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6;"><?php echo h($m['supply_name']); ?></td>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; color: #6c757d;"><?php echo h($m['supply_unit']); ?></td>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;"><?php echo h($m['Unit']); ?></td>
                                        <td style="padding: 8px; border-bottom: 1px solid #dee2e6; text-align: center;">
                                            <a href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=edit_material&id=<?php echo h($m['ID_KitMaterial']); ?>&pagina=<?php echo $paginaActual; ?>"
                                               style="color: #28a745; text-decoration: none; margin: 0 5px;">Editar</a>
                                            <a href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=delete_material&id=<?php echo h($m['ID_KitMaterial']); ?>&pagina=<?php echo $paginaActual; ?>"
                                               onclick="return confirm('¿Eliminar este material del kit?')"
                                               style="color: #dc3545; text-decoration: none; margin: 0 5px;">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Formulario para agregar material -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <h4 style="margin-bottom: 10px; font-size: 1em;">Agregar material</h4>
                        <form method="post" style="display: flex; gap: 10px; align-items: flex-end;">
                            <input type="hidden" name="action" value="add_material">
                            <input type="hidden" name="fk_kit" value="<?php echo h($id_kit); ?>">
                            
                            <div style="flex: 1;">
                                <label>Material</label>
                                <select name="fk_supply" required style="width: 100%;">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach ($supplies as $s): ?>
                                        <option value="<?php echo h($s['ID_Supply']); ?>">
                                            <?php echo h($s['Name'] . ' (' . $s['Unit'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="width: 100px;">
                                <label>Cantidad</label>
                                <input type="number" name="unit" min="0" value="1" required style="width: 100%;">
                            </div>
                            
                            <div>
                                <button type="submit" style="white-space: nowrap;">Agregar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna lateral: Formularios -->
        <aside>
            <!-- Formulario para crear/editar kit -->
            <div class="card">
                <?php if ($getAction === 'edit_kit' && isset($_GET['id'])): ?>
                    <?php
                    $id = intval($_GET['id']);
                    $stmt = $conn->prepare(
                        "SELECT ID_Kit, FK_ID_Semester, Start_date, End_date 
                         FROM kit WHERE ID_Kit = ?"
                    );
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res      = $stmt->get_result();
                    $kit_edit = $res->fetch_assoc() ?: null;
                    $stmt->close();
                    ?>
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.2em;">Editar Kit #<?php echo h($id); ?></h3>
                    <form method="post">
                        <input type="hidden" name="action" value="update_kit">
                        <input type="hidden" name="id_kit" value="<?php echo h($id); ?>">

                        <div style="margin-bottom: 10px;">
                            <label>Semestre</label>
                            <select name="fk_semester" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo h($s['ID_Semester']); ?>"
                                        <?php echo ($kit_edit && $kit_edit['FK_ID_Semester']==$s['ID_Semester']) ? 'selected' : ''; ?>>
                                        <?php echo h($s['Period'].' '.$s['Year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label>Fecha inicio</label>
                            <input type="date" name="start_date"
                                   value="<?php echo h($kit_edit['Start_date'] ?? ''); ?>" required>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label>Fecha fin</label>
                            <input type="date" name="end_date"
                                   value="<?php echo h($kit_edit['End_date'] ?? ''); ?>" required>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit">Guardar cambios</button>
                            <a href="<?php echo h($_SERVER['PHP_SELF']); ?>?pagina=<?php echo $paginaActual; ?>" 
                               style="padding: 8px 16px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em;">
                               Cancelar
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.2em;">Crear nuevo Kit</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="create_kit">

                        <div style="margin-bottom: 10px;">
                            <label>Semestre</label>
                            <select name="fk_semester" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?php echo h($s['ID_Semester']); ?>">
                                        <?php echo h($s['Period'].' '.$s['Year']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label>Fecha inicio</label>
                            <input type="date" name="start_date" required>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label>Fecha fin</label>
                            <input type="date" name="end_date" required>
                        </div>

                        <button type="submit">Crear Kit</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Catálogo de suministros -->
            <div class="card">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.2em;">Catálogo de suministros</h3>
                
                <div style="max-height: 200px; overflow-y: auto; margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tbody>
                            <?php if (empty($supplies)): ?>
                                <tr>
                                    <td style="padding: 8px; color: #6c757d; text-align: center;">
                                        No hay suministros
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($supplies as $sp): ?>
                                    <tr>
                                        <td style="padding: 6px 8px; border-bottom: 1px solid #f0f0f0;">
                                            <?php echo h($sp['Name']); ?>
                                            <span style="color: #6c757d; font-size: 0.85em;">
                                                (<?php echo h($sp['Unit']); ?>)
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding-top: 15px; border-top: 1px solid #dee2e6;">
                    <h4 style="margin-bottom: 10px; font-size: 1em;">Agregar suministro</h4>
                    <form method="post">
                        <input type="hidden" name="action" value="create_supply">
                        
                        <div style="margin-bottom: 10px;">
                            <label>Nombre</label>
                            <input type="text" name="s_name" placeholder="Ej. Lápiz" required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>Unidad</label>
                            <input type="text" name="s_unit" placeholder="Ej. piezas" required>
                        </div>
                        
                        <button type="submit">Agregar suministro</button>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Script para auto-ocultar mensajes -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.msg');
    
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(function() {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 500);
        }, 3000); // 3 segundos
    });
});
</script>
</body>
</html>