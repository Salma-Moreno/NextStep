<?php
// Lógica y datos
require 'inventario_logic.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Inventario — Kits de Materiales</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="../assets/staff/inventario.css">
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
                    'kit_created'      => 'Beca / kit creado',
                    'kit_updated'      => 'Beca / kit actualizada',
                    'kit_deleted'      => 'Beca / kit eliminada',
                    'material_added'   => 'Material agregado',
                    'material_updated' => 'Material actualizado',
                    'material_deleted' => 'Material eliminado',
                    'supply_created'   => 'Suministro agregado',
                    'supply_updated'   => 'Suministro actualizado (unidades sumadas)'
                ];
                echo h($map[$msg] ?? $msg);
            ?>
        </div>
    <?php endif; ?>

    <!-- ########### ARRIBA: CREAR / EDITAR KIT ########### -->
    <div class="card card--with-gap">
        <?php
        // Si estamos viendo materiales de un kit, usamos ese mismo para editar arriba
        if ($selectedKitId && $selectedKitInfo) {
            $kit_edit = $selectedKitInfo;
        } elseif ($getAction === 'edit_kit' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare(
                "SELECT ID_Kit, Name, FK_ID_Semester, Start_date, End_date 
                 FROM kit WHERE ID_Kit = ?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res      = $stmt->get_result();
            $kit_edit = $res->fetch_assoc() ?: null;
            $stmt->close();
            $selectedKitId = $id;
        } else {
            $kit_edit = null;
        }
        ?>

        <?php if ($kit_edit): ?>
            <h3>Editar beca / kit: <?php echo h($kit_edit['Name']); ?></h3>
            <form method="post">
                <input type="hidden" name="action" value="update_kit">
                <input type="hidden" name="id_kit" value="<?php echo h($kit_edit['ID_Kit']); ?>">

                <p class="small">
                    Nombre interno: <strong><?php echo h($kit_edit['Name']); ?></strong>
                </p>

                <label>Semestre</label>
                <select name="fk_semester" required>
                    <option value="">-- elegir semestre --</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?php echo h($s['ID_Semester']); ?>"
                            <?php echo ($kit_edit['FK_ID_Semester']==$s['ID_Semester']) ? 'selected' : ''; ?>>
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
                    <a href="<?php echo h($_SERVER['PHP_SELF']); ?>?pagina=<?php echo $paginaActual; ?>" class="small link-cancel">Cancelar</a>
                </div>
            </form>
        <?php else: ?>
            <h3>Crear nueva beca / kit</h3>
            <form method="post">
                <input type="hidden" name="action" value="create_kit">

                <div class="kit-create-grid">
                    <!-- Columna izquierda: datos de la beca -->
                    <div class="kit-create-col">
                        <label>Semestre</label>
                        <select name="fk_semester" required>
                            <option value="">-- elegir semestre --</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?php echo h($s['ID_Semester']); ?>">
                                    <?php echo h($s['Period'].' '.$s['Year']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label>Tipo de beca</label>
                        <select name="kit_level" id="kit_level" required>
                            <option value="">-- elegir tipo --</option>
                            <option value="Basica">Básica (máx. 4 artículos)</option>
                            <option value="Intermedia">Intermedia (máx. 6 artículos)</option>
                            <option value="Superior">Superior (máx. 8 artículos)</option>
                        </select>

                        <label>Fecha inicio</label>
                        <input type="date" name="start_date" required>

                        <label>Fecha fin</label>
                        <input type="date" name="end_date" required>
                    </div>

                    <!-- Columna derecha: materiales del kit -->
                    <div class="kit-create-col">
                        <h4>Materiales del kit a crear</h4>
                        <p class="small">Agrega aquí los materiales que llevará esta beca.</p>

                        <div id="kit-materials-container">
                            <!-- Fila inicial -->
                            <div class="kit-material-row">
                                <div class="kit-material-col">
                                    <label>Material</label>
                                    <select name="kit_supply_ids[]">
                                        <option value="">-- elegir --</option>
                                        <?php foreach ($supplies as $s): ?>
                                            <option value="<?php echo h($s['ID_Supply']); ?>">
                                                <?php 
                                                    echo h(
                                                        $s['Name'] .
                                                        ' | ' . ($s['marca'] ?: 'Sin marca') .
                                                        ' | ' . ($s['features'] ?: '-')
                                                    ); 
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="kit-material-qty">
                                    <label>Cantidad</label>
                                    <input type="number" name="kit_units[]" min="1" value="1">
                                </div>
                                <div class="kit-material-remove">
                                    <button type="button" onclick="removeKitMaterialRow(this)">✕</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-secondary-link" onclick="addKitMaterialRow();">
                            Agregar otro material
                        </button>
                    </div>
                </div>

                <div style="margin-top:10px">
                    <button type="submit">Crear Kit con materiales</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- ########### MATERIALES DEL KIT SELECCIONADO (ABAJO DEL FORMULARIO) ########### -->
    <?php if ($selectedKitId && $selectedKitInfo): ?>
        <div class="card kit-details-panel" style="margin-top:15px;">
            <div class="kit-details-header">
                <h4>
                    Materiales de <?php echo h($selectedKitInfo['Name']); ?>
                </h4>
                <button class="close-btn" onclick="window.location.href='<?php echo h($_SERVER['PHP_SELF']); ?>?pagina=<?php echo $paginaActual; ?>'">
                    ×
                </button>
            </div>

            <div class="small" style="margin-bottom:15px">
                Semestre: 
                <?php echo h(($selectedKitInfo['Period'] ?? '-') . ' ' . ($selectedKitInfo['Year'] ?? '')); ?>
                <br>
                Período de vigencia: 
                <?php echo h($selectedKitInfo['Start_date'] ?? '-'); ?> — 
                <?php echo h($selectedKitInfo['End_date'] ?? '-'); ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Características</th>
                        <th>Marca</th>
                        <th>Stock total</th>
                        <th>Cantidad en kit</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($selectedMaterials)): ?>
                        <tr><td colspan="6" class="small">No hay materiales asignados a este kit.</td></tr>
                    <?php else: ?>
                        <?php foreach ($selectedMaterials as $m): ?>
                            <tr>
                                <td><?php echo h($m['supply_name']); ?></td>
                                <td class="small"><?php echo h($m['features']); ?></td>
                                <td class="small"><?php echo h($m['marca']); ?></td>
                                <td class="small"><?php echo h($m['stock']); ?></td>
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

            <!-- Form agregar material al kit (extra, ya creado) -->
            <div style="margin-top:15px">
                <form method="post" class="kit-add-material-inline">
                    <input type="hidden" name="action" value="add_material">
                    <input type="hidden" name="fk_kit" value="<?php echo h($selectedKitId); ?>">

                    <div class="kit-add-material-select">
                        <label>Seleccionar suministro</label>
                        <select name="fk_supply" required>
                            <option value="">-- elegir --</option>
                            <?php foreach ($supplies as $s): ?>
                                <option value="<?php echo h($s['ID_Supply']); ?>">
                                    <?php 
                                        echo h(
                                            $s['Name'] .
                                            ' | ' . ($s['marca'] ?: 'Sin marca') .
                                            ' | ' . ($s['features'] ?: '-') .
                                            ' | stock: ' . $s['Unit']
                                        ); 
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="kit-add-material-qty">
                        <label>Cantidad para el kit</label>
                        <input type="number" name="unit" min="0" value="1" required>
                    </div>

                    <div class="kit-add-material-btn">
                        <button type="submit">Agregar material</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- ########### GRID: KITS + CATÁLOGO ########### -->
    <div class="grid" style="margin-top:20px;">
        <!-- Izquierda: Kits -->
        <div class="card">
            <div class="controls-row">
                <h3 style="margin:0">Becas / kits registrados</h3>
                <div class="small">
                    Total: <?php echo $totalKits; ?> registros
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Semestre</th>
                    <th>Inicio — Fin</th>
                    <th class="small">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($kits)): ?>
                    <tr><td colspan="4" class="small">No hay becas registradas. Crea una arriba.</td></tr>
                <?php else: ?>
                    <?php foreach ($kits as $k): ?>
                        <tr>
                            <td><?php echo h($k['Name']); ?></td>
                            <td><?php echo h(($k['Period'] ?? '-') . ' ' . ($k['Year'] ?? '')); ?></td>
                            <td><?php echo h($k['Start_date']); ?> — <?php echo h($k['End_date']); ?></td>
                            <td class="actions">
                                <!-- Ver ahora abre modal -->
                                <a class="view"
                                   href="#"
                                   onclick="openKitModal(<?php echo (int)$k['ID_Kit']; ?>); return false;">
                                   Ver
                                </a>
                                <!-- Editar lleva a ver_materials para editar arriba -->
                                <a class="edit"
                                   href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=view_materials&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>">
                                   Editar
                                </a>
                                <a class="del"
                                   href="<?php echo h($_SERVER['PHP_SELF']); ?>?action=delete_kit&id=<?php echo h($k['ID_Kit']); ?>&pagina=<?php echo $paginaActual; ?>"
                                   onclick="return confirm('¿Eliminar beca/kit y sus materiales?')">
                                   x
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Paginación de kits -->
            <?php if ($totalPaginas > 1): ?>
                <div class="pagination-buttons">
                    <?php if ($paginaActual > 1): ?>
                        <a href="?pagina=<?php echo $paginaActual - 1; ?>">
                            ‹ Anterior
                        </a>
                    <?php else: ?>
                        <span class="disabled">‹ Anterior</span>
                    <?php endif; ?>

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

        <!-- Derecha: Catálogo de suministros -->
        <aside>
            <div class="card">
                <h3>Catálogo de suministros</h3>
                <table id="supplies-table" style="margin-bottom:8px">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Características</th>
                            <th>Marca</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($supplies_all)): ?>
                            <tr><td colspan="4" class="small">No hay suministros registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($supplies_all as $sp): ?>
                                <tr>
                                    <td><?php echo h($sp['Name']); ?></td>
                                    <td class="small"><?php echo h($sp['features']); ?></td>
                                    <td class="small"><?php echo h($sp['marca']); ?></td>
                                    <td class="small"><?php echo h($sp['Unit']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div id="supplies-pager" class="small"></div>

                <hr class="divider">

                <!-- Formulario 1: SOLO tipos permitidos -->
                <p class="form-subtitle">
                    Agregar suministro (solo tipos permitidos)
                </p>
                <form method="post" id="form-suministro-basico">
                    <input type="hidden" name="action" value="create_supply">

                    <label>Tipo de suministro</label>
                    <select name="s_name" required>
                        <option value="">-- elegir tipo --</option>
                        <option value="Cuaderno">Cuaderno</option>
                        <option value="Bolígrafo">Bolígrafo</option>
                        <option value="Lápiz de grafito">Lápiz de grafito</option>
                        <option value="Calculadora común">Calculadora común</option>
                        <option value="Calculadora científica">Calculadora científica</option>
                        <option value="Juego de reglas">Juego de reglas</option>
                    </select>

                    <label>Características</label>
                    <input type="text" name="s_features" placeholder="ej. raya sencilla, tapa dura">

                    <label>Marca</label>
                    <select name="s_marca" required>
                        <option value="">-- Elegir marca --</option>
                        <option>Faber-Castell</option>
                        <option>Staedtler</option>
                        <option>Stabilo</option>
                        <option>Pentel</option>
                        <option>Pilot</option>
                        <option>Bic</option>
                        <option>Sharpie</option>
                        <option>Post-it (3M)</option>
                        <option>Norma</option>
                        <option>Kiut</option>
                        <option>Estrella</option>
                        <option>Otro</option>
                    </select>

                    <label>Cantidad (unidades)</label>
                    <input type="number" name="s_unit" min="1" placeholder="Cantidad (ej. 10)" required>

                    <div class="form-actions">
                        <button type="submit">Agregar suministro</button>
                    </div>
                </form>

                <!-- Botón para mostrar formulario 2 -->
                <button type="button" class="btn-secondary-link"
                        onclick="toggleOtroSuministro();">
                    Agregar otro tipo de suministro
                </button>

                <!-- Formulario 2: OTRO tipo de suministro (nombre libre) -->
                <div id="otro-suministro-wrap" style="display:none; margin-top:10px;">
                    <p class="form-subtitle">
                        Otro tipo de suministro (nombre libre)
                    </p>
                    <form method="post" id="form-suministro-otro">
                        <input type="hidden" name="action" value="create_supply">

                        <label>Nombre</label>
                        <input type="text" name="s_name" placeholder="Nombre (ej. Marcadores fluorescentes)" required>

                        <label>Características</label>
                        <input type="text" name="s_features" placeholder="ej. colores neón">

                        <label>Marca</label>
                        <select name="s_marca" required>
                            <option value="">-- elegir marca --</option>
                            <option>Faber-Castell</option>
                            <option>Staedtler</option>
                            <option>Stabilo</option>
                            <option>Pentel</option>
                            <option>Pilot</option>
                            <option>Bic</option>
                            <option>Sharpie</option>
                            <option>Post-it (3M)</option>
                            <option>Norma</option>
                            <option>Kiut</option>
                            <option>Estrella</option>
                            <option>Otro</option>
                        </select>

                        <label>Cantidad (unidades)</label>
                        <input type="number" name="s_unit" min="1" placeholder="Cantidad (ej. 5)" required>

                        <div class="form-actions">
                            <button type="submit">Agregar suministro</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </div>

    <!-- Overlay editar material -->
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

    <!-- Modal de detalles de beca / kit (para botón Ver) -->
    <div id="kit-modal-overlay" class="kit-modal-overlay">
        <div id="kit-modal" class="card kit-modal">
            <button type="button"
                    class="kit-modal-close close-btn"
                    onclick="closeKitModal()">
                ×
            </button>
            <div id="kit-modal-content">
                <!-- JS inyecta contenido -->
            </div>
        </div>
    </div>

</div>

<script>
function toggleOtroSuministro() {
    const wrap = document.getElementById('otro-suministro-wrap');
    if (!wrap) return;
    if (wrap.style.display === 'none' || wrap.style.display === '') {
        wrap.style.display = 'block';
        wrap.scrollIntoView({behavior:'smooth'});
    } else {
        wrap.style.display = 'none';
    }
}

/* === Límite de materiales por tipo de beca (JS) === */
function getKitLevelLimit() {
    const levelSelect = document.getElementById('kit_level');
    if (!levelSelect) return null;

    const value = levelSelect.value;
    switch (value) {
        case 'Basica':
            return 4;
        case 'Intermedia':
            return 6;
        case 'Superior':
            return 8;
        default:
            return null;
    }
}

/* === FILAS DINÁMICAS DE MATERIALES EN CREAR KIT === */
function addKitMaterialRow() {
    const container = document.getElementById('kit-materials-container');
    if (!container) return;

    const limit = getKitLevelLimit();
    if (!limit) {
        alert('Primero selecciona el tipo de beca.');
        const levelSelect = document.getElementById('kit_level');
        if (levelSelect) levelSelect.focus();
        return;
    }

    const rows = container.querySelectorAll('.kit-material-row');
    const currentCount = rows.length;

    if (currentCount >= limit) {
        alert('Para este tipo de beca solo puedes agregar hasta ' + limit + ' artículos.');
        return;
    }

    const firstRow = rows[0];
    if (!firstRow) return;

    const newRow = firstRow.cloneNode(true);

    const select = newRow.querySelector('select[name="kit_supply_ids[]"]');
    const input  = newRow.querySelector('input[name="kit_units[]"]');
    if (select) select.value = '';
    if (input)  input.value  = '1';

    container.appendChild(newRow);
}

function removeKitMaterialRow(btn) {
    const row = btn.closest('.kit-material-row');
    const container = document.getElementById('kit-materials-container');
    if (!row || !container) return;

    const rows = container.querySelectorAll('.kit-material-row');
    if (rows.length > 1) {
        row.remove();
    } else {
        const select = row.querySelector('select[name="kit_supply_ids[]"]');
        const input  = row.querySelector('input[name="kit_units[]"]');
        if (select) select.value = '';
        if (input)  input.value  = '1';
    }
}

/* === Paginación front-end del catálogo de suministros === */
document.addEventListener('DOMContentLoaded', function () {
    const perPage = 5;
    const table   = document.getElementById('supplies-table');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'));

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

    renderPage(1);
});

/* === Modal "Ver" kit === */
function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openKitModal(kitId) {
    const overlay = document.getElementById('kit-modal-overlay');
    const content = document.getElementById('kit-modal-content');
    if (!overlay || !content) return;

    content.innerHTML = '<p class="small">Cargando detalles...</p>';
    overlay.style.display = 'flex';

    const url = 'inventario.php?action=kit_details&id=' + encodeURIComponent(kitId);

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.kit) {
                content.innerHTML = '<p class="small">No se pudieron obtener los detalles de la beca.</p>';
                return;
            }

            const kit = data.kit;
            const materials = data.materials || [];

            let html = '';

            html += '<h3 style="margin-top:0;">' + escapeHtml(kit.Name || ('Beca #' + kit.ID_Kit)) + '</h3>';
            html += '<p class="small">';
            html += 'Semestre: ' + escapeHtml(kit.Period || '-') + ' ' + escapeHtml(kit.Year || '') + '<br>';
            html += 'Vigencia: ' + escapeHtml(kit.Start_date || '-') + ' — ' + escapeHtml(kit.End_date || '-') + '';
            html += '</p>';

            if (!materials.length) {
                html += '<p class="small">Esta beca aún no tiene materiales asignados.</p>';
            } else {
                html += '<table>';
                html += '<thead><tr>'
                     + '<th>Material</th>'
                     + '<th>Características</th>'
                     + '<th>Marca</th>'
                     + '<th>Cantidad en beca</th>'
                     + '</tr></thead><tbody>';

                materials.forEach(function(m){
                    html += '<tr>';
                    html += '<td>' + escapeHtml(m.supply_name || '') + '</td>';
                    html += '<td class="small">' + escapeHtml(m.features || '') + '</td>';
                    html += '<td class="small">' + escapeHtml(m.marca || '') + '</td>';
                    html += '<td>' + (m.Unit != null ? m.Unit : '') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            content.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<p class="small">Ocurrió un error al cargar la información.</p>';
        });
}

function closeKitModal() {
    const overlay = document.getElementById('kit-modal-overlay');
    if (overlay) overlay.style.display = 'none';
}
</script>
</body>
</html>
