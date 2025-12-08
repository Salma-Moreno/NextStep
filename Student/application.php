<?php 
session_start();

/* ========= GUARDIÁN: Solo estudiantes pueden entrar =========== */
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

/* ========= Conexión a la base de datos ======== */
include '../Conexiones/db.php';

$user_id = $_SESSION['usuario_id'];

/* ======== 1. Verificar si el estudiante tiene datos personales COMPLETOS ========= */
$tieneDatosPersonales = false;
$student_id = null;
$perfil_completo = false;

$sql = "SELECT s.ID_Student, s.Name, s.Last_Name, s.Email_Address, s.Phone_Number,
               sd.Birthdate, sd.High_school, sd.Grade, sd.License, sd.Average,
               td.Tutor_name, td.Tutor_lastname, td.Phone_Number as Tutor_Phone,
               ad.Street, ad.City, ad.Postal_Code
        FROM student s
        LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
        LEFT JOIN tutor_data td ON s.ID_Student = td.FK_ID_Student
        LEFT JOIN address ad ON s.ID_Student = ad.FK_ID_Student
        WHERE s.FK_ID_User = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $student_id = (int)$row['ID_Student'];
    
    // Verificar si TODOS los campos obligatorios están llenos
    $tieneDatosPersonales = (
        !empty($row['Name']) &&
        !empty($row['Last_Name']) &&
        !empty($row['Email_Address']) &&
        !empty($row['Phone_Number']) &&
        !empty($row['Birthdate']) &&
        !empty($row['High_school']) &&
        !empty($row['Grade']) &&
        !empty($row['Tutor_name']) &&
        !empty($row['Tutor_lastname']) &&
        !empty($row['Tutor_Phone']) &&
        !empty($row['Street']) &&
        !empty($row['City']) &&
        !empty($row['Postal_Code'])
    );
    
    $perfil_completo = $tieneDatosPersonales;
}
$stmt->close();

/* ======== 2. Obtener TODAS las becas que el estudiante YA HA SOLICITADO ========= */
$kits_solicitados = [];       // IDs de kits ya solicitados (sin importar estado)
$solicitudes_activas = [];    // Solicitudes con estados activos
$tieneSolicitudesActivas = false;

if ($student_id) {

    // Todos los kits que ha solicitado
    $sql_kits = "SELECT DISTINCT FK_ID_Kit 
                 FROM aplication 
                 WHERE FK_ID_Student = ?";
    $stmt_kits = $conn->prepare($sql_kits);
    $stmt_kits->bind_param("i", $student_id);
    $stmt_kits->execute();
    $result_kits = $stmt_kits->get_result();
    while ($kit_row = $result_kits->fetch_assoc()) {
        $kits_solicitados[] = (int)$kit_row['FK_ID_Kit'];
    }
    $stmt_kits->close();

    // Solicitudes ACTIVAS + nombre de beca
    $sql_activas = "SELECT a.FK_ID_Kit, a.status, k.Name AS kit_name
                    FROM aplication a
                    JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
                    WHERE a.FK_ID_Student = ?
                      AND a.status NOT IN ('Cancelada', 'Rechazada')";
    
    $stmt_activas = $conn->prepare($sql_activas);
    $stmt_activas->bind_param("i", $student_id);
    $stmt_activas->execute();
    $res_activas = $stmt_activas->get_result();
    
    while ($activa = $res_activas->fetch_assoc()) {
        $solicitudes_activas[] = $activa;
    }
    $stmt_activas->close();

    $tieneSolicitudesActivas = !empty($solicitudes_activas);
}

/* ========= 3. Cancelar solicitud ========= */
if (isset($_POST['cancel_application']) && $student_id && isset($_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    
    $sql = "UPDATE aplication
            SET status = 'Cancelada'
            WHERE FK_ID_Student = ?
              AND FK_ID_Kit = ?
              AND status NOT IN ('Cancelada', 'Rechazada', 'Entregada')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $kit_id);
    
    if ($stmt->execute()) {
        header('Location: application.php?cancel_success=1');
        exit;
    }
    
    $stmt->close();
}

