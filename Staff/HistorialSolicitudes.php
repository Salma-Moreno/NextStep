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
            'aprobar' => 'Aprobada',      // Cambiado de 'Approved'
            'rechazar' => 'Rechazada',    // Cambiado de 'Rejected'  
            'entregar' => 'Entrega',      // Cambiado de 'Delivered'
            'cancelar' => 'Cancelada'     // Cambiado de 'Canceled'
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

// ====== AGREGADO: CONFIGURACIÓN DE PAGINACIÓN ======
$registros_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

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

    // ====== MODIFICADO: Consulta base para contar total ======
    $sql_base = "SELECT 
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
            WHERE a.status != 'Cancelada' AND a.status != 'Canceled'";

    $where_conditions = [];
    $params = [];
    $types = '';

    if ($filtro_kit > 0) {
        $where_conditions[] = "a.FK_ID_Kit = ?";
        $params[] = $filtro_kit;
        $types .= 'i';
    }

    if (!empty($filtro_estado)) {
        $where_conditions[] = "a.status = ?";
        $params[] = $filtro_estado;
        $types .= 's';
    }

    if (!empty($filtro_nombre)) {
        $where_conditions[] = "(s.Name LIKE ? OR s.Last_Name LIKE ?)";
        $params[] = "%$filtro_nombre%";
        $params[] = "%$filtro_nombre%";
        $types .= 'ss';
    }

    if (!empty($filtro_email)) {
        $where_conditions[] = "s.Email_Address LIKE ?";
        $params[] = "%$filtro_email%";
        $types .= 's';
    }

    // ====== AGREGADO: Contar total de registros ======
    $sql_total = $sql_base;
    if (!empty($where_conditions)) {
        $sql_total .= " AND " . implode(" AND ", $where_conditions);
    }
    
    // Contar total
    $stmt_total = $conexion->prepare($sql_total);
    if (!empty($params)) {
        $stmt_total->bind_param($types, ...$params);
    }
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_registros = $result_total->num_rows;
    $stmt_total->close();

    // ====== AGREGADO: Calcular páginas ======
    $total_paginas = ceil($total_registros / $registros_por_pagina);
    
    // Ajustar página si es necesario
    if ($pagina_actual > $total_paginas && $total_paginas > 0) {
        $pagina_actual = $total_paginas;
        $offset = ($pagina_actual - 1) * $registros_por_pagina;
    }

    // ====== MODIFICADO: Consulta con LIMIT y OFFSET ======
    $sql_datos = $sql_base;
    if (!empty($where_conditions)) {
        $sql_datos .= " AND " . implode(" AND ", $where_conditions);
    }
    $sql_datos .= " ORDER BY a.ID_status DESC LIMIT ? OFFSET ?";

    // ====== AGREGADO: Parámetros de paginación ======
    $params_paginacion = $params;
    $types_paginacion = $types . 'ii';
    $params_paginacion[] = $registros_por_pagina;
    $params_paginacion[] = $offset;

    // Preparar y ejecutar consulta con paginación
    $stmt = $conexion->prepare($sql_datos);
    if (!empty($params_paginacion)) {
        $stmt->bind_param($types_paginacion, ...$params_paginacion);
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

    // ====== MODIFICADO: Para estadísticas necesitamos TODOS los registros ======
    $sql_estadisticas = $sql_total;
    $stmt_estad = $conexion->prepare($sql_estadisticas);
    if (!empty($params)) {
        $stmt_estad->bind_param($types, ...$params);
    }
    $stmt_estad->execute();
    $result_estad = $stmt_estad->get_result();
    
    $todas_solicitudes = [];
    while ($row = $result_estad->fetch_assoc()) {
        $todas_solicitudes[] = $row;
    }
    $stmt_estad->close();

    // Calcular estadísticas (sin contar canceladas)
    // CONTAR CON LOS NUEVOS ESTADOS
    $total = count($todas_solicitudes);
    $pendientes = array_filter($todas_solicitudes, function($s) { 
        return $s['status'] == 'Enviada' || $s['status'] == 'En revisión'; 
    });
    $aprobadas = array_filter($todas_solicitudes, function($s) { 
        return $s['status'] == 'Aprobada'; 
    });
    $rechazadas = array_filter($todas_solicitudes, function($s) { 
        return $s['status'] == 'Rechazada'; 
    });
    $entregadas = array_filter($todas_solicitudes, function($s) { 
        return $s['status'] == 'Entrega'; 
    });

} else {
    // VISTA DE KITS DISPONIBLES - Solo contar activas
    
    // ====== AGREGADO: Paginación para kits también ======
    $kits_por_pagina = 5;
    $pagina_actual_kits = isset($_GET['pagina_kits']) ? max(1, (int)$_GET['pagina_kits']) : 1;
    $offset_kits = ($pagina_actual_kits - 1) * $kits_por_pagina;
    
    // Contar total de kits
    $sql_count_kits = "SELECT COUNT(*) as total FROM kit";
    $result_count = $conexion->query($sql_count_kits);
    $row_count = $result_count->fetch_assoc();
    $total_kits_registros = $row_count['total'];
    $total_paginas_kits = ceil($total_kits_registros / $kits_por_pagina);
    
    // Ajustar página si es necesario
    if ($pagina_actual_kits > $total_paginas_kits && $total_paginas_kits > 0) {
        $pagina_actual_kits = $total_paginas_kits;
        $offset_kits = ($pagina_actual_kits - 1) * $kits_por_pagina;
    }
    
    // ====== MODIFICADO: Agregar LIMIT y OFFSET ======
    $sql_kits_completos = "SELECT 
        k.ID_Kit,
        CONCAT('Kit ', s.Period, ' ', s.Year) AS nombre_kit,
        k.Start_date,
        k.End_date,
        s.Period,
        s.Year,
        COUNT(CASE WHEN a.status NOT IN ('Cancelada', 'Canceled') THEN a.ID_status END) AS total_solicitudes,
        SUM(CASE WHEN a.status IN ('Enviada', 'En revisión') THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN a.status = 'Aprobada' THEN 1 ELSE 0 END) AS aprobadas,
        SUM(CASE WHEN a.status = 'Rechazada' THEN 1 ELSE 0 END) AS rechazadas,
        SUM(CASE WHEN a.status = 'Entrega' THEN 1 ELSE 0 END) AS entregadas
    FROM kit k
    INNER JOIN semester s ON k.FK_ID_Semester = s.ID_Semester
    LEFT JOIN aplication a ON k.ID_Kit = a.FK_ID_Kit AND a.status NOT IN ('Cancelada', 'Canceled')
    GROUP BY k.ID_Kit
    ORDER BY k.Start_date DESC
    LIMIT ? OFFSET ?";  // ====== AGREGADO: LIMIT y OFFSET ======
    
    $stmt_kits = $conexion->prepare($sql_kits_completos);
    $stmt_kits->bind_param("ii", $kits_por_pagina, $offset_kits);
    $stmt_kits->execute();
    $result_kits_completos = $stmt_kits->get_result();
    
    $kits_completos = [];
    $kits_con_solicitudes = 0;
    $kits_sin_solicitudes = 0;
    $total_solicitudes_todas = 0;
    
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
        
        // Contar estadísticas
        if ($kit['total_solicitudes'] > 0) {
            $kits_con_solicitudes++;
            $total_solicitudes_todas += $kit['total_solicitudes'];
        } else {
            $kits_sin_solicitudes++;
        }
    }
    $stmt_kits->close();
    
    $total_kits = $total_kits_registros;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Kits</title>
    <style>
        /* TODO TU CSS ORIGINAL SE CONSERVA INTACTO */
        * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
        body {
    margin: 0;
    padding: 0;
    font-family: "Arial", sans-serif;
    background: #f2f2f2;
}
        /* ESTILOS EXCLUSIVOS PARA ESTA PÁGINA */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .content-wrapper {
            max-width: 90%;
            margin: 0 auto;
        }
        
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 10px;
            }
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #3498db;
            position: relative;
            text-align: center;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: #3498db;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eef2f7;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #3498db;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #2c3e50;
            display: block;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .view-toggle {
            display: inline-flex;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 3px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
        }
        
        .toggle-btn {
            padding: 6px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .toggle-btn.active {
            background: #3498db;
            color: white;
            box-shadow: 0 3px 8px rgba(52, 152, 219, 0.4);
        }
        
        .toggle-btn:hover:not(.active) {
            background: #e9ecef;
            color: #495057;
        }
        
        .filters-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #eef2f7;
            max-width: 1200px;
            margin: 0 auto 25px auto;
        }
        
        @media (max-width: 768px) {
            .filters-panel {
                grid-template-columns: 1fr;
                padding: 15px;
            }
        }
        
        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-item label {
            font-size: 13px;
            color: #5a6c7d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-item select,
        .filter-item input {
            padding: 12px 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            width: 100%;
            transition: all 0.3s ease;
            color: #333;
        }
        
        .filter-item select:focus,
        .filter-item input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        @media (max-width: 768px) {
            .filter-buttons {
                flex-direction: column;
            }
            
            .btn-filter,
            .btn-clear {
                width: 100%;
            }
        }
        
        .btn-filter {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-filter:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }
        
        .btn-clear {
            padding: 12px 24px;
            background: #95a5a6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-clear:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 140, 141, 0.3);
        }
        
        /* ESTILOS PARA TABLAS RESPONSIVAS */
        .data-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 1px solid #eef2f7;
            margin-bottom: 30px;
            max-width: 1300px;
            margin: 0 auto 30px auto;
        }
        
        .responsive-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 auto;
        }
        
        @media (max-width: 1200px) {
            .data-table-container {
                overflow-x: auto;
            }
            .responsive-table {
                min-width: 1000px;
            }
        }
        
        .responsive-table th {
            background: #2c3e50;
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .responsive-table th:last-child {
            border-right: none;
        }
        
        .responsive-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 14px;
            transition: background-color 0.2s ease;
            color: #4a5568;
        }
        
        .responsive-table tr:hover td {
            background: #f8fafc;
        }
        
        .responsive-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Clases para columnas específicas */
        .col-id { 
            width: 70px; 
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
        }
        
        .col-estudiante { 
            min-width: 200px; 
            max-width: 250px;
        }
        
        .col-email { 
            min-width: 220px; 
            max-width: 280px;
        }
        
        .col-kit { 
            min-width: 140px; 
            max-width: 180px;
        }
        
        .col-fecha { 
            width: 140px; 
            font-family: 'Courier New', monospace;
        }
        
        .col-materiales { 
            min-width: 280px; 
            max-width: 350px;
            word-wrap: break-word;
        }
        
        .col-periodo { 
            width: 160px; 
        }
        
        .col-estado { 
            width: 120px; 
            text-align: center;
        }
        
        /* Para la vista de kits */
        .kit-table .col-estadisticas {
            min-width: 220px;
            max-width: 280px;
        }
        
        .kit-table .col-materiales {
            min-width: 300px;
            max-width: 400px;
        }
        
        /* ESTILOS PARA ESTADOS */
        .status-tag {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
            min-width: 90px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid;
            transition: all 0.3s ease;
        }
        
        /* Colores para estados */
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        
        .status-canceled {
            background: #e2e3e5;
            color: #383d41;
            border-color: #d6d8db;
        }
        
        /* NUEVOS COLORES PARA ESTADOS EN ESPAÑOL */
        .status-enviada {
            background: #e6f7ff;
            color: #1890ff;
            border-color: #91d5ff;
        }
        
        .status-en-revision {
            background: #fffbe6;
            color: #faad14;
            border-color: #ffe58f;
        }
        
        .status-aprobada {
            background: #f6ffed;
            color: #52c41a;
            border-color: #b7eb8f;
        }
        
        .status-rechazada {
            background: #fff2f0;
            color: #ff4d4f;
            border-color: #ffccc7;
        }
        
        .status-entrega {
            background: #e8f9f7;
            color: #009688;
            border-color: #80cbc4;
        }
        
        .status-cancelada {
            background: #f5f5f5;
            color: #8c8c8c;
            border-color: #d9d9d9;
        }
        
        /* ESTILOS PARA ESTADÍSTICAS DE KITS */
        .stats-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: center;
        }
        
        .stat-tag {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tag-total { 
            background: #2c3e50; 
            color: white; 
        }
        
        .tag-pending { 
            background: #ffc107; 
            color: #212529; 
        }
        
        .tag-approved { 
            background: #28a745; 
            color: white; 
        }
        
        .tag-rejected { 
            background: #dc3545; 
            color: white; 
        }
        
        .tag-delivered { 
            background: #17a2b8; 
            color: white; 
        }
        
        .tag-empty { 
            background: #e9ecef; 
            color: #6c757d; 
            border: 1px solid #dee2e6; 
        }
        
        /* MENSAJES */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 2px dashed #e2e8f0;
            max-width: 800px;
            margin: 0 auto 30px auto;
        }
        
        .no-data h3 {
            font-size: 20px;
            color: #4a5568;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .no-data p {
            font-size: 15px;
            max-width: 500px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        /* ENLACES */
        .mini-view {
            display: inline-block;
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 5px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .mini-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
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
            backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-cancel.show {
            opacity: 1;
        }
        
        .modal-box {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal-cancel.show .modal-box {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #95a5a6;
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: #f8f9fa;
            color: #e74c3c;
        }
        
        .modal-body {
            margin-bottom: 25px;
        }
        
        .modal-message {
            font-size: 15px;
            color: #5a6c7d;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .form-field {
            margin-bottom: 20px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #4a5568;
            font-weight: 600;
        }
        
        .form-field textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            transition: all 0.3s ease;
            color: #333;
        }
        
        .form-field textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        @media (max-width: 480px) {
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-btn {
                width: 100%;
            }
        }
        
        .modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-modal-cancel {
            background: #95a5a6;
            color: white;
        }
        
        .btn-modal-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 140, 141, 0.3);
        }
        
        .btn-modal-confirm {
            background: #e74c3c;
            color: white;
        }
        
        .btn-modal-confirm:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        /* SCROLLBAR PERSONALIZADA */
        .data-table-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .data-table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        .data-table-container::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 4px;
        }
        
        .data-table-container::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }
        
        /* ANIMACIONES */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-card, .filters-panel, .data-table-container {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* MENSAJE DE ÉXITO/ERROR */
        .alert-message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.5s ease-out;
            border: 2px solid transparent;
            max-width: 1200px;
            margin: 0 auto 25px auto;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        /* LOADER PARA TABLAS */
        .table-loader {
            text-align: center;
            padding: 40px;
            color: #718096;
            font-size: 15px;
        }
        
        .table-loader::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-left: 10px;
            border: 3px solid #e0e6ed;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* NUEVO: ESTILO PARA CONTADORES ACTUALIZADOS */
        .stat-number.updated {
            animation: pulse 1s ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* NUEVO: AUTO-REFRESH INDICATOR */
        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ====== AGREGADO: ESTILOS PARA PAGINACIÓN ====== */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #eef2f7;
            max-width: 1300px;
            margin: 20px auto 30px auto;
        }
        
        .pagination-info {
            margin: 0 20px;
            font-size: 14px;
            color: #5a6c7d;
            font-weight: 500;
        }
        
        .pagination-info strong {
            color: #2c3e50;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 10px;
        }
        
        .pagination-btn {
            padding: 10px 18px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 120px;
            justify-content: center;
        }
        
        .pagination-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }
        
        .pagination-btn-prev {
            background: #34495e;
        }
        
        .pagination-btn-prev:hover:not(:disabled) {
            background: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                gap: 15px;
            }
            
            .pagination-buttons {
                width: 100%;
            }
            
            .pagination-btn {
                flex: 1;
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
            <div class="alert-message <?php echo $_SESSION['tipo_mensaje'] == 'success' ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas <?php echo $_SESSION['tipo_mensaje'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($_SESSION['mensaje']); ?>
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']); ?>
        <?php endif; ?>
        
        <?php if ($vista == 'solicitudes'): ?>
            <!-- VISTA DE SOLICITUDES ACTIVAS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Solicitudes Activas</div>
                    <div class="stat-number" id="stat-total"><?php echo $total; ?></div>
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
                    <div class="stat-number" id="stat-pendientes"><?php echo count($pendientes); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Aprobadas</div>
                    <div class="stat-number" id="stat-aprobadas"><?php echo count($aprobadas); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Rechazadas</div>
                    <div class="stat-number" id="stat-rechazadas"><?php echo count($rechazadas); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Entregadas</div>
                    <div class="stat-number" id="stat-entregadas"><?php echo count($entregadas); ?></div>
                </div>
            </div>
            
            <form method="GET" action="" class="filters-panel">
                <input type="hidden" name="vista" value="solicitudes">
                <!-- ====== AGREGADO: Input para página ====== -->
                <input type="hidden" name="pagina" value="1">
                
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
                        <option value="Enviada" <?php echo ($filtro_estado == 'Enviada') ? 'selected' : ''; ?>>Enviada</option>
                        <option value="En revisión" <?php echo ($filtro_estado == 'En revisión') ? 'selected' : ''; ?>>En revisión</option>
                        <option value="Aprobada" <?php echo ($filtro_estado == 'Aprobada') ? 'selected' : ''; ?>>Aprobada</option>
                        <option value="Rechazada" <?php echo ($filtro_estado == 'Rechazada') ? 'selected' : ''; ?>>Rechazada</option>
                        <option value="Entrega" <?php echo ($filtro_estado == 'Entrega') ? 'selected' : ''; ?>>Entrega</option>
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
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                    
                    <button type="button" class="btn-clear" onclick="window.location.href='?vista=solicitudes'">
                        <i class="fas fa-times"></i> Limpiar Filtros
                    </button>
                </div>
            </form>
            
            <?php if (empty($solicitudes)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px;"></i>
                    <h3>No hay solicitudes activas</h3>
                    <p>Las solicitudes canceladas no se muestran en esta lista.</p>
                </div>
            <?php else: ?>
                <div class="data-table-container">
                    <div style="overflow-x: auto;">
                        <table class="responsive-table">
                            <thead>
                                <tr>
                                    <th class="col-id">ID</th>
                                    <th class="col-estudiante">Estudiante</th>
                                    <th class="col-email">Email</th>
                                    <th class="col-kit">Kit</th>
                                    <th class="col-fecha">Fecha</th>
                                    <th class="col-materiales">Materiales</th>
                                    <th class="col-periodo">Período</th>
                                    <th class="col-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($solicitudes as $solicitud): ?>
                                    <?php 
                                    // Determinar texto y clase según estado en español
                                    $texto_estado = $solicitud['status'];
                                    $clase_estado = '';
                                    $estado = trim($solicitud['status']);
                                    
                                    switch($estado) {
                                        case 'Enviada': 
                                            $clase_estado = 'status-enviada';
                                            break;
                                        case 'En revisión': 
                                            $clase_estado = 'status-en-revision';
                                            break;
                                        case 'Aprobada': 
                                            $clase_estado = 'status-aprobada';
                                            break;
                                        case 'Rechazada': 
                                            $clase_estado = 'status-rechazada';
                                            break;
                                        case 'Entrega': 
                                            $clase_estado = 'status-entrega';
                                            break;
                                        case 'Cancelada': 
                                            $clase_estado = 'status-cancelada';
                                            break;
                                        default: 
                                            $clase_estado = 'status-enviada';
                                            break;
                                    }
                                    ?>
                                    <tr>
                                        <td class="col-id">
                                            <strong>#<?php echo $solicitud['ID_status']; ?></strong>
                                        </td>
                                        <td class="col-estudiante">
                                            <strong><?php echo htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['estudiante_apellido']); ?></strong>
                                            <div style="font-size: 12px; color: #718096; margin-top: 3px;">
                                                ID: <?php echo $solicitud['ID_Student']; ?>
                                            </div>
                                        </td>
                                        <td class="col-email">
                                            <a href="mailto:<?php echo htmlspecialchars($solicitud['estudiante_email']); ?>" 
                                               style="color: #3498db; text-decoration: none;">
                                                <?php echo htmlspecialchars($solicitud['estudiante_email']); ?>
                                            </a>
                                        </td>
                                        <td class="col-kit">
                                            <?php echo htmlspecialchars($solicitud['Nombre_Kit']); ?>
                                        </td>
                                        <td class="col-fecha">
                                            <?php echo htmlspecialchars($solicitud['fecha_solicitud'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="col-materiales" title="<?php echo htmlspecialchars($solicitud['materiales']); ?>">
                                            <?php 
                                            $materiales = $solicitud['materiales'];
                                            echo htmlspecialchars(strlen($materiales) > 50 ? substr($materiales, 0, 50) . '...' : $materiales);
                                            ?>
                                        </td>
                                        <td class="col-periodo">
                                            <?php if ($solicitud['Start_date'] != 'No disponible'): ?>
                                                <div style="font-size: 12px; color: #718096;">
                                                    <?php echo htmlspecialchars($solicitud['Period'] . ' ' . $solicitud['Year']); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #a0aec0;">
                                                    <?php echo date('d/m/Y', strtotime($solicitud['Start_date'])) . ' - ' . 
                                                           date('d/m/Y', strtotime($solicitud['End_date'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #a0aec0;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-estado">
                                            <span class="status-tag <?php echo $clase_estado; ?>">
                                                <?php echo $texto_estado; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ====== AGREGADO: PAGINACIÓN PARA SOLICITUDES ====== -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-buttons">
                        <button class="pagination-btn pagination-btn-prev" 
                                onclick="navegarPagina(<?php echo $pagina_actual - 1; ?>)" 
                                <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                    </div>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?php echo min($registros_por_pagina, count($solicitudes)); ?></strong> de 
                        <strong><?php echo $total_registros; ?></strong> registros
                        - Página <strong><?php echo $pagina_actual; ?></strong> de <strong><?php echo $total_paginas; ?></strong>
                    </div>
                    
                    <div class="pagination-buttons">
                        <button class="pagination-btn" 
                                onclick="navegarPagina(<?php echo $pagina_actual + 1; ?>)" 
                                <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>>
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- VISTA DE KITS DISPONIBLES -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Kits</div>
                    <div class="stat-number" id="stat-total-kits"><?php echo $total_kits; ?></div>
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
                    <div class="stat-number" id="stat-con-solicitudes"><?php echo $kits_con_solicitudes; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sin Solicitudes</div>
                    <div class="stat-number" id="stat-sin-solicitudes"><?php echo $kits_sin_solicitudes; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Solicitudes Activas</div>
                    <div class="stat-number" id="stat-total-solicitudes"><?php echo $total_solicitudes_todas; ?></div>
                </div>
            </div>
            
            <?php if (empty($kits_completos)): ?>
                <div class="no-data">
                    <i class="fas fa-box-open" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px;"></i>
                    <h3>No hay kits registrados</h3>
                    <p>No se encontraron kits en el sistema.</p>
                </div>
            <?php else: ?>
                <div class="data-table-container">
                    <div style="overflow-x: auto;">
                        <table class="responsive-table kit-table">
                            <thead>
                                <tr>
                                    <th class="col-id">ID</th>
                                    <th class="col-kit">Nombre del Kit</th>
                                    <th class="col-periodo">Período</th>
                                    <th style="width: 180px;">Vigencia</th>
                                    <th class="col-materiales">Materiales</th>
                                    <th class="col-estadisticas">Estadísticas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kits_completos as $kit): ?>
                                    <tr>
                                        <td class="col-id">
                                            <strong>#<?php echo $kit['ID_Kit']; ?></strong>
                                        </td>
                                        <td class="col-kit">
                                            <strong><?php echo htmlspecialchars($kit['nombre_kit']); ?></strong>
                                            <div>
                                                <a href="?vista=solicitudes&kit=<?php echo $kit['ID_Kit']; ?>" class="mini-view">
                                                    <i class="fas fa-eye"></i> Ver Solicitudes
                                                </a>
                                            </div>
                                        </td>
                                        <td class="col-periodo">
                                            <?php echo htmlspecialchars($kit['Period'] . ' ' . $kit['Year']); ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 13px; color: #4a5568;">
                                                <?php echo date('d/m/Y', strtotime($kit['Start_date'])); ?>
                                            </div>
                                            <div style="font-size: 12px; color: #718096;">
                                                hasta <?php echo date('d/m/Y', strtotime($kit['End_date'])); ?>
                                            </div>
                                        </td>
                                        <td class="col-materiales" title="<?php echo htmlspecialchars($kit['materiales']); ?>">
                                            <?php 
                                            $materiales = $kit['materiales'];
                                            echo htmlspecialchars(strlen($materiales) > 60 ? substr($materiales, 0, 60) . '...' : $materiales);
                                            ?>
                                        </td>
                                        <td class="col-estadisticas">
                                            <div class="stats-tags">
                                                <?php if ($kit['total_solicitudes'] > 0): ?>
                                                    <span class="stat-tag tag-total" title="Total solicitudes activas">
                                                        <i class="fas fa-chart-bar"></i> <?php echo $kit['total_solicitudes']; ?>
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
                                                        <i class="fas fa-ban"></i> Sin solicitudes
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- ====== AGREGADO: PAGINACIÓN PARA KITS ====== -->
                <?php if ($total_paginas_kits > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-buttons">
                        <button class="pagination-btn pagination-btn-prev" 
                                onclick="navegarPaginaKits(<?php echo $pagina_actual_kits - 1; ?>)" 
                                <?php echo ($pagina_actual_kits <= 1) ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Anterior
                        </button>
                    </div>
                    
                    <div class="pagination-info">
                        Mostrando <strong><?php echo min($kits_por_pagina, count($kits_completos)); ?></strong> de 
                        <strong><?php echo $total_kits_registros; ?></strong> kits
                        - Página <strong><?php echo $pagina_actual_kits; ?></strong> de <strong><?php echo $total_paginas_kits; ?></strong>
                    </div>
                    
                    <div class="pagination-buttons">
                        <button class="pagination-btn" 
                                onclick="navegarPaginaKits(<?php echo $pagina_actual_kits + 1; ?>)" 
                                <?php echo ($pagina_actual_kits >= $total_paginas_kits) ? 'disabled' : ''; ?>>
                            Siguiente <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- MODAL PARA CANCELAR CON MENSAJE (TODO IGUAL) -->
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
                            <i class="fas fa-times"></i> No Cancelar
                        </button>
                        <button type="submit" class="modal-btn btn-modal-confirm">
                            <i class="fas fa-check"></i> Confirmar Cancelación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // ====== AGREGADO: FUNCIONES DE PAGINACIÓN ======
        function navegarPagina(pagina) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('pagina', pagina);
            window.location.href = '?' + urlParams.toString();
        }
        
        function navegarPaginaKits(pagina) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('pagina_kits', pagina);
            window.location.href = '?' + urlParams.toString();
        }
        
        // TODO TU JAVASCRIPT ORIGINAL SE CONSERVA INTACTO
        let lastUpdateTime = Date.now();
        let refreshIndicator = null;
        let refreshTimeout = null;
        
        function abrirModalCancelar(idSolicitud) {
            const modal = document.getElementById('modalCancelar');
            document.getElementById('modalIdSolicitud').value = idSolicitud;
            document.getElementById('modalTexto').textContent = 
                `¿Cancelar la solicitud #${idSolicitud}? Ingresa un motivo (opcional):`;
            
            // Mostrar con animación
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
            
            // Enfocar textarea
            setTimeout(() => {
                document.getElementById('mensajeCancelacion').focus();
            }, 300);
        }
        
        function cerrarModal() {
            const modal = document.getElementById('modalCancelar');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.style.display = 'none';
                document.getElementById('mensajeCancelacion').value = '';
            }, 300);
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
            const mensaje = document.getElementById('mensajeCancelacion').value.trim();
            
            if (confirm(`¿Estás seguro de cancelar la solicitud #${idSolicitud}?`)) {
                this.submit();
            }
        });
        
        // Prevenir reenvío de formulario
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // AUTO-REFRESH PARA ACTUALIZAR CONTADORES
        function setupAutoRefresh() {
            // Verificar si hay filtros activos
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.has('kit') || urlParams.has('estado') || 
                              urlParams.has('nombre') || urlParams.has('email');
            
            // Solo auto-refresh si no hay filtros aplicados
            if (!hasFilters) {
                // Configurar refresh cada 30 segundos
                refreshTimeout = setTimeout(() => {
                    checkForUpdates();
                }, 30000); // 30 segundos
            }
        }
        
        // Verificar actualizaciones
        async function checkForUpdates() {
            try {
                // Hacer una petición ligera para verificar cambios
                const response = await fetch(window.location.href, {
                    method: 'HEAD',
                    cache: 'no-cache'
                });
                
                const currentTime = Date.now();
                const serverTime = new Date(response.headers.get('Last-Modified')).getTime() || currentTime;
                
                // Si han pasado más de 10 segundos desde la última actualización del servidor
                if (serverTime > lastUpdateTime + 10000) {
                    showRefreshIndicator();
                } else {
                    // Programar próxima verificación
                    refreshTimeout = setTimeout(checkForUpdates, 30000);
                }
                
            } catch (error) {
                console.log('Error verificando actualizaciones:', error);
                // Reintentar en 30 segundos
                refreshTimeout = setTimeout(checkForUpdates, 30000);
            }
        }
        
        // Mostrar indicador de actualización disponible
        function showRefreshIndicator() {
            // Crear indicador si no existe
            if (!refreshIndicator) {
                refreshIndicator = document.createElement('div');
                refreshIndicator.className = 'refresh-indicator';
                refreshIndicator.innerHTML = `
                    <i class="fas fa-sync-alt fa-spin"></i>
                    <span>Datos actualizados disponibles</span>
                    <button onclick="refreshPage()" style="background: white; color: #3498db; border: none; 
                            padding: 4px 10px; border-radius: 10px; font-size: 11px; cursor: pointer; margin-left: 8px;">
                        Actualizar
                    </button>
                `;
                document.body.appendChild(refreshIndicator);
                
                // Auto-ocultar después de 10 segundos
                setTimeout(() => {
                    if (refreshIndicator && refreshIndicator.parentNode) {
                        refreshIndicator.style.opacity = '0';
                        setTimeout(() => {
                            if (refreshIndicator && refreshIndicator.parentNode) {
                                refreshIndicator.parentNode.removeChild(refreshIndicator);
                                refreshIndicator = null;
                            }
                        }, 300);
                    }
                }, 10000);
            }
        }
        
        // Refrescar la página
        function refreshPage() {
            // Cancelar timeout pendiente
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }
            
            // Remover indicador
            if (refreshIndicator && refreshIndicator.parentNode) {
                refreshIndicator.parentNode.removeChild(refreshIndicator);
                refreshIndicator = null;
            }
            
            // Actualizar tiempo de última actualización
            lastUpdateTime = Date.now();
            
            // Recargar la página
            window.location.reload();
        }
        
        // Efecto visual cuando los contadores cambian
        function highlightUpdatedCounters() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(number => {
                number.classList.add('updated');
                setTimeout(() => {
                    number.classList.remove('updated');
                }, 1000);
            });
        }
        
        // Mejorar la experiencia de usuario en filtros
        document.addEventListener('DOMContentLoaded', function() {
            // Iniciar auto-refresh
            setupAutoRefresh();
            
            // Guardar estado actual de los contadores
            const initialCounters = {};
            document.querySelectorAll('.stat-number').forEach((counter, index) => {
                initialCounters[index] = counter.textContent;
            });
            
            // Detectar cambios en los contadores (cuando regresas de otra página)
            setTimeout(() => {
                document.querySelectorAll('.stat-number').forEach((counter, index) => {
                    if (initialCounters[index] !== counter.textContent) {
                        highlightUpdatedCounters();
                    }
                });
            }, 500);
            
            // Auto-enfocar en el primer campo de búsqueda si hay filtros aplicados
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('nombre') || urlParams.has('email') || urlParams.has('kit') || urlParams.has('estado')) {
                setTimeout(() => {
                    document.querySelector('input[name="nombre"], input[name="email"]').focus();
                }, 500);
            }
        });
        
        // Actualizar lastUpdateTime cuando se interactúa con la página
        document.addEventListener('click', () => {
            lastUpdateTime = Date.now();
        });
        
        document.addEventListener('keypress', () => {
            lastUpdateTime = Date.now();
        });
        
        // Limpiar timeout al salir de la página
        window.addEventListener('beforeunload', () => {
            if (refreshTimeout) {
                clearTimeout(refreshTimeout);
            }
        });
    </script>
</body>
</html>