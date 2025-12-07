<?php
session_start();
require '../Conexiones/db.php'; // Debe definir $conn (mysqli)

// Guardián: solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    // OJO: como también usaremos este archivo como API,
// para las llamadas AJAX es mejor devolver JSON, no redirigir a HTML
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    header('Location: StaffLogin.php');
    exit;
}

// ------------------ MODO API (JSON) ------------------
$VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($action !== null) {
    header('Content-Type: application/json; charset=utf-8');

    // 1) Datos básicos del estudiante
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

        $stmt->bind_param('i', $id);
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

    // 2) Solicitudes de beca del estudiante
if ($method === 'GET' && $action === 'applications') {
    $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    if (!$student_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID de estudiante inválido']);
        exit;
    }

    // Como la tabla kit no tiene Name ni Description,
    // construimos un "nombre" genérico usando el ID_Kit
    $sql = "
        SELECT 
            k.ID_Kit,
            CONCAT('Kit ', k.ID_Kit) AS KitName,
            NULL AS KitDescription,
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

    // 3) Actualizar estatus
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

    // Acción no válida
    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida.']);
    exit;
}

// ------------------ MODO PÁGINA (HTML) ------------------

// Consulta que obtiene TODOS los estudiantes aunque no tengan detalles ni solicitud de beca
$query = "
SELECT 
    s.ID_Student,
    s.Name,
    s.Last_Name,
    sd.License,
    sd.Average,
    a.status
FROM student s
LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
LEFT JOIN aplication a ON s.ID_Student = a.FK_ID_Student
ORDER BY s.Last_Name, s.Name
";

$result = mysqli_query($conn, $query);
if ($result === false) {
    die('Error en la consulta SQL: ' . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Estudiantes</title>
    <link rel="stylesheet" href="../assets/Staff/list.css">
</head>
<body>
<?php include '../includes/HeaderMenuStaff.php'; ?> 

<div class="container">
    <h2>Estudiantes Inscritos</h2>

    <!-- Buscador -->
    <div class="search-box">
        <input 
            type="text" 
            id="search" 
            placeholder="Buscar por nombre, apellido, matrícula…">
    </div>

    <!-- Tabla -->
    <table id="studentTable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Matrícula</th>
                <th>Promedio</th>
                <th>Estatus de Beca</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <?php 
                    $statusRaw   = $row['status'] ?? null;
                    $statusText  = $statusRaw ?? 'Sin Solicitud';
                    $statusClass = $statusRaw ? 'status-' . preg_replace('/\s+/', '', $statusRaw) : 'status-null';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['Last_Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['License'] ?? 'No asignada') ?></td>
                    <td><?= htmlspecialchars($row['Average'] ?? '—') ?></td>
                    <td class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusText) ?></td>
                    <td>
                        <button class="btn-action btn-perfil" type="button"
                            onclick="openProfileModal(<?= (int)$row['ID_Student'] ?>)">
                            |||
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <div class="pagination">
        <button id="prevBtn">Anterior</button>
        <span>Página <span id="currentPage">1</span></span>
        <button id="nextBtn">Siguiente</button>
    </div>
</div>

<!-- MODAL PERFIL ESTUDIANTE -->
<div id="profileModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Perfil del estudiante</h3>
            <button id="pm_close" class="modal-close">&times;</button>
        </div>

        <div id="pm_error" class="modal-error"></div>

        <!-- Datos básicos -->
        <div class="modal-row">
            <strong>Nombre:</strong>
            <span id="pm_name"></span>
        </div>

        <div class="modal-row">
            <strong>Correo:</strong>
            <span id="pm_email"></span>
        </div>

        <div class="modal-row">
            <strong>Matrícula:</strong>
            <span id="pm_license"></span>
        </div>

        <div class="modal-row">
            <strong>Promedio:</strong>
            <span id="pm_average"></span>
        </div>

        <div class="modal-row">
            <strong>Estatus de Beca:</strong>
            <span id="pm_status"></span>
        </div>

        <hr>

        <!-- Solicitudes de beca del estudiante -->
        <h4>Solicitudes de beca</h4>
        <div id="pm_applications"></div>
    </div>
</div>

<script>
// ===== Helpers =====
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function statusClassFromText(status) {
    if (!status) return '';
    return status.replace(/\s+/g, '-');
}

// Helper para pedir JSON de ESTE MISMO ARCHIVO
function fetchJson(params, options) {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    return fetch(url.toString(), options)
        .then(res => res.text().then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const snippet = text.slice(0, 200).replace(/\s+/g, ' ');
                throw new Error('Respuesta no válida del servidor: ' + snippet);
            }

            if (!res.ok) {
                const msg = data.error || data.message || ('Error ' + res.status);
                throw new Error(msg);
            }

            return data;
        }));
}

