<?php
/* -----------------------
   Conexión
   ----------------------- */
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    die("Falló conexión a BD: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

/* -----------------------
   Helpers
   ----------------------- */
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* -----------------------
   Procesar acciones (POST/GET)
   ----------------------- */

/* --- Crear kit --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_kit') {
    $fk_semester = intval($_POST['fk_semester'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    if ($fk_semester && $start_date && $end_date) {
        $stmt = $mysqli->prepare("INSERT INTO kit (FK_ID_Semester, Start_date, End_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $fk_semester, $start_date, $end_date);
        $stmt->execute();
        $stmt->close();
        header("Location: inventario_kits.php?msg=kit_created");
        exit;
    } else {
        $error = "Todos los campos son obligatorios para crear un kit.";
    }
}

/* --- Editar kit --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_kit') {
    $id = intval($_POST['id_kit'] ?? 0);
    $fk_semester = intval($_POST['fk_semester'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;

    if ($id && $fk_semester && $start_date && $end_date) {
        $stmt = $mysqli->prepare("UPDATE kit SET FK_ID_Semester = ?, Start_date = ?, End_date = ? WHERE ID_Kit = ?");
        $stmt->bind_param("issi", $fk_semester, $start_date, $end_date, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: inventario_kits.php?msg=kit_updated");
        exit;
    } else {
        $error = "Todos los campos son obligatorios para editar un kit.";
    }
}

/* --- Eliminar kit --- */
if (isset($_GET['action']) && $_GET['action'] === 'delete_kit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id) {
        // Primero eliminar kit_material asociados (por integridad)
        $stmt = $mysqli->prepare("DELETE FROM kit_material WHERE FK_ID_Kit = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Luego eliminar el kit
        $stmt = $mysqli->prepare("DELETE FROM kit WHERE ID_Kit = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        header("Location: inventario_kits.php?msg=kit_deleted");
        exit;
    }
}

/* --- Agregar material a un kit --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_material') {
    $fk_kit = intval($_POST['fk_kit'] ?? 0);
    $fk_supply = intval($_POST['fk_supply'] ?? 0);
    $unit = intval($_POST['unit'] ?? 0);

    if ($fk_kit && $fk_supply && $unit >= 0) {
        // Si ya existe esa combinación FK_ID_Kit + FK_ID_Supply, actualizar la unidad (sumar)
        $stmt = $mysqli->prepare("SELECT ID_KitMaterial, Unit FROM kit_material WHERE FK_ID_Kit = ? AND FK_ID_Supply = ?");
        $stmt->bind_param("ii", $fk_kit, $fk_supply);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $newUnit = intval($row['Unit']) + $unit;
            $stmt->close();
            $up = $mysqli->prepare("UPDATE kit_material SET Unit = ? WHERE ID_KitMaterial = ?");
            $up->bind_param("ii", $newUnit, $row['ID_KitMaterial']);
            $up->execute();
            $up->close();
        } else {
            $stmt->close();
            $ins = $mysqli->prepare("INSERT INTO kit_material (FK_ID_Kit, FK_ID_Supply, Unit) VALUES (?, ?, ?)");
            $ins->bind_param("iii", $fk_kit, $fk_supply, $unit);
            $ins->execute();
            $ins->close();
        }

        header("Location: inventario_kits.php?action=view_materials&id=" . $fk_kit . "&msg=material_added");
        exit;
    } else {
        $error = "Datos inválidos al intentar agregar material.";
    }
}

/* --- Editar material --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_material') {
    $id = intval($_POST['id_material'] ?? 0);
    $unit = intval($_POST['unit'] ?? 0);

    if ($id && $unit >= 0) {
        $stmt = $mysqli->prepare("UPDATE kit_material SET Unit = ? WHERE ID_KitMaterial = ?");
        $stmt->bind_param("ii", $unit, $id);
        $stmt->execute();
        $stmt->close();

        // obtener fk_kit para redirigir
        $stmt2 = $mysqli->prepare("SELECT FK_ID_Kit FROM kit_material WHERE ID_KitMaterial = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $fk_kit = $res2->fetch_assoc()['FK_ID_Kit'] ?? 0;
        $stmt2->close();

        header("Location: inventario_kits.php?action=view_materials&id=" . $fk_kit . "&msg=material_updated");
        exit;
    } else {
        $error = "Datos inválidos al intentar editar material.";
    }
}

/* --- Eliminar material --- */
if (isset($_GET['action']) && $_GET['action'] === 'delete_material' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id) {
        // obtener fk_kit antes de borrar para redirigir
        $stmt = $mysqli->prepare("SELECT FK_ID_Kit FROM kit_material WHERE ID_KitMaterial = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $fk_kit = $res->fetch_assoc()['FK_ID_Kit'] ?? 0;
        $stmt->close();

        $del = $mysqli->prepare("DELETE FROM kit_material WHERE ID_KitMaterial = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        header("Location: inventario_kits.php?action=view_materials&id=" . $fk_kit . "&msg=material_deleted");
        exit;
    }
}

/* --- Agregar supply (suministro) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_supply') {
    $name = trim($_POST['s_name'] ?? '');
    $unit = trim($_POST['s_unit'] ?? '');

    if ($name !== '' && $unit !== '') {
        $stmt = $mysqli->prepare("INSERT INTO supplies (Name, Unit) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $unit);
        $stmt->execute();
        $stmt->close();
        header("Location: inventario_kits.php?msg=supply_created");
        exit;
    } else {
        $error = "Nombre y unidad son obligatorios para crear un suministro.";
    }
}

/* -----------------------
   Consultas auxiliares para mostrar datos
   ----------------------- */

/* Obtener semestres para select */
$semesters = [];
$res = $mysqli->query("SELECT ID_Semester, Period, Year FROM semester ORDER BY Year DESC, Period ASC");
while ($r = $res->fetch_assoc()) $semesters[] = $r;
$res->free();

/* Obtener supplies para select */
$supplies = [];
$res = $mysqli->query("SELECT ID_Supply, Name, Unit FROM supplies ORDER BY Name");
while ($r = $res->fetch_assoc()) $supplies[] = $r;
$res->free();

/* Obtener lista de kits (con info de semestre) */
$kits = [];
$q = "SELECT k.ID_Kit, k.FK_ID_Semester, k.Start_date, k.End_date, s.Period, s.Year
      FROM kit k
      LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
      ORDER BY k.ID_Kit DESC";
$res = $mysqli->query($q);
while ($r = $res->fetch_assoc()) $kits[] = $r;
$res->free();

/* Mensajes */
$msg = $_GET['msg'] ?? null;

/* -----------------------
   VISTA (HTML + CSS)
   ----------------------- */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario — Kits de Materiales</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        :root{ --primary:#1e88e5; --accent:#ffb300; --danger:#e53935; --muted:#666; --card:#fff; }
        *{box-sizing:border-box}
        body{font-family:Inter,Segoe UI,Arial; margin:0; background:#f4f7fb; color:#222}
        .wrap{max-width:1150px; margin:28px auto; padding:20px}
        header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
        header h1{margin:0;font-size:20px;color:var(--primary)}
        .grid{display:grid;grid-template-columns: 1fr 420px; gap:18px}
        .card{background:var(--card); padding:16px; border-radius:10px; box-shadow:0 6px 18px rgba(20,30,50,0.06)}
        form .row{display:flex;gap:8px}
        label{display:block;font-size:13px;color:var(--muted); margin-bottom:6px}
        input[type="text"], input[type="date"], input[type="number"], select, textarea{
            width:100%; padding:10px;border:1px solid #e3e7ee; border-radius:8px; font-size:14px;
        }
        textarea{min-height:70px; resize:vertical}
        button{background:var(--primary); color:white; padding:10px 12px;border:none;border-radius:8px; cursor:pointer}
        button.secondary{background:#6c757d}
        button.danger{background:var(--danger)}
        table{width:100%; border-collapse:collapse; margin-top:12px}
        th, td{padding:10px 8px; text-align:left; border-bottom:1px solid #eef2f7; font-size:14px}
        th{background:linear-gradient(90deg,var(--primary),#1565c0); color:white; border-bottom:none}
        .small{font-size:13px;color:var(--muted)}
        .actions a{margin-right:6px; text-decoration:none; padding:6px 9px; border-radius:6px; color:white; font-size:13px}
        .edit{background:#4caf50} .del{background:var(--danger)} .view{background:#ff9800}
        .badge{display:inline-block;padding:5px 8px;border-radius:999px;background:#eef6ff;color:var(--primary);font-weight:600}
        .msg{margin:12px 0;padding:10px;border-radius:8px}
        .msg.ok{background:#e8f7ee;color:#23642b}
        .msg.err{background:#fdecea;color:#a12b2b}
        /* responsive */
        @media (max-width:980px){ .grid{grid-template-columns:1fr} }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Inventario — Kits de Materiales</h1>
        <div class="small">Base: <strong><?php echo h($db_name); ?></strong></div>
    </header>

    <?php if (!empty($error)): ?>
        <div class="msg err card"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($msg): ?>
        <div class="msg ok card">
            <?php
                $map = [
                    'kit_created'=>'Kit creado',
                    'kit_updated'=>'Kit actualizado',
                    'kit_deleted'=>'Kit eliminado',
                    'material_added'=>'Material agregado',
                    'material_updated'=>'Material actualizado',
                    'material_deleted'=>'Material eliminado',
                    'supply_created'=>'Suministro agregado'
                ];
                echo h($map[$msg] ?? $msg);
            ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- Izquierda: Listado de kits y materiales -->
        <div class="card">
            <h3 style="margin-top:0">Kits registrados</h3>

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
                                <a class="view" href="inventario_kits.php?action=view_materials&id=<?php echo h($k['ID_Kit']); ?>">Ver materiales</a>
                                <a class="edit" href="inventario_kits.php?action=edit_kit&id=<?php echo h($k['ID_Kit']); ?>">Editar</a>
                                <a class="del" href="inventario_kits.php?action=delete_kit&id=<?php echo h($k['ID_Kit']); ?>" onclick="return confirm('¿Eliminar kit y sus materiales?')">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Si se está viendo materiales de un kit -->
            <?php
            if (isset($_GET['action']) && $_GET['action'] === 'view_materials' && isset($_GET['id'])):
                $id_kit = intval($_GET['id']);
                // Obtener datos del kit
                $stmt = $mysqli->prepare("SELECT k.ID_Kit, k.Start_date, k.End_date, s.Period, s.Year FROM kit k LEFT JOIN semester s ON k.FK_ID_Semester = s.ID_Semester WHERE k.ID_Kit = ?");
                $stmt->bind_param("i", $id_kit);
                $stmt->execute();
                $res = $stmt->get_result();
                $kit_info = $res->fetch_assoc() ?: null;
                $stmt->close();

                // Obtener materiales del kit
                $materials = [];
                $qm = $mysqli->prepare("SELECT km.ID_KitMaterial, km.FK_ID_Supply, km.Unit, sp.Name AS supply_name, sp.Unit AS supply_unit FROM kit_material km JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply WHERE km.FK_ID_Kit = ? ORDER BY sp.Name");
                $qm->bind_param("i", $id_kit);
                $qm->execute();
                $rm = $qm->get_result();
                while ($r = $rm->fetch_assoc()) $materials[] = $r;
                $qm->close();
            ?>
                <hr style="margin:16px 0">
                <h4>Materiales del kit <?php echo h($kit_info ? ($kit_info['Period'].' '.$kit_info['Year']) : $id_kit); ?></h4>
                <div class="small" style="margin-bottom:8px">Período: <?php echo h($kit_info['Start_date'] ?? '-'); ?> — <?php echo h($kit_info['End_date'] ?? '-'); ?></div>

                <table>
                    <thead>
                        <tr><th>Material</th><th>Unidad (supply)</th><th>Cantidad</th><th>Acciones</th></tr>
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
                                        <a class="edit" href="inventario_kits.php?action=edit_material&id=<?php echo h($m['ID_KitMaterial']); ?>">Editar</a>
                                        <a class="del" href="inventario_kits.php?action=delete_material&id=<?php echo h($m['ID_KitMaterial']); ?>" onclick="return confirm('¿Eliminar material del kit?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Form para agregar material -->
                <div style="margin-top:12px">
                    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                        <input type="hidden" name="action" value="add_material">
                        <input type="hidden" name="fk_kit" value="<?php echo h($id_kit); ?>">
                        <div style="flex:1 1 220px">
                            <label>Seleccionar suministro</label>
                            <select name="fk_supply" required>
                                <option value="">-- elegir --</option>
                                <?php foreach ($supplies as $s): ?>
                                    <option value="<?php echo h($s['ID_Supply']); ?>"><?php echo h($s['Name'] . ' (' . $s['Unit'] . ')'); ?></option>
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

            <?php endif; ?>
        </div>

        <!-- Derecha: Form crear/editar kit y gestión supplies -->
        <aside>
            <div class="card" style="margin-bottom:12px">
                <?php
                // Si editar kit (GET action=edit_kit&id=)
                if (isset($_GET['action']) && $_GET['action'] === 'edit_kit' && isset($_GET['id'])):
                    $id = intval($_GET['id']);
                    $stmt = $mysqli->prepare("SELECT ID_Kit, FK_ID_Semester, Start_date, End_date FROM kit WHERE ID_Kit = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res = $stmt->get_result();
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
                                <option value="<?php echo h($s['ID_Semester']); ?>" <?php echo ($kit_edit && $kit_edit['FK_ID_Semester']==$s['ID_Semester']) ? 'selected' : ''; ?>>
                                    <?php echo h($s['Period'].' '.$s['Year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Fecha inicio</label>
                        <input type="date" name="start_date" value="<?php echo h($kit_edit['Start_date'] ?? ''); ?>" required>

                        <label>Fecha fin</label>
                        <input type="date" name="end_date" value="<?php echo h($kit_edit['End_date'] ?? ''); ?>" required>

                        <div style="margin-top:10px">
                            <button type="submit">Guardar cambios</button>
                            <a href="inventario_kits.php" class="small" style="margin-left:8px;text-decoration:none;color:#666">Cancelar</a>
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
                                <option value="<?php echo h($s['ID_Semester']); ?>"><?php echo h($s['Period'].' '.$s['Year']); ?></option>
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

            <!-- Gestión rápida de supplies -->
            <div class="card">
                <h3>Catálogo de suministros</h3>
                <table style="margin-bottom:8px">
                    <thead><tr><th>Nombre</th><th>Unidad</th></tr></thead>
                    <tbody>
                        <?php if (empty($supplies)): ?>
                            <tr><td colspan="2" class="small">No hay suministros registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($supplies as $sp): ?>
                                <tr><td><?php echo h($sp['Name']); ?></td><td class="small"><?php echo h($sp['Unit']); ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <hr style="margin:10px 0">
                <form method="post">
                    <input type="hidden" name="action" value="create_supply">
                    <label>Agregar suministro</label>
                    <input type="text" name="s_name" placeholder="Nombre (ej. Lápiz)" required>
                    <label>Unidad</label>
                    <input type="text" name="s_unit" placeholder="ej. piezas / cajas" required>
                    <div style="margin-top:8px">
                        <button type="submit">Agregar suministro</button>
                    </div>
                </form>
            </div>
        </aside>
    </div>

    <!-- Editar material (pantalla simple) -->
    <?php
    if (isset($_GET['action']) && $_GET['action'] === 'edit_material' && isset($_GET['id'])):
        $idmat = intval($_GET['id']);
        $stmt = $mysqli->prepare("SELECT km.ID_KitMaterial, km.Unit, km.FK_ID_Kit, sp.Name FROM kit_material km JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply WHERE km.ID_KitMaterial = ?");
        $stmt->bind_param("i", $idmat);
        $stmt->execute();
        $res = $stmt->get_result();
        $mat_edit = $res->fetch_assoc() ?: null;
        $stmt->close();
        if ($mat_edit):
    ?>
        <div style="max-width:800px;margin:18px auto;">
            <div class="card">
                <h3>Editar material: <?php echo h($mat_edit['Name']); ?></h3>
                <form method="post" style="display:flex;gap:10px;align-items:end">
                    <input type="hidden" name="action" value="update_material">
                    <input type="hidden" name="id_material" value="<?php echo h($mat_edit['ID_KitMaterial']); ?>">
                    <div style="flex:0 0 160px">
                        <label>Cantidad</label>
                        <input type="number" name="unit" min="0" value="<?php echo h($mat_edit['Unit']); ?>" required>
                    </div>
                    <div>
                        <button type="submit">Guardar</button>
                        <a href="inventario_kits.php?action=view_materials&id=<?php echo h($mat_edit['FK_ID_Kit']); ?>" class="small" style="margin-left:8px">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    <?php
        endif;
    endif;
    ?>
</div>
</body>
</html>