/* ========= 4. Aplicar a beca ========= */
if (isset($_POST['apply_scholarship'], $_POST['kit_id'])) {
    $kit_id = (int)$_POST['kit_id'];
    $initial_status = 'Enviada';

    // Validaciones
    if (!$perfil_completo) {
        header('Location: perfil.php?completa=1');
        exit;
    }
    
    // Verificar que el estudiante no haya solicitado esta beca antes
    if (in_array($kit_id, $kits_solicitados)) {
        header('Location: application.php?error=beca_ya_solicitada');
        exit;
    }

    // Generar nuevo ID_status
    $next_id_status = 1;
    $sql_max_id = "SELECT MAX(ID_status) AS max_id FROM aplication";
    $result_max = $conn->query($sql_max_id);
    if ($result_max && $row_max = $result_max->fetch_assoc()) {
        if (!is_null($row_max['max_id'])) {
            $next_id_status = (int)$row_max['max_id'] + 1;
        }
    }

    // Insertar nueva solicitud
    $sql_insert = "INSERT INTO aplication 
                    (ID_status, FK_ID_Student, FK_ID_Kit, status)
                   VALUES (?, ?, ?, ?)";

    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "iiis",
        $next_id_status,
        $student_id,
        $kit_id,
        $initial_status
    );

    if ($stmt_insert->execute()) {
        header('Location: application.php?success=1');
        exit;
    }

    $stmt_insert->close();
}

/* ========= 5. Endpoint AJAX para detalles de beca (modal) ========= */
$action = $_GET['action'] ?? null;