// ====== Abrir modal y cargar info ======
function openProfileModal(studentId) {
    const modal         = document.getElementById('profileModal');
    const errorBox      = document.getElementById('pm_error');
    const appsContainer = document.getElementById('pm_applications');

    errorBox.textContent = 'Cargando información...';

    document.getElementById('pm_name').textContent    = '';
    document.getElementById('pm_email').textContent   = '';
    document.getElementById('pm_license').textContent = '';
    document.getElementById('pm_average').textContent = '';
    document.getElementById('pm_status').textContent  = '';
    appsContainer.innerHTML = 'Cargando solicitudes...';

    modal.classList.add('open');
    document.body.classList.add('modal-open');

    // 1) Datos básicos del estudiante
    fetchJson({ action: 'basic', id: studentId })
        .then(data => {
            const nombreCompleto = (data.Name || '') + ' ' + (data.Last_Name || '');
            document.getElementById('pm_name').textContent    = nombreCompleto.trim() || '—';
            document.getElementById('pm_email').textContent   = data.Email_Address || '—';
            document.getElementById('pm_license').textContent = data.License || 'No asignada';
            document.getElementById('pm_average').textContent = data.Average || '—';
            document.getElementById('pm_status').textContent  = data.status ? data.status : 'Sin Solicitud';
            errorBox.textContent = '';
        })
        .catch(err => {
            errorBox.textContent = 'No se pudo cargar la información: ' + err.message;
        });

    // 2) Solicitudes de beca del estudiante
    fetchJson({ action: 'applications', student_id: studentId })
        .then(data => {
            renderApplicationsInModal(studentId, data.applications || []);
        })
        .catch(err => {
            appsContainer.innerHTML = 'No se pudieron cargar las solicitudes: ' + err.message;
        });
}

// Pintar solicitudes en el modal
function renderApplicationsInModal(studentId, apps) {
    const container = document.getElementById('pm_applications');
    const VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada'];

    if (!apps || apps.length === 0) {
        container.innerHTML = '<p style="font-size:0.9rem;color:#666;">Este estudiante no tiene solicitudes de beca registradas.</p>';
        return;
    }

    let html = '';
    apps.forEach(app => {
        const selectId   = `status-select-${app.ID_Kit}`;
        const msgId      = `status-msg-${app.ID_Kit}`;
        const statusClass = statusClassFromText(app.ApplicationStatus);

        html += `
        <div class="modal-app-card">
            <h4>${escapeHtml(app.KitName)}</h4>
            <p><strong>ID Solicitud:</strong> #${app.ID_status}</p>
            <p>
                <strong>Estatus actual:</strong>
                <span class="status-pill status-${escapeHtml(statusClass)}">
                    ${escapeHtml(app.ApplicationStatus)}
                </span>
            </p>
            <p><strong>Fecha solicitud:</strong> ${escapeHtml(app.Application_date)}</p>
            <p><strong>Periodo:</strong> ${escapeHtml(app.Start_date)} al ${escapeHtml(app.End_date)}</p>
            <p><strong>Descripción:</strong> ${escapeHtml(app.KitDescription || '')}</p>

            <label for="${selectId}">Cambiar estatus a:</label>
            <select id="${selectId}">
                ${VALID_STATUSES.map(st => `
                    <option value="${st}" ${st === app.ApplicationStatus ? 'selected' : ''}>
                        ${st}
                    </option>
                `).join('')}
            </select>

            <button type="button"
                    onclick="updateApplicationStatus(${studentId}, ${app.ID_Kit}, '${selectId}', '${msgId}')">
                Actualizar estatus
            </button>

            <div id="${msgId}" class="status-update-msg"></div>
        </div>`;
    });

    container.innerHTML = html;
}

