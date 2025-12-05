<?php
session_start();
// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

include '../Conexiones/db.php';
$conexion = $conn;

// Manejar cambios de estado CON MENSAJE
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accion'], $_POST['id_solicitud'])) {
        $id_solicitud = (int)$_POST['id_solicitud'];
        $accion = $_POST['accion'];
        $mensaje_staff = isset($_POST['mensaje_staff']) ? $conexion->real_escape_string($_POST['mensaje_staff']) : '';
        
        $estados = [
            'aprobar' => 'Approved',
            'rechazar' => 'Rejected',
            'entregar' => 'Delivered',
            'cancelar' => 'Canceled'
        ];
        
        if (isset($estados[$accion])) {
            $nuevo_estado = $estados[$accion];
            
            // Si es cancelación del staff, guardar mensaje
            if ($accion == 'cancelar' && !empty($mensaje_staff)) {
                $sql_crear_tabla = "CREATE TABLE IF NOT EXISTS historial_mensajes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_solicitud INT NOT NULL,
                    accion VARCHAR(20) NOT NULL,
                    mensaje TEXT,
                    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                    staff_id INT
                )";
                $conexion->query($sql_crear_tabla);
                
                $staff_id = $_SESSION['usuario_id'];
                $sql_mensaje = "INSERT INTO historial_mensajes (id_solicitud, accion, mensaje, staff_id) 
                                VALUES (?, ?, ?, ?)";
                $stmt_msg = $conexion->prepare($sql_mensaje);
                $stmt_msg->bind_param("issi", $id_solicitud, $accion, $mensaje_staff, $staff_id);
                $stmt_msg->execute();
                $stmt_msg->close();
            }
            
            // Actualizar estado de la solicitud
            $sql_update = "UPDATE aplication SET status = ? WHERE ID_status = ?";
            $stmt = $conexion->prepare($sql_update);
            $stmt->bind_param("si", $nuevo_estado, $id_solicitud);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Solicitud #$id_solicitud actualizada a: " . ucfirst($accion) . "do";
                $_SESSION['tipo_mensaje'] = 'success';
            } else {
                $_SESSION['mensaje'] = "Error al actualizar la solicitud";
                $_SESSION['tipo_mensaje'] = 'error';
            }
            $stmt->close();
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Determinar qué vista mostrar
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'solicitudes';

// Obtener filtros
$filtro_kit = isset($_GET['kit']) ? (int)$_GET['kit'] : 0;
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_nombre = isset($_GET['nombre']) ? $conexion->real_escape_string($_GET['nombre']) : '';
$filtro_email = isset($_GET['email']) ? $conexion->real_escape_string($_GET['email']) : '';

