<?php
session_start();
require '../Conexiones/db.php';

// Guardián: solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
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
// CONSULTA MODIFICADA: Cada estudiante aparece UNA sola vez
$query = "
SELECT DISTINCT
    s.ID_Student,
    s.Name,
    s.Last_Name,
    sd.License,
    sd.Average
FROM student s
LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
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
    <title>Lista de Estudiantes - Panel Staff</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        h2 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid #eaeaea;
        }

        .search-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        #search {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid #d1d9e6;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
        }

        #search:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        thead {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #edf2f7;
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8fafc;
        }

        td {
            padding: 16px 15px;
            color: #4a5568;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .btn-perfil {
            background: #3498db;
            color: white;
            border: 1px solid #2980b9;
        }

        .btn-perfil:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-perfil.active {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
        }

        .pagination button {
            padding: 10px 24px;
            border: 1px solid #d1d9e6;
            border-radius: 6px;
            background: white;
            color: #4a5568;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pagination button:hover:not(:disabled) {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination button:disabled {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: not-allowed;
        }

        .pagination span {
            font-size: 1rem;
            color: #4a5568;
        }

        /* Estilos para la sección de detalles */
        .details-section {
            display: none;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin: 20px 0;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            animation: slideDown 0.3s ease;
        }

        .details-section.open {
            display: block;
        }

        .details-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 18px 25px;
            border-radius: 10px 10px 0 0;
            margin: -25px -25px 25px -25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .details-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .close-details-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s ease;
        }

        .close-details-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .details-error {
            background: #fee;
            color: #c33;
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
            text-align: center;
        }

        .details-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .details-row:last-child {
            border-bottom: none;
        }

        .details-row strong {
            min-width: 140px;
            color: #2c3e50;
            font-weight: 600;
        }

        .details-row span {
            color: #4a5568;
        }

        .details-hr {
            margin: 20px 0;
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, #3498db, transparent);
        }

        /* Estilo para el resumen de solicitudes */
        .applications-summary {
            background: #f8fafc;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            text-align: center;
        }

        .applications-count {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .applications-count span {
            color: #3498db;
            font-size: 1.4rem;
        }

        .applications-message {
            color: #4a5568;
            font-size: 1rem;
        }

        .app-card {
            background: #f8fafc;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
            border: 1px solid #e2e8f0;
        }

        .app-card h4 {
            color: #2c3e50;
            margin-bottom: 12px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .app-card p {
            margin: 6px 0;
            color: #4a5568;
            line-height: 1.5;
        }

        .app-card strong {
            color: #2c3e50;
        }

        .status-pill {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }

        .status-Enviada { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-Enrevisión { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-Aprobada { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-Rechazada { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-Entrega { background: #d1c4e9; color: #311b92; border: 1px solid #b39ddb; }
        .status-Cancelada { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .status-null { background: #f8f9fa; color: #6c757d; border: 1px solid #e9ecef; }

        .app-card label {
            display: block;
            margin: 15px 0 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .app-card select {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d9e6;
            border-radius: 6px;
            font-size: 0.95rem;
            margin-bottom: 15px;
            background: white;
            transition: border 0.2s ease;
        }

        .app-card select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .app-card button {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .app-card button:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .status-update-msg {
            margin-top: 12px;
            padding: 10px;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            font-size: 0.9rem;
        }

        .status-update-msg.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-update-msg.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        /* Contenedor para la sección de detalles */
        .details-container {
            margin-top: 30px;
        }
    </style>
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
            placeholder="Buscar por nombre, apellido, matrícula..."
            autocomplete="off">
    </div>

    <!-- Tabla -->
    <table id="studentTable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Matrícula</th>
                <th>Promedio</th>
                <th>Acciones</th>
            </tr>
        </thead>

        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr data-student-id="<?= (int)$row['ID_Student'] ?>">
                    <td><?= htmlspecialchars($row['Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['Last_Name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['License'] ?? 'No asignada') ?></td>
                    <td><?= htmlspecialchars($row['Average'] ?? '—') ?></td>
                    <td>
                        <button class="btn-action btn-perfil" type="button"
                            onclick="toggleStudentDetails(<?= (int)$row['ID_Student'] ?>)"
                            title="Ver detalles del estudiante">
                            |||
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Contenedor para los detalles -->
    <div class="details-container" id="detailsContainer">
        <!-- Los detalles se cargarán aquí -->
    </div>

    <!-- Paginación -->
    <div class="pagination">
        <button id="prevBtn">Anterior</button>
        <span>Página <span id="currentPage">1</span></span>
        <button id="nextBtn">Siguiente</button>
    </div>
</div>

<script>
// ===== Helpers =====
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    if (typeof str !== 'string') {
        str = String(str);
    }
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function statusClassFromText(status) {
    if (!status || typeof status !== 'string') return '';
    return status.replace(/\s+/g, '');
}

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

// ====== Manejo de detalles del estudiante ======
let currentOpenStudentId = null;

function toggleStudentDetails(studentId) {
    const container = document.getElementById('detailsContainer');
    const button = document.querySelector(`tr[data-student-id="${studentId}"] .btn-perfil`);
    
    // Si ya está abierto este estudiante, cerrarlo
    if (currentOpenStudentId === studentId) {
        container.innerHTML = '';
        button.classList.remove('active');
        currentOpenStudentId = null;
        return;
    }
    
    // Cerrar detalles anteriores si los hay
    if (currentOpenStudentId) {
        const oldButton = document.querySelector(`tr[data-student-id="${currentOpenStudentId}"] .btn-perfil`);
        if (oldButton) oldButton.classList.remove('active');
    }
    
    // Marcar botón como activo
    button.classList.add('active');
    
    // Mostrar mensaje de carga
    container.innerHTML = `
        <div class="details-section open">
            <div class="details-header">
                <h3>Cargando información...</h3>
                <button class="close-details-btn" onclick="toggleStudentDetails(${studentId})">&times;</button>
            </div>
            <div class="details-error">Cargando información del estudiante...</div>
        </div>
    `;
    
    // Desplazar a la sección de detalles
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Cargar información del estudiante
    loadStudentDetails(studentId);
}

function loadStudentDetails(studentId) {
    const container = document.getElementById('detailsContainer');
    
    // 1) Datos básicos del estudiante
    fetchJson({ action: 'basic', id: studentId })
        .then(studentData => {
            // 2) Solicitudes de beca del estudiante
            return fetchJson({ action: 'applications', student_id: studentId })
                .then(appsData => {
                    renderStudentDetails(studentId, studentData, appsData.applications || []);
                    currentOpenStudentId = studentId;
                });
        })
        .catch(err => {
            container.innerHTML = `
                <div class="details-section open">
                    <div class="details-header">
                        <h3>Error</h3>
                        <button class="close-details-btn" onclick="toggleStudentDetails(${studentId})">&times;</button>
                    </div>
                    <div class="details-error">Error al cargar la información: ${err.message || 'Error desconocido'}</div>
                </div>
            `;
        });
}

function renderStudentDetails(studentId, studentData, applications) {
    const container = document.getElementById('detailsContainer');
    const VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada'];
    
    // Asegurarnos de que los valores sean strings
    const name = studentData.Name || '';
    const lastName = studentData.Last_Name || '';
    const email = studentData.Email_Address || '';
    const license = studentData.License || '';
    const average = studentData.Average || '';
    const status = studentData.status || '';
    
    const nombreCompleto = name + ' ' + lastName;
    const totalApplications = applications ? applications.length : 0;
    
    let html = `
        <div class="details-section open">
            <div class="details-header">
                <h3>Perfil del estudiante</h3>
                <button class="close-details-btn" onclick="toggleStudentDetails(${studentId})">&times;</button>
            </div>
            
            <div class="details-error" style="display: none;"></div>
            
            <div class="details-row">
                <strong>Nombre:</strong>
                <span>${escapeHtml(nombreCompleto.trim() || '—')}</span>
            </div>
            
            <div class="details-row">
                <strong>Correo:</strong>
                <span>${escapeHtml(email || '—')}</span>
            </div>
            
            <div class="details-row">
                <strong>Matrícula:</strong>
                <span>${escapeHtml(license || 'No asignada')}</span>
            </div>
            
            <div class="details-row">
                <strong>Promedio:</strong>
                <span>${escapeHtml(average || '—')}</span>
            </div>
            
            <div class="details-row">
                <strong>Estatus:</strong>
                <span>${escapeHtml(status || 'Sin Solicitud')}</span>
            </div>
            
            <div class="details-hr"></div>
            
            <h4 style="color: #2c3e50; margin-bottom: 20px; font-size: 1.3rem;">Solicitudes de beca</h4>
    `;
    
    if (totalApplications === 0) {
        html += '<div style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 6px; color: #666;">Este estudiante no tiene solicitudes de beca registradas.</div>';
    } else {
        // Mostrar primero el mensaje con el número total de solicitudes
        html += `
            <div class="applications-summary">
                <div class="applications-count">
                    Este estudiante tiene <span>${totalApplications}</span> solicitud(es) de beca registrada(s).
                </div>
                <div class="applications-message">
                    A continuación se muestran los detalles de cada solicitud:
                </div>
            </div>
        `;
        
        // Ahora mostrar cada solicitud individual
        applications.forEach(app => {
            const selectId   = `status-select-${studentId}-${app.ID_Kit}`;
            const msgId      = `status-msg-${studentId}-${app.ID_Kit}`;
            const statusClass = statusClassFromText(app.ApplicationStatus || '');
            
            // Asegurarnos de que los valores sean strings
            const kitName = app.KitName || '';
            const appStatus = app.ApplicationStatus || '';
            const appDate = app.Application_date || '';
            const startDate = app.Start_date || '';
            const endDate = app.End_date || '';
            const idStatus = app.ID_status || '';

            html += `
            <div class="app-card">
                <h4>${escapeHtml(kitName)}</h4>
                <p><strong>ID Solicitud:</strong> #${escapeHtml(idStatus)}</p>
                <p>
                    <strong>Estatus actual:</strong>
                    <span class="status-pill status-${escapeHtml(statusClass)}">
                        ${escapeHtml(appStatus)}
                    </span>
                </p>
                <p><strong>Fecha solicitud:</strong> ${escapeHtml(appDate)}</p>
                <p><strong>Periodo:</strong> ${escapeHtml(startDate)} al ${escapeHtml(endDate)}</p>

                <label for="${selectId}">Cambiar estatus a:</label>
                <select id="${selectId}">
                    ${VALID_STATUSES.map(st => `
                        <option value="${st}" ${st === appStatus ? 'selected' : ''}>
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
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Actualizar estatus vía AJAX
function updateApplicationStatus(studentId, kitId, selectId, msgId) {
    const select = document.getElementById(selectId);
    const newStatus = select.value;
    const msgBox = document.getElementById(msgId);

    let confirmText;
    if (newStatus === 'Cancelada') {
        confirmText = '¿Estás seguro de cancelar esta solicitud?\n\nLa solicitud será marcada como "Cancelada" y NO podrá ser solicitada nuevamente.';
    } else if (newStatus === 'Rechazada') {
        confirmText = '¿Estás seguro de rechazar esta solicitud?\n\nLa solicitud será marcada como "Rechazada" y NO podrá ser solicitada nuevamente.';
    } else {
        confirmText = '¿Confirmar cambio de estado a: ' + newStatus + '?';
    }

    if (!confirm(confirmText)) return;

    msgBox.textContent = 'Guardando...';
    msgBox.className = 'status-update-msg';

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
        msgBox.className = 'status-update-msg success';

        const card = document.getElementById(selectId).closest('.app-card');
        const pill = card.querySelector('.status-pill');
        pill.textContent = newStatus;
        pill.className = 'status-pill status-' + statusClassFromText(newStatus);
    })
    .catch(err => {
        msgBox.textContent = 'Error al actualizar: ' . err.message;
        msgBox.className = 'status-update-msg error';
    });
}

// ====== Paginación + búsqueda ======
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
        // Cerrar detalles si están abiertos al buscar
        if (currentOpenStudentId) {
            toggleStudentDetails(currentOpenStudentId);
        }
    });

    renderPage(1);
});
</script>

</body>
</html>