if ($action === 'kit_details' && isset($_GET['id'])) {
    $kitId = (int)$_GET['id'];
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'ok'        => false,
        'kit'       => null,
        'materials' => []
    ];

    // Datos del kit + semestre
    $stmt = $conn->prepare(
        "SELECT k.ID_Kit,
                k.Name       AS Name,
                k.Start_date,
                k.End_date,
                s.Period,
                s.Year
         FROM kit k
         JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
         WHERE k.ID_Kit = ?"
    );
    $stmt->bind_param("i", $kitId);
    $stmt->execute();
    $resKit = $stmt->get_result();

    if ($kitRow = $resKit->fetch_assoc()) {
        $response['ok']  = true;
        $response['kit'] = $kitRow;

        // Materiales del kit
        $stmtM = $conn->prepare(
            "SELECT km.Unit,
                    sp.Name     AS supply_name,
                    sp.features,
                    sp.marca
             FROM kit_material km
             JOIN supplies sp ON km.FK_ID_Supply = sp.ID_Supply
             WHERE km.FK_ID_Kit = ?
             ORDER BY sp.Name"
        );
        $stmtM->bind_param("i", $kitId);
        $stmtM->execute();
        $resMat = $stmtM->get_result();
        while ($m = $resMat->fetch_assoc()) {
            $response['materials'][] = $m;
        }
        $stmtM->close();
    }
    $stmt->close();

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Becas - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/application.css">
</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">

    <!-- ========= CARD SUPERIOR ========= -->
    <div class="scholarship-header-card">
        <h1>Solicitud de Becas</h1>

        <?php if ($perfil_completo): ?>
            <h2>Selecciona una de las becas disponibles para ti:</h2>
            
            <!-- Mensajes -->
            <?php if (isset($_GET['error']) && $_GET['error'] == 'beca_ya_solicitada'): ?>
                <div class="alert alert-warning">
                    Ya has solicitado esta beca anteriormente. <strong>NO puedes volver a solicitarla.</strong>
                </div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ¡Solicitud enviada exitosamente!
                </div>
            <?php elseif (isset($_GET['cancel_success'])): ?>
                <div class="alert alert-success">
                    ¡Solicitud cancelada exitosamente!
                </div>
            <?php endif; ?>

            <!-- Información de solicitudes activas -->
            <?php if ($tieneSolicitudesActivas): ?>
                <div class="active-applications-info">
                    <h4>Tus solicitudes activas:</h4>
                    <ul class="application-status-list">
                        <?php 
                        // Agrupar por kit para no repetir
                        $activas_por_kit = [];
                        foreach ($solicitudes_activas as $sol) {
                            $k_id = (int)$sol['FK_ID_Kit'];
                            $activas_por_kit[$k_id] = [
                                'status'   => $sol['status'],
                                'kit_name' => $sol['kit_name']
                            ];
                        }
                        foreach ($activas_por_kit as $kit_id => $info):
                            $status   = $info['status'];
                            $kitName  = $info['kit_name'];
                            $statusCss = 'status-' . strtolower(str_replace(' ', '-', $status));
                        ?>
                        <li>
                            <?= htmlspecialchars($kitName) ?>:
                            <span class="status-label <?= htmlspecialchars($statusCss) ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p><small>Puedes tener múltiples solicitudes activas simultáneamente.</small></p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <h2>Perfil Incompleto</h2>
            <div class="profile-warning">
                <h3>⚠️ Perfil incompleto</h3>
                <p>Debes completar tu perfil antes de poder solicitar becas.</p>
                <p>Necesitas llenar todos los campos obligatorios en tu perfil.</p>
                <a href="perfil.php" class="btn">Ir a completar mi perfil</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($perfil_completo): ?>

    <!-- ========= LISTA DE BECAS ========= -->
    <div class="scholarship-list">

        <?php
        // Construir la consulta de becas vigentes
        $sql = "
            SELECT 
                k.ID_Kit,
                k.Name AS DisplayName,
                s.Period,
                s.Year,
                k.Start_date,
                k.End_date,
                CONCAT(
                    'Material escolar para el periodo ',
                    s.Period, ' ', s.Year
                ) AS ShortDescription,
                CONCAT(
                    'Vigente del ',
                    DATE_FORMAT(k.Start_date, '%d/%m/%Y'),
                    ' al ',
                    DATE_FORMAT(k.End_date, '%d/%m/%Y')
                ) AS LongDescription
            FROM kit k
            INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
            WHERE k.Start_date <= CURDATE()
              AND k.End_date   >= CURDATE()
        ";
        
        // Excluir todos los kits que ya solicitó (sin importar estado)
        if (!empty($kits_solicitados)) {
            $excluded_ids = implode(',', array_map('intval', $kits_solicitados));
            $sql .= " AND k.ID_Kit NOT IN ($excluded_ids)";
        }
        
        $sql .= " ORDER BY k.Start_date DESC";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0):

            while ($row = $result->fetch_assoc()):
                $kitId = (int)$row['ID_Kit'];
                $esKitSolicitado  = in_array($kitId, $kits_solicitados);
                $displayName      = $row['DisplayName'] ?: ('Beca #' . $kitId);

                // Verificar si es una beca activa actual (por si acaso)
                $esBecaActiva     = false;
                $estadoBecaActiva = '';
                foreach ($solicitudes_activas as $solicitud) {
                    if ((int)$solicitud['FK_ID_Kit'] === $kitId) {
                        $esBecaActiva     = true;
                        $estadoBecaActiva = $solicitud['status'];
                        break;
                    }
                }
        ?>

        <!-- ========= CARD INDIVIDUAL ========= -->
        <div class="scholarship-card" onclick="openKitModal(<?= $kitId ?>)">
            <div class="card-image"></div>

            <div class="card-body">
                <h3><?= htmlspecialchars($displayName) ?></h3>
                <p class="card-short-text"><?= htmlspecialchars($row['ShortDescription']) ?></p>
                <p class="card-long-text"><?= htmlspecialchars($row['LongDescription']) ?></p>

                <div class="card-footer">

                    <?php if (!$esKitSolicitado): ?>
                        <!-- PUEDE SOLICITAR - Nunca ha solicitado esta beca -->
                        <form method="POST" action="application.php" onclick="event.stopPropagation();">
                            <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                            <button type="submit" name="apply_scholarship" class="apply-btn">
                                Solicitar beca
                            </button>
                        </form>

                    <?php elseif ($esBecaActiva): ?>
                        <!-- YA TIENE ESTA BECA ACTIVA -->
                        <?php
                        $statusClass = 'enviada';
                        if ($estadoBecaActiva === 'Entrega')     $statusClass = 'entrega';
                        elseif ($estadoBecaActiva === 'Aprobada') $statusClass = 'aprobada';
                        elseif ($estadoBecaActiva === 'En revisión') $statusClass = 'en-revision';
                        ?>
                        <span class="status-label <?= $statusClass ?>">
                            <?= htmlspecialchars($estadoBecaActiva) ?>
                        </span>
                        
                        <?php if (!in_array($estadoBecaActiva, ['Entregada', 'Aprobada', 'Entrega'])): ?>
                            <form method="POST" action="application.php" onclick="event.stopPropagation();">
                                <input type="hidden" name="kit_id" value="<?= $kitId ?>">
                                <button type="submit" name="cancel_application" class="cancel-btn"
                                    onclick="return confirm('¿Cancelar esta solicitud?\n\nUna vez cancelada NO podrás volver a solicitarla.');">
                                    Cancelar
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- YA SOLICITÓ ESTA BECA PERO FUE CANCELADA/RECHAZADA -->
                        <span class="status-label ya-solicitada">Ya solicitada</span>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php endwhile;
        else:
            echo "<p class='alert alert-info'>No hay becas disponibles por el momento.</p>";
        endif;
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal detalles de beca -->
<div id="kit-modal-overlay" class="kit-modal-overlay">
    <div class="kit-modal card">
        <button type="button"
                class="kit-modal-close"
                onclick="closeKitModal()">
            ×
        </button>
        <div id="kit-modal-content">
            <!-- El JS inyecta aquí el contenido -->
        </div>
    </div>