if ($vista == 'solicitudes') {
    // VISTA DE SOLICITUDES - SOLO ACTIVAS (NO CANCELADAS POR NADIE)
    
    // Obtener kits para filtro
    $sql_kits = "SELECT k.ID_Kit, CONCAT('Kit ', s.Period, ' ', s.Year) AS nombre_kit
                 FROM kit k
                 INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
                 ORDER BY k.Start_date DESC";
    $result_kits = $conexion->query($sql_kits);
    $kits_disponibles = [];
    while ($kit = $result_kits->fetch_assoc()) {
        $kits_disponibles[] = $kit;
    }

    // Construir consulta - EXCLUIR TODAS LAS CANCELADAS (estudiante o staff)
    $sql = "SELECT 
            a.ID_status,
            s.Name AS estudiante_nombre,
            s.Last_Name AS estudiante_apellido,
            s.Email_Address AS estudiante_email,
            a.status,
            a.FK_ID_Kit,
            s.ID_Student,
            DATE_FORMAT(a.Application_date, '%d/%m/%Y %H:%i') AS fecha_solicitud
            FROM aplication a
            JOIN student s ON a.FK_ID_Student = s.ID_Student
            WHERE a.status != 'Canceled'"; // No mostrar NINGUNA cancelada

    $params = [];
    $types = '';

    if ($filtro_kit > 0) {
        $sql .= " AND a.FK_ID_Kit = ?";
        $params[] = $filtro_kit;
        $types .= 'i';
    }

    if (!empty($filtro_estado)) {
        $sql .= " AND a.status = ?";
        $params[] = $filtro_estado;
        $types .= 's';
    }

    if (!empty($filtro_nombre)) {
        $sql .= " AND (s.Name LIKE ? OR s.Last_Name LIKE ?)";
        $params[] = "%$filtro_nombre%";
        $params[] = "%$filtro_nombre%";
        $types .= 'ss';
    }

    if (!empty($filtro_email)) {
        $sql .= " AND s.Email_Address LIKE ?";
        $params[] = "%$filtro_email%";
        $types .= 's';
    }

    $sql .= " ORDER BY a.ID_status DESC";

    // Preparar y ejecutar consulta
    $stmt = $conexion->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $solicitudes = [];

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $kit_id = $row['FK_ID_Kit'];
            
            if ($kit_id) {
                $sql_kit = "SELECT k.Start_date, k.End_date, se.Period, se.Year 
                           FROM kit k 
                           LEFT JOIN semester se ON k.FK_ID_Semester = se.ID_Semester 
                           WHERE k.ID_Kit = ?";
                $stmt_kit = $conexion->prepare($sql_kit);
                $stmt_kit->bind_param("i", $kit_id);
                $stmt_kit->execute();
                $result_kit = $stmt_kit->get_result();
                
                if ($kit_data = $result_kit->fetch_assoc()) {
                    $row['Start_date'] = $kit_data['Start_date'];
                    $row['End_date'] = $kit_data['End_date'];
                    $row['Period'] = $kit_data['Period'];
                    $row['Year'] = $kit_data['Year'];
                    $row['Nombre_Kit'] = 'Kit ' . $kit_data['Period'] . ' ' . $kit_data['Year'];
                } else {
                    $row['Start_date'] = 'No disponible';
                    $row['End_date'] = 'No disponible';
                    $row['Period'] = 'N/A';
                    $row['Year'] = 'N/A';
                    $row['Nombre_Kit'] = 'Kit no encontrado';
                }
                $stmt_kit->close();
                
                $sql_materiales = "SELECT sup.Name, km.Unit 
                                  FROM kit_material km 
                                  LEFT JOIN supplies sup ON km.FK_ID_Supply = sup.ID_Supply 
                                  WHERE km.FK_ID_Kit = ?";
                $stmt_mat = $conexion->prepare($sql_materiales);
                $stmt_mat->bind_param("i", $kit_id);
                $stmt_mat->execute();
                $result_mat = $stmt_mat->get_result();
                
                $materiales = [];
                while ($mat = $result_mat->fetch_assoc()) {
                    $materiales[] = $mat['Name'] . ' (' . $mat['Unit'] . ')';
                }
                $row['materiales'] = !empty($materiales) ? implode(', ', $materiales) : 'Sin materiales';
                $stmt_mat->close();
            } else {
                $row['Start_date'] = 'No disponible';
                $row['End_date'] = 'No disponible';
                $row['Period'] = 'N/A';
                $row['Year'] = 'N/A';
                $row['Nombre_Kit'] = 'Sin kit asignado';
                $row['materiales'] = 'Sin materiales';
            }
            
            $solicitudes[] = $row;
        }
    }
    $stmt->close();

    // Calcular estadísticas (sin contar canceladas)
    $total = count($solicitudes);
    $pendientes = array_filter($solicitudes, function($s) { return $s['status'] == 'Pending'; });
    $aprobadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Approved'; });
    $rechazadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Rejected'; });
    $entregadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Delivered'; });

} else {
    // VISTA DE KITS DISPONIBLES - Solo contar activas
    
    $sql_kits_completos = "SELECT 
        k.ID_Kit,
        CONCAT('Kit ', s.Period, ' ', s.Year) AS nombre_kit,
        k.Start_date,
        k.End_date,
        s.Period,
        s.Year,
        COUNT(CASE WHEN a.status != 'Canceled' THEN a.ID_status END) AS total_solicitudes,
        SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN a.status = 'Approved' THEN 1 ELSE 0 END) AS aprobadas,
        SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) AS rechazadas,
        SUM(CASE WHEN a.status = 'Delivered' THEN 1 ELSE 0 END) AS entregadas
    FROM kit k
    INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
    LEFT JOIN aplication a ON k.ID_Kit = a.FK_ID_Kit AND a.status != 'Canceled'
    GROUP BY k.ID_Kit
    ORDER BY k.Start_date DESC";
    
    $result_kits_completos = $conexion->query($sql_kits_completos);
    $kits_completos = [];
    
    while ($kit = $result_kits_completos->fetch_assoc()) {
        $sql_materiales_kit = "SELECT sup.Name, km.Unit 
                              FROM kit_material km 
                              LEFT JOIN supplies sup ON km.FK_ID_Supply = sup.ID_Supply 
                              WHERE km.FK_ID_Kit = ?";
        $stmt_mat_kit = $conexion->prepare($sql_materiales_kit);
        $stmt_mat_kit->bind_param("i", $kit['ID_Kit']);
        $stmt_mat_kit->execute();
        $result_mat_kit = $stmt_mat_kit->get_result();
        
        $materiales_kit = [];
        while ($mat = $result_mat_kit->fetch_assoc()) {
            $materiales_kit[] = $mat['Name'] . ' (' . $mat['Unit'] . ')';
        }
        $kit['materiales'] = !empty($materiales_kit) ? implode(', ', $materiales_kit) : 'Sin materiales';
        $stmt_mat_kit->close();
        
        $kits_completos[] = $kit;
    }
    
    $total_kits = count($kits_completos);
    $kits_con_solicitudes = array_filter($kits_completos, function($k) { return $k['total_solicitudes'] > 0; });
    $kits_sin_solicitudes = array_filter($kits_completos, function($k) { return $k['total_solicitudes'] == 0; });
    $total_solicitudes_todas = array_sum(array_column($kits_completos, 'total_solicitudes'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Kits</title>
    <style>
        /* ESTILOS EXCLUSIVOS PARA ESTA PÁGINA */
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2196F3;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: #2196F3;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .view-toggle {
            display: inline-flex;
            background: #e9ecef;
            border-radius: 4px;
            padding: 2px;
            margin-top: 5px;
        }
        
        .toggle-btn {
            padding: 4px 12px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            background: transparent;
            color: #666;
        }
        
        .toggle-btn.active {
            background: #2196F3;
            color: white;
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
        }
        
        .toggle-btn:hover:not(.active) {
            background: #dee2e6;
        }
        
        .filters-panel {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-item label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }
        
        .filter-item select,
        .filter-item input {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            background: white;
            width: 100%;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-filter {
            padding: 8px 15px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        
        .btn-clear {
            padding: 8px 15px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        
        .data-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .fixed-width-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        .fixed-width-table th {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }
        
        .fixed-width-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
            font-size: 13px;
        }
        
        .fixed-width-table tr:hover {
            background: #f9f9f9;
        }
        
        /* ANCHOS DE COLUMNAS */
        .fixed-width-table th:nth-child(1), .fixed-width-table td:nth-child(1) { width: 50px; }
        .fixed-width-table th:nth-child(2), .fixed-width-table td:nth-child(2) { width: 200px; }
        .fixed-width-table th:nth-child(3), .fixed-width-table td:nth-child(3) { width: 180px; }
        .fixed-width-table th:nth-child(4), .fixed-width-table td:nth-child(4) { width: 120px; }
        .fixed-width-table th:nth-child(5), .fixed-width-table td:nth-child(5) { width: 120px; }
        .fixed-width-table th:nth-child(6), .fixed-width-table td:nth-child(6) { width: 250px; }
        .fixed-width-table th:nth-child(7), .fixed-width-table td:nth-child(7) { width: 140px; }
        .fixed-width-table th:nth-child(8), .fixed-width-table td:nth-child(8) { width: 100px; }
        
        /* PARA TABLA DE KITS */
        .kit-table th:nth-child(1), .kit-table td:nth-child(1) { width: 50px; }
        .kit-table th:nth-child(2), .kit-table td:nth-child(2) { width: 150px; }
        .kit-table th:nth-child(3), .kit-table td:nth-child(3) { width: 120px; }
        .kit-table th:nth-child(4), .kit-table td:nth-child(4) { width: 150px; }
        .kit-table th:nth-child(5), .kit-table td:nth-child(5) { width: 300px; }
        .kit-table th:nth-child(6), .kit-table td:nth-child(6) { width: 200px; }
        
        /* REMOVIDAS LAS CLASES DE COLORES DE ESTADO */
        .status-tag {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 80px;
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }
        
        .stats-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        
        .stat-tag {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .tag-total { background: #2196F3; color: white; }
        .tag-pending { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .tag-approved { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .tag-rejected { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .tag-delivered { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; }
        .tag-empty { background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        /* MODAL PARA CANCELAR */
        .modal-cancel {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #555;
            font-weight: 500;
        }
        
        .form-field textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-modal-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-modal-confirm {
            background: #2196F3;
            color: white;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .filters-panel {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .data-table {
                overflow-x: auto;
            }
            
            .fixed-width-table {
                min-width: 1000px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-panel {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .btn-filter, .btn-clear {
                width: 100%;
            }
            
            .content-wrapper {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuStaff.php'; ?>
    
    <div class="content-wrapper">
        <!-- TÍTULO DE LA PÁGINA -->
        <h1 class="page-title">Gestión de Solicitudes y Kits</h1>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div style="background-color: <?php echo $_SESSION['tipo_mensaje'] == 'success' ? '#d4edda' : '#f8d7da'; ?>; 
                        color: <?php echo $_SESSION['tipo_mensaje'] == 'success' ? '#155724' : '#721c24'; ?>;
                        padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid <?php echo $_SESSION['tipo_mensaje'] == 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
                <?php echo $_SESSION['mensaje']; ?>
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        <?php endif; ?>
        
        <?php if ($vista == 'solicitudes'): ?>
            <!-- VISTA DE SOLICITUDES ACTIVAS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Solicitudes Activas</div>
                    <div class="stat-number"><?php echo $total; ?></div>
                    <div class="view-toggle">
                        <button class="toggle-btn active" onclick="window.location.href='?vista=solicitudes'">
                            Solicitudes
                        </button>
                        <button class="toggle-btn" onclick="window.location.href='?vista=kits'">
                            Kits
                        </button>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pendientes</div>
                    <div class="stat-number"><?php echo count($pendientes); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Aprobadas</div>
                    <div class="stat-number"><?php echo count($aprobadas); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Rechazadas</div>
                    <div class="stat-number"><?php echo count($rechazadas); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Entregadas</div>
                    <div class="stat-number"><?php echo count($entregadas); ?></div>
                </div>
            </div>
            
            <form method="GET" action="" class="filters-panel">
                <input type="hidden" name="vista" value="solicitudes">
                
                <div class="filter-item">
                    <label>Filtrar por Kit</label>
                    <select name="kit">
                        <option value="">Todos los kits</option>
                        <?php foreach ($kits_disponibles as $kit): ?>
                            <option value="<?php echo $kit['ID_Kit']; ?>" 
                                <?php echo ($filtro_kit == $kit['ID_Kit']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kit['nombre_kit']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Filtrar por Estado</label>
                    <select name="estado">
                        <option value="">Todos los estados</option>
                        <option value="Pending" <?php echo ($filtro_estado == 'Pending') ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="Approved" <?php echo ($filtro_estado == 'Approved') ? 'selected' : ''; ?>>Aprobado</option>
                        <option value="Rejected" <?php echo ($filtro_estado == 'Rejected') ? 'selected' : ''; ?>>Rechazado</option>
                        <option value="Delivered" <?php echo ($filtro_estado == 'Delivered') ? 'selected' : ''; ?>>Entregado</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Buscar por Nombre</label>
                    <input type="text" name="nombre" placeholder="Nombre del estudiante" 
                           value="<?php echo htmlspecialchars($filtro_nombre); ?>">
                </div>
                
                <div class="filter-item">
                    <label>Buscar por Email</label>
                    <input type="text" name="email" placeholder="Email del estudiante" 
                           value="<?php echo htmlspecialchars($filtro_email); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">
                        Aplicar Filtros
                    </button>
                    
                    <button type="button" class="btn-clear" onclick="window.location.href='?vista=solicitudes'">
                        Limpiar Filtros
                    </button>
                </div>
            </form>
            
            <?php if (empty($solicitudes)): ?>
                <div class="no-data">
                    <h3>No hay solicitudes activas</h3>
                    <p>Las solicitudes canceladas no se muestran en esta lista.</p>
                </div>
            <?php else: ?>
                <div class="data-table">
                    <table class="fixed-width-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Estudiante</th>
                                <th>Email</th>
                                <th>Kit</th>
                                <th>Fecha</th>
                                <th>Materiales</th>
                                <th>Período</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $solicitud): ?>
                                <?php 
                                // Determinar texto según estado
                                $texto_estado = '';
                                $estado = trim($solicitud['status']);
                                
                                switch($estado) {
                                    case 'Pending': 
                                        $texto_estado = 'Pendiente';
                                        break;
                                    case 'Approved': 
                                        $texto_estado = 'Aprobado';
                                        break;
                                    case 'Rejected': 
                                        $texto_estado = 'Rechazado';
                                        break;
                                    case 'Delivered': 
                                        $texto_estado = 'Entregado';
                                        break;
                                    case 'Canceled': 
                                        $texto_estado = 'Cancelado';
                                        break;
                                    default: 
                                        $texto_estado = $solicitud['status'];
                                        break;
                                }
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $solicitud['ID_status']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['estudiante_apellido']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($solicitud['estudiante_email']); ?></td>
                                    <td><?php echo htmlspecialchars($solicitud['Nombre_Kit']); ?></td>
                                    <td><?php echo htmlspecialchars($solicitud['fecha_solicitud'] ?? 'N/A'); ?></td>
                                    <td title="<?php echo htmlspecialchars($solicitud['materiales']); ?>">
                                        <?php 
                                        $materiales = $solicitud['materiales'];
                                        echo htmlspecialchars(strlen($materiales) > 30 ? substr($materiales, 0, 30) . '...' : $materiales);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($solicitud['Start_date'] != 'No disponible'): ?>
                                            <?php echo date('d/m/Y', strtotime($solicitud['Start_date'])) . ' - ' . 
                                                   date('d/m/Y', strtotime($solicitud['End_date'])); ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-tag">
                                            <?php echo $texto_estado; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- VISTA DE KITS DISPONIBLES -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Kits</div>
                    <div class="stat-number"><?php echo $total_kits; ?></div>
                    <div class="view-toggle">
                        <button class="toggle-btn" onclick="window.location.href='?vista=solicitudes'">
                            Solicitudes
                        </button>
                        <button class="toggle-btn active" onclick="window.location.href='?vista=kits'">
                            Kits
                        </button>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Con Solicitudes Activas</div>
                    <div class="stat-number"><?php echo count($kits_con_solicitudes); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sin Solicitudes</div>
                    <div class="stat-number"><?php echo count($kits_sin_solicitudes); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Solicitudes Activas</div>
                    <div class="stat-number"><?php echo $total_solicitudes_todas; ?></div>
                </div>
            </div>
            
            <?php if (empty($kits_completos)): ?>
                <div class="no-data">
                    <h3>No hay kits registrados</h3>
                    <p>No se encontraron kits en el sistema.</p>
                </div>
            <?php else: ?>
                <div class="data-table">
                    <table class="fixed-width-table kit-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Kit</th>
                                <th>Período</th>
                                <th>Vigencia</th>
                                <th>Materiales</th>
                                <th>Estadísticas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kits_completos as $kit): ?>
                                <tr>
                                    <td><strong>#<?php echo $kit['ID_Kit']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($kit['nombre_kit']); ?></strong>
                                        <div>
                                            <a href="?vista=solicitudes&kit=<?php echo $kit['ID_Kit']; ?>" class="mini-view">
                                                Ver Solicitudes
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($kit['Period'] . ' ' . $kit['Year']); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($kit['Start_date'])) . ' - ' . 
                                               date('d/m/Y', strtotime($kit['End_date'])); ?>
                                    </td>
                                    <td title="<?php echo htmlspecialchars($kit['materiales']); ?>">
                                        <?php 
                                        $materiales = $kit['materiales'];
                                        echo htmlspecialchars(strlen($materiales) > 40 ? substr($materiales, 0, 40) . '...' : $materiales);
                                        ?>
                                    </td>
                                    <td>
                                        <div class="stats-tags">
                                            <?php if ($kit['total_solicitudes'] > 0): ?>
                                                <span class="stat-tag tag-total" title="Total solicitudes activas">
                                                    <?php echo $kit['total_solicitudes']; ?>
                                                </span>
                                                <?php if ($kit['pendientes'] > 0): ?>
                                                    <span class="stat-tag tag-pending" title="Pendientes">
                                                        <?php echo $kit['pendientes']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($kit['aprobadas'] > 0): ?>
                                                    <span class="stat-tag tag-approved" title="Aprobadas">
                                                        <?php echo $kit['aprobadas']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($kit['rechazadas'] > 0): ?>
                                                    <span class="stat-tag tag-rejected" title="Rechazadas">
                                                        <?php echo $kit['rechazadas']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($kit['entregadas'] > 0): ?>
                                                    <span class="stat-tag tag-delivered" title="Entregadas">
                                                        <?php echo $kit['entregadas']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="stat-tag tag-empty">
                                                    Sin solicitudes
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- MODAL PARA CANCELAR CON MENSAJE -->
    <div id="modalCancelar" class="modal-cancel">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title">Cancelar Solicitud</h3>
                <button class="close-modal" onclick="cerrarModal()">×</button>
            </div>
            <div class="modal-body">
                <p class="modal-message" id="modalTexto">
                    ¿Cancelar esta solicitud? Ingresa un motivo (opcional):
                </p>
                <form id="formCancelar" method="POST">
                    <input type="hidden" name="id_solicitud" id="modalIdSolicitud">
                    <input type="hidden" name="accion" value="cancelar">
                    
                    <div class="form-field">
                        <label for="mensajeCancelacion">Motivo de cancelación:</label>
                        <textarea id="mensajeCancelacion" name="mensaje_staff" 
                                  placeholder="Ej: Documentación incompleta, fecha límite excedida, etc."></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="modal-btn btn-modal-cancel" onclick="cerrarModal()">
                            No Cancelar
                        </button>
                        <button type="submit" class="modal-btn btn-modal-confirm">
                            Confirmar Cancelación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Función para abrir modal de cancelación
        function abrirModalCancelar(idSolicitud) {
            document.getElementById('modalIdSolicitud').value = idSolicitud;
            document.getElementById('modalTexto').textContent = 
                `¿Cancelar la solicitud #${idSolicitud}? Ingresa un motivo (opcional):`;
            document.getElementById('modalCancelar').style.display = 'flex';
        }
        
        // Función para cerrar modal
        function cerrarModal() {
            document.getElementById('modalCancelar').style.display = 'none';
            document.getElementById('mensajeCancelacion').value = '';
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('modalCancelar').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
        
        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
        
        // Confirmación en formulario de cancelación
        document.getElementById('formCancelar').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const idSolicitud = document.getElementById('modalIdSolicitud').value;
            
            if (confirm(`¿Estás seguro de cancelar la solicitud #${idSolicitud}?`)) {
                this.submit();
            }
        });
        
        // Prevenir reenvío de formulario
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>