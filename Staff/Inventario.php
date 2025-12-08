<?php
/* =======================
   Conexión y helpers
   ======================= */
require '../Conexiones/db.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

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
                $stmt = $conn->prepare(
                    "INSERT INTO kit (FK_ID_Semester, Start_date, End_date) VALUES (?, ?, ?)"
                );
                $stmt->bind_param("iss", $fk_semester, $start_date, $end_date);
                $stmt->execute();
                $stmt->close();
                go('kit_created');
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
                $stmt = $conn->prepare(
                    "UPDATE kit 
                     SET FK_ID_Semester = ?, Start_date = ?, End_date = ? 
                     WHERE ID_Kit = ?"
                );
                $stmt->bind_param("issi", $fk_semester, $start_date, $end_date, $id);
                $stmt->execute();
                $stmt->close();
                go('kit_updated');
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
                // Primero obtenemos el kit al que pertenece
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

        /* --- Crear suministro --- */
        case 'create_supply':
            $name = trim($_POST['s_name'] ?? '');
            $unit = trim($_POST['s_unit'] ?? '');

            if ($name !== '' && $unit !== '') {
                $stmt = $conn->prepare(
                    "INSERT INTO supplies (Name, Unit) VALUES (?, ?)"
                );
                $stmt->bind_param("ss", $name, $unit);
                $stmt->execute();
                $stmt->close();
                go('supply_created');
            } else {
                $error = "Nombre y unidad son obligatorios para crear un suministro.";
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario — Kits de Materiales</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Ajusta la ruta según estructura de carpetas -->
    <link rel="stylesheet" href="../assets/staff/inventario.css">
    <style>
        .pagination-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .pagination-buttons a, .pagination-buttons span {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            background: white;
            transition: all 0.3s;
        }
        
        .pagination-buttons a:hover {
            background: #f0f0f0;
        }
        
        .pagination-buttons .disabled {
            color: #ccc;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        /* Estilos para el header de detalles del kit */
        .kit-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .kit-details-header h4 {
            margin: 0;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .close-btn:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        /* Animación para el panel de detalles */
        .kit-details-panel {
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
<?php include '../Includes/HeaderMenuStaff.php'; ?>
<div class="wrap">
    <header>
        <h1>Inventario — Kits de Materiales</h1>
        <div class="small">
            Base:
            <strong><?php echo h($db_name ?? ''); ?></strong>
        </div>
    </header>

    <?php if (!empty($error)): ?>
        <div class="msg err card"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="msg ok card">
            <?php
                $map = [
                    'kit_created'      => 'Kit creado',
                    'kit_updated'      => 'Kit actualizado',
                    'kit_deleted'      => 'Kit eliminado',
                    'material_added'   => 'Material agregado',
                    'material_updated' => 'Material actualizado',
                    'material_deleted' => 'Material eliminado',
                    'supply_created'   => 'Suministro agregado'
                ];
                echo h($map[$msg] ?? $msg);
            ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Izquierda: Kits y materiales -->
        <div class="card">
            <div class="controls-row">
                <h3 style="margin:0">Kits registrados</h3>
                <div class="small">
                    Total: <?php echo $totalKits; ?> kits
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Semestre</th>
                    <th>Inicio — Fin</th>
                    <th class="small">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($kits)): ?>
                    <tr><td colspan="4" class="small">No hay kits. Crea uno a la derecha.</td></tr>
                <?php else: ?>
                    <?php foreach ($kits as $k): ?>
                        <tr>
                            <td><?php echo h($k['ID_Kit']); ?></td>
                            <td><?php echo h(($k['Period'] ?? '-') . ' ' . ($k['Year'] ?? '')); ?></td>
                            <td><?php echo h($k['Start_date']); ?> — <?php echo h($k['End_date']); ?></td>
                            <td class="actions">
                                <a class="view"
                                   href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=view_materials&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>">
                                   Ver materiales
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

            <!-- Solo botones Anterior/Siguiente -->
            <?php if ($totalPaginas > 1): ?>
                <div class="pagination-buttons">
                    <!-- Botón Anterior -->
                    <?php if ($paginaActual > 1): ?>
                        <a href="?pagina=<?php echo $paginaActual - 1; ?><?php echo $getAction ? '&action=' . h($getAction) : ''; ?><?php echo isset($_GET['id']) ? '&id=' . h($_GET['id']) : ''; ?>">
                            ‹ Anterior
                        </a>
                    <?php else: ?>
                        <span class="disabled">‹ Anterior</span>
                    <?php endif; ?>

                    <!-- Botón Siguiente -->
                    <?php if ($paginaActual < $totalPaginas): ?>
                        <a href="?pagina=<?php echo $paginaActual + 1; ?><?php echo $getAction ? '&action=' . h($getAction) : ''; ?><?php echo isset($_GET['id']) ? '&id=' . h($_GET['id']) : ''; ?>">
                            Siguiente ›
                        </a>
                    <?php else: ?>
                        <span class="disabled">Siguiente ›</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Materiales de un kit -->
            <?php
            if ($getAction === 'view_materials' && isset($_GET['id'])):
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
                <hr style="margin:16px 0">
                <div class="kit-details-panel">
                    <div class="kit-details-header">
                        <h4>
                            Materiales del kit 
                            <?php echo h($kit_info ? ($kit_info['Period'].' '.$kit_info['Year']) : $id_kit); ?>
                        </h4>
                        <button class="close-btn" onclick="window.location.href='<?php echo h($_SERVER['PHP_SELF']); ?>?pagina=<?php echo $paginaActual; ?>'">
                            ×
                        </button>
                    </div>
                    
                    <div class="small" style="margin-bottom:15px">
                        Período: 
                        <?php echo h($kit_info['Start_date'] ?? '-'); ?> — 
                        <?php echo h($kit_info['End_date'] ?? '-'); ?>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Unidad (supply)</th>
                                <th>Cantidad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materials)): ?>
                                <tr><td colspan="4" class="small">No hay materiales asignados a este kit.</td></tr>
                            <?php else: ?>
                                <?php foreach ($materials as $m): ?>
                                    <tr>
                                        <td><?php echo h($m['supply_name']); ?></td>
                                        <td class="small"><?php echo h($m['supply_unit']); ?></td>
                                        <td><?php echo h($m['Unit']); ?></td>
                                        <td class="actions">
                                            <a class="edit"
                                               href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=edit_material&id=<?php echo h($m['ID_KitMaterial']); ?>&pagina=<?php echo $paginaActual; ?>">
                                               Editar
                                            </a>
                                            <a class="del"
                                               href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=delete_material&id=<?php echo h($m['ID_KitMaterial']); ?>&pagina=<?php echo $paginaActual; ?>"
                                               onclick="return confirm('¿Eliminar material del kit?')">
                                               Eliminar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Form agregar material -->
                    <div style="margin-top:15px">
                        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                            <input type="hidden" name="action" value="add_material">
                            <input type="hidden" name="fk_kit" value="<?php echo h($id_kit); ?>">
                            <div style="flex:1 1 220px">
                                <label>Seleccionar suministro</label>
                                <select name="fk_supply" required>
                                    <option value="">-- elegir --</option>
                                    <?php foreach ($supplies as $s): ?>
                                        <option value="<?php echo h($s['ID_Supply']); ?>">
                                            <?php echo h($s['Name'] . ' (' . $s['Unit'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="width:130px">
                                <label>Cantidad</label>
                                <input type="number" name="unit" min="0" value="1" required>
                            </div>
                            <div style="align-self:end">
                                <button type="submit">Agregar material</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Derecha: crear/editar kit + supplies -->
        <aside>
            <div class="card card--with-gap">
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
                    <h3>Editar Kit #<?php echo h($id); ?></h3>
                    <form method="post">
                        <input type="hidden" name="action" value="update_kit">
                        <input type="hidden" name="id_kit" value="<?php echo h($id); ?>">

                        <label>Semestre</label>
                        <select name="fk_semester" required>
                            <option value="">-- elegir semestre --</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?php echo h($s['ID_Semester']); ?>"
                                    <?php echo ($kit_edit && $kit_edit['FK_ID_Semester']==$s['ID_Semester']) ? 'selected' : ''; ?>>
                                    <?php echo h($s['Period'].' '.$s['Year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Fecha inicio</label>
                        <input type="date" name="start_date"
                               value="<?php echo h($kit_edit['Start_date'] ?? ''); ?>" required>

                        <label>Fecha fin</label>
                        <input type="date" name="end_date"
                               value="<?php echo h($kit_edit['End_date'] ?? ''); ?>" required>

                        <div style="margin-top:10px">
                            <button type="submit">Guardar cambios</button>
                            <a href="inventario_kits.php?pagina=<?php echo $paginaActual; ?>" class="small link-cancel">Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h3>Crear nuevo Kit</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="create_kit">

                        <label>Semestre</label>
                        <select name="fk_semester" required>
                            <option value="">-- elegir semestre --</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?php echo h($s['ID_Semester']); ?>">
                                    <?php echo h($s['Period'].' '.$s['Year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Fecha inicio</label>
                        <input type="date" name="start_date" required>

                        <label>Fecha fin</label>
                        <input type="date" name="end_date" required>

                        <div style="margin-top:10px">
                            <button type="submit">Crear Kit</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3>Catálogo de suministros</h3>
                <table id="supplies-table" style="margin-bottom:8px">
                    <thead>
                        <tr><th>Nombre</th><th>Unidad</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($supplies)): ?>
                            <tr><td colspan="2" class="small">No hay suministros registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($supplies as $sp): ?>
                                <tr>
                                    <td><?php echo h($sp['Name']); ?></td>
                                    <td class="small"><?php echo h($sp['Unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Controles de paginación (JS los llenará) -->
                <div id="supplies-pager" class="small"></div>

                <hr class="divider">
                <form method="post">
                    <input type="hidden" name="action" value="create_supply">
                    <label>Agregar suministro</label>
                    <input type="text" name="s_name" placeholder="Nombre (ej. Lápiz)" required>
                    <label>Unidad</label>
                    <input type="text" name="s_unit" placeholder="ej. piezas / cajas" required>
                    <div class="form-actions">
                        <button type="submit">Agregar suministro</button>
                    </div>
                </form>
            </div>
        </aside>
    </div>

    <!-- Editar material (overlay simple) -->
    <?php if ($getAction === 'edit_material' && isset($_GET['id'])): ?>
        <?php
        $idmat = intval($_GET['id']);
        $stmt = $conn->prepare(
            "SELECT km.ID_KitMaterial, km.Unit, km.FK_ID_Kit, sp.Name 
             FROM kit_material km 
             JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply 
             WHERE km.ID_KitMaterial = ?"
        );
        $stmt->bind_param("i", $idmat);
        $stmt->execute();
        $res      = $stmt->get_result();
        $mat_edit = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($mat_edit):
        ?>
        <div class="edit-material-wrap">
            <div class="card">
                <div class="kit-details-header">
                    <h3>Editar material: <?php echo h($mat_edit['Name']); ?></h3>
                    <button class="close-btn" onclick="window.location.href='<?php echo h($_SERVER['PHP_SELF']); ?>?action=view_materials&id=<?php echo h($mat_edit['FK_ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>'">
                        ×
                    </button>
                </div>
                <form method="post" class="edit-material-form">
                    <input type="hidden" name="action" value="update_material">
                    <input type="hidden" name="id_material" value="<?php echo h($mat_edit['ID_KitMaterial']); ?>">
                    <div class="edit-material-field">
                        <label>Cantidad</label>
                        <input type="number" name="unit" min="0"
                               value="<?php echo h($mat_edit['Unit']); ?>" required>
                    </div>
                    <div>
                        <button type="submit">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Paginación front-end para la tabla de suministros -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const perPage = 5; // 5 registros por vista
    const table   = document.getElementById('supplies-table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));

    // si solo hay la fila de "no hay suministros" o <= perPage, no paginamos
    if (rows.length <= perPage) return;

    const pager = document.getElementById('supplies-pager');
    let currentPage = 1;
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));

    const btnPrev = document.createElement('button');
    btnPrev.textContent = '◀ Anterior';
    btnPrev.type = 'button';
    btnPrev.className = 'pager-btn';

    const btnNext = document.createElement('button');
    btnNext.textContent = 'Siguiente ▶';
    btnNext.type = 'button';
    btnNext.className = 'pager-btn';

    const info = document.createElement('span');
    info.className = 'pager-info';

    pager.appendChild(btnPrev);
    pager.appendChild(info);
    pager.appendChild(btnNext);

    function renderPage(page) {
        currentPage = page;

        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;

        rows.forEach((tr, idx) => {
            tr.style.display = (idx >= start && idx < end) ? '' : 'none';
        });

        info.textContent = 'Página ' + currentPage + ' de ' + totalPages;
        btnPrev.disabled = (currentPage === 1);
        btnNext.disabled = (currentPage === totalPages);
    }

    btnPrev.addEventListener('click', function () {
        if (currentPage > 1) renderPage(currentPage - 1);
    });

    btnNext.addEventListener('click', function () {
        if (currentPage < totalPages) renderPage(currentPage + 1);
    });

    // Primera vista
    renderPage(1);
});
</script>
</body>
</html>