// Actualizar estatus vía AJAX
function updateApplicationStatus(studentId, kitId, selectId, msgId) {
    const select    = document.getElementById(selectId);
    const newStatus = select.value;
    const msgBox    = document.getElementById(msgId);

    let confirmText;
    if (newStatus === 'Cancelada') {
        confirmText = '¿Estás seguro de cancelar esta solicitud?\n\nLa solicitud será marcada como "Cancelada" y el estudiante será notificado.';
    } else if (newStatus === 'Rechazada') {
        confirmText = '¿Estás seguro de rechazar esta solicitud?\n\nLa solicitud será marcada como "Rechazada" y el estudiante será notificado.';
    } else {
        confirmText = '¿Confirmar cambio de estado a: ' + newStatus + '?';
    }

    if (!confirm(confirmText)) return;

    msgBox.textContent = 'Guardando...';

    fetchJson(
        { action: 'update_status' },
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'update_status',
                student_id: studentId,
                kit_id: kitId,
                new_status: newStatus
            })
        }
    )
    .then(data => {
        msgBox.textContent = data.message || 'Estatus actualizado.';

        const card = document.getElementById(selectId).closest('.modal-app-card');
        const pill = card.querySelector('.status-pill');
        pill.textContent = newStatus;
        pill.className = 'status-pill status-' + statusClassFromText(newStatus);
    })
    .catch(err => {
        msgBox.textContent = 'Error al actualizar: ' + err.message;
    });
}

// ====== Paginación + búsqueda + cierre modal ======
document.addEventListener('DOMContentLoaded', function () {
    const rows = Array.from(document.querySelectorAll('#studentTable tbody tr'));
    const rowsPerPage = 10;
    let currentPage = 1;

    rows.forEach(row => row.dataset.visible = '1');

    function renderPage(page) {
        const visibleRows = rows.filter(r => r.dataset.visible === '1');
        const total = visibleRows.length;
        const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));

        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        currentPage = page;

        const start = (page - 1) * rowsPerPage;
        const end   = start + rowsPerPage;

        rows.forEach(r => r.style.display = 'none');
        visibleRows.forEach((row, index) => {
            if (index >= start && index < end) row.style.display = '';
        });

        document.getElementById('currentPage').textContent = page;
        document.getElementById('prevBtn').disabled = (page === 1);
        document.getElementById('nextBtn').disabled = (page === totalPages);
    }

    document.getElementById('prevBtn').addEventListener('click', function () {
        renderPage(currentPage - 1);
    });

    document.getElementById('nextBtn').addEventListener('click', function () {
        renderPage(currentPage + 1);
    });

    const searchInput = document.getElementById('search');
    searchInput.addEventListener('keyup', function () {
        const filter = this.value.toLowerCase();

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = text.includes(filter);
            row.dataset.visible = match ? '1' : '0';
        });

        renderPage(1);
    });

    renderPage(1);

    const modal   = document.getElementById('profileModal');
    const closeBtn = document.getElementById('pm_close');

    closeBtn.addEventListener('click', () => {
        modal.classList.remove('open');
        document.body.classList.remove('modal-open');
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
        }
    });
});
</script>

</body>
</html>