</div>

<script>
// Helper para escapar HTML
function escapeHtml(str){
    if (str == null) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

/* === Abrir modal de detalles de beca === */
function openKitModal(kitId){
    const overlay = document.getElementById('kit-modal-overlay');
    const content = document.getElementById('kit-modal-content');
    if (!overlay || !content) return;

    overlay.style.display = 'flex';
    content.innerHTML = '<p class="kit-modal-subtitle">Cargando detalles de la beca...</p>';

    const url = 'application.php?action=kit_details&id=' + encodeURIComponent(kitId);

    fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.ok || !data.kit){
                content.innerHTML = '<p class="empty-msg">No se pudieron obtener los detalles de la beca.</p>';
                return;
            }

            const kit = data.kit;
            const materials = data.materials || [];

            const displayName = kit.Name || ('Beca #' + kit.ID_Kit);

            let html = '';

            // Título y subtítulo
            html += '<h2 class="kit-modal-title">' + escapeHtml(displayName) + '</h2>';
            html += '<p class="kit-modal-subtitle">Detalle de materiales incluidos en esta beca</p>';

            // Datos de semestre y vigencia
            html += '<div class="kit-modal-meta">';
            html +=   '<span><strong>Semestre:</strong> '
                   +   escapeHtml((kit.Period || '') + ' ' + (kit.Year || ''))
                   + '</span>';
            html +=   '<span><strong>Vigencia:</strong> '
                   +   escapeHtml(kit.Start_date || '-') + ' — '
                   +   escapeHtml(kit.End_date || '-')
                   + '</span>';
            html += '</div>';

            // Tabla de materiales
            if (!materials.length){
                html += '<p class="empty-msg">Este kit aún no tiene materiales asignados.</p>';
            } else {
                html += '<table>';
                html +=   '<thead><tr>'
                       +     '<th>Material</th>'
                       +     '<th>Características</th>'
                       +     '<th>Marca</th>'
                       +     '<th>Cantidad en beca</th>'
                       +   '</tr></thead><tbody>';

                materials.forEach(function(m){
                    html += '<tr>'
                         +   '<td>' + escapeHtml(m.supply_name || '') + '</td>'
                         +   '<td>' + escapeHtml(m.features || '') + '</td>'
                         +   '<td>' + escapeHtml(m.marca || '') + '</td>'
                         +   '<td>' + escapeHtml(m.Unit != null ? String(m.Unit) : '') + '</td>'
                         + '</tr>';
                });

                html +=   '</tbody></table>';
            }

            content.innerHTML = html;
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<p class="empty-msg">Ocurrió un error al cargar la información.</p>';
        });
}

function closeKitModal(){
    const overlay = document.getElementById('kit-modal-overlay');
    if (overlay) overlay.style.display = 'none';
}
</script>

</body>
</html>
