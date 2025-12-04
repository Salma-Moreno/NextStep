<?php
session_start();
// Guardián
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

include '../Conexiones/db.php';
$conexion = $conn;

// Obtener todas las solicitudes de estudiantes
$sql = "SELECT 
        a.ID_status,
        s.Name AS estudiante_nombre,
        s.Last_Name AS estudiante_apellido,
        s.Email_Address AS estudiante_email,
        a.status,
        a.FK_ID_Kit
        FROM aplication a
        JOIN student s ON a.FK_ID_Student = s.ID_Student
        ORDER BY a.ID_status DESC";

$result = $conexion->query($sql);

// Verificar si hay error
if (!$result) {
    die("Error en la consulta: " . $conexion->error);
}

$solicitudes = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Agregar datos adicionales para cada kit
        $kit_id = $row['FK_ID_Kit'];
        
        // Obtener información del kit si existe
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
            } else {
                $row['Start_date'] = 'No disponible';
                $row['End_date'] = 'No disponible';
                $row['Period'] = 'N/A';
                $row['Year'] = 'N/A';
            }
            $stmt_kit->close();
            
            // Obtener materiales del kit
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
            $row['materiales'] = 'Sin kit asignado';
        }
        
        $solicitudes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Solicitudes - Staff</title>
    <style>
       
        
        /* Contenedor principal */
        .historial-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            width: 100%;
        }
        
        /* Header específico de esta página */
        .historial-header {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .historial-header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        /* Filtros */
        .filtros-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .filtros-container select, 
        .filtros-container input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 180px;
            background: white;
        }
        
        .filtros-container button {
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            font-family: inherit;
        }
        
        .filtros-container button:hover {
            background: #0b7dda;
        }
        
        /* Tabla */
        .tabla-solicitudes {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .tabla-solicitudes th {
            background: linear-gradient(135deg, #2196F3, #0D47A1);
            color: white;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .tabla-solicitudes td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .tabla-solicitudes tr:hover {
            background: #f9f9f9;
        }
        
        /* Estados */
        .estado-solicitud {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .estado-aprobado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .estado-rechazado {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .estado-entregado {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        
        /* Acciones */
        .acciones-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-accion-historial {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn-ver-historial {
            background: #2196F3;
            color: white;
        }
        
        .btn-ver-historial:hover {
            background: #0b7dda;
        }
        
        .btn-aprobar-historial {
            background: #4CAF50;
            color: white;
        }
        
        .btn-aprobar-historial:hover {
            background: #3d8b40;
        }
        
        .btn-rechazar-historial {
            background: #f44336;
            color: white;
        }
        
        .btn-rechazar-historial:hover {
            background: #d32f2f;
        }
        
        .btn-entregar-historial {
            background: #FF9800;
            color: white;
        }
        
        .btn-entregar-historial:hover {
            background: #F57C00;
        }
        
        /* Estadísticas */
        .stats-bar-historial {
            display: flex;
            justify-content: space-between;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .stat-item-historial {
            text-align: center;
            flex: 1;
        }
        
        .stat-number-historial {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            display: block;
        }
        
        .stat-label-historial {
            font-size: 14px;
            color: #666;
        }
        
        /* Sin solicitudes */
        .sin-solicitudes-container {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            font-style: italic;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        /* Paginación */
        .paginacion-container {
            display: flex;
            justify-content: center;
            margin-top: 25px;
            gap: 8px;
        }
        
        .pagina-historial {
            padding: 10px 16px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .pagina-historial:hover {
            background: #f0f0f0;
        }
        
        .pagina-historial.activa {
            background: #2196F3;
            color: white;
            border-color: #2196F3;
        }
        
        /* Modal */
        .modal-historial-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-historial-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalAppear 0.3s ease;
        }
        
        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
       .modal-historial-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-historial-header h2 {
            color: #0D47A1;
            font-size: 24px;
            margin: 0;
        }
        
        .close-modal-historial {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-family: inherit;
        }
        
        .close-modal-historial:hover {
            background: #f0f0f0;
        }
        
        .detalle-item-historial {
            margin-bottom: 18px;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
        }
        
        .detalle-item-historial strong {
            color: #0D47A1;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-estudiante-historial {
            background: #E3F2FD;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .materiales-lista-historial {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .materiales-lista-historial li {
            padding: 8px 12px;
            background: white;
            margin-bottom: 8px;
            border-radius: 6px;
            border: 1px solid #eee;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .historial-container {
                padding: 0 15px;
            }
            
            .filtros-container {
                flex-direction: column;
            }
            
            .filtros-container select, 
            .filtros-container input {
                width: 100%;
            }
            
            .tabla-solicitudes {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 768px) {
            .historial-header {
                padding: 20px;
            }
            
            .historial-header h1 {
                font-size: 24px;
            }
            
            .acciones-container {
                flex-direction: column;
            }
            
            .btn-accion-historial {
                width: 100%;
                justify-content: center;
            }
            
            .stats-bar-historial {
                flex-wrap: wrap;
                gap: 15px;
            }
            
            .stat-item-historial {
                flex: 0 0 calc(50% - 15px);
            }
        }
        
        
        
       
    </style>
</head>
<body>
    <?php include '../Includes/HeaderMenuStaff.php'; ?>
    
    <!-- Contenedor específico para esta página -->
    <div id="historial-page">
        <div class="historial-main-content">
            <div class="historial-container">
                <div class="historial-header">
                    <h1>📋 Historial de Solicitudes de Kits</h1>
                </div>
                
                <?php 
                // Calcular estadísticas
                $total = count($solicitudes);
                $pendientes = array_filter($solicitudes, function($s) { return $s['status'] == 'Pending'; });
                $aprobadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Approved'; });
                $rechazadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Rejected'; });
                $entregadas = array_filter($solicitudes, function($s) { return $s['status'] == 'Delivered'; });
                ?>
                
                <div class="stats-bar-historial">
                    <div class="stat-item-historial">
                        <span class="stat-number-historial"><?php echo $total; ?></span>
                        <span class="stat-label-historial">Total</span>
                    </div>
                    <div class="stat-item-historial">
                        <span class="stat-number-historial"><?php echo count($pendientes); ?></span>
                        <span class="stat-label-historial">Pendientes</span>
                    </div>
                    <div class="stat-item-historial">
                        <span class="stat-number-historial"><?php echo count($aprobadas); ?></span>
                        <span class="stat-label-historial">Aprobadas</span>
                    </div>
                    <div class="stat-item-historial">
                        <span class="stat-number-historial"><?php echo count($rechazadas); ?></span>
                        <span class="stat-label-historial">Rechazadas</span>
                    </div>
                    <div class="stat-item-historial">
                        <span class="stat-number-historial"><?php echo count($entregadas); ?></span>
                        <span class="stat-label-historial">Entregadas</span>
                    </div>
                </div>
                
                <div class="filtros-container">
                    <select id="filtro-estado">
                        <option value="">Todos los estados</option>
                        <option value="Pending">Pendiente</option>
                        <option value="Approved">Aprobado</option>
                        <option value="Rejected">Rechazado</option>
                        <option value="Delivered">Entregado</option>
                    </select>
                    
                    <input type="text" id="filtro-nombre" placeholder="🔍 Buscar por nombre..." onkeyup="aplicarFiltros()">
                    <input type="text" id="filtro-email" placeholder="✉️ Buscar por email..." onkeyup="aplicarFiltros()">
                    <select id="filtro-semestre">
                        <option value="">Todos los semestres</option>
                        <?php
                        // Obtener semestres únicos
                        $semestres = [];
                        foreach ($solicitudes as $solicitud) {
                            $semestre = $solicitud['Period'] . ' ' . $solicitud['Year'];
                            if (!in_array($semestre, $semestres) && $semestre != 'N/A N/A') {
                                $semestres[] = $semestre;
                            }
                        }
                        foreach ($semestres as $semestre) {
                            echo "<option value=\"$semestre\">$semestre</option>";
                        }
                        ?>
                    </select>
                    
                    <button onclick="aplicarFiltros()">
                        🔍 Aplicar Filtros
                    </button>
                    <button onclick="limpiarFiltros()" style="background: #6c757d;">
                        🗑️ Limpiar Filtros
                    </button>
                </div>
                
                <?php if (empty($solicitudes)): ?>
                    <div class="sin-solicitudes-container">
                        <h3>📭 No hay solicitudes registradas</h3>
                        <p>Los estudiantes aún no han aplicado a kits de útiles escolares.</p>
                    </div>
                <?php else: ?>
                    <table class="tabla-solicitudes">
                        <thead>
                            <tr>
                                <th width="80">ID</th>
                                <th>Estudiante</th>
                                <th>Email</th>
                                <th>Semestre</th>
                                <th>Materiales</th>
                                <th>Período</th>
                                <th>Estado</th>
                                <th width="280">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-body">
                            <?php foreach ($solicitudes as $solicitud): ?>
                                <?php 
                                $clase_estado = '';
                                switch($solicitud['status']) {
                                    case 'Pending': $clase_estado = 'estado-pendiente'; break;
                                    case 'Approved': $clase_estado = 'estado-aprobado'; break;
                                    case 'Rejected': $clase_estado = 'estado-rechazado'; break;
                                    case 'Delivered': $clase_estado = 'estado-entregado'; break;
                                }
                                
                                $estados_texto = [
                                    'Pending' => 'Pendiente',
                                    'Approved' => 'Aprobado',
                                    'Rejected' => 'Rechazado',
                                    'Delivered' => 'Entregado'
                                ];
                                ?>
                                <tr>
                                    <td><strong>#<?php echo $solicitud['ID_status']; ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($solicitud['estudiante_nombre'] . ' ' . $solicitud['estudiante_apellido']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($solicitud['estudiante_email']); ?></td>
                                    <td><?php echo htmlspecialchars($solicitud['Period'] . ' ' . $solicitud['Year']); ?></td>
                                    <td title="<?php echo htmlspecialchars($solicitud['materiales']); ?>">
                                        <?php 
                                        $materiales = $solicitud['materiales'];
                                        echo strlen($materiales) > 50 ? substr($materiales, 0, 50) . '...' : $materiales;
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($solicitud['Start_date'] != 'No disponible') {
                                            echo date('d/m/Y', strtotime($solicitud['Start_date'])) . ' a ' . 
                                                 date('d/m/Y', strtotime($solicitud['End_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="estado-solicitud <?php echo $clase_estado; ?>">
                                            <?php echo $estados_texto[$solicitud['status']] ?? $solicitud['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="acciones-container">
                                            <button class="btn-accion-historial btn-ver-historial" 
                                                    onclick="verDetalle(<?php echo $solicitud['ID_status']; ?>)">
                                                 Ver Detalles
                                            </button>
                                            
                                            <?php if ($solicitud['status'] == 'Pending'): ?>
                                                <button class="btn-accion-historial btn-aprobar-historial" 
                                                        onclick="cambiarEstado(<?php echo $solicitud['ID_status']; ?>, 'Approved')">
                                                    ✓ Aprobar
                                                </button>
                                                <button class="btn-accion-historial btn-rechazar-historial" 
                                                        onclick="cambiarEstado(<?php echo $solicitud['ID_status']; ?>, 'Rejected')">
                                                    ✗ Rechazar
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($solicitud['status'] == 'Approved'): ?>
                                                <button class="btn-accion-historial btn-entregar-historial" 
                                                        onclick="marcarEntregado(<?php echo $solicitud['ID_status']; ?>)">
                                                    Marcar Entregado
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="paginacion-container" id="paginacion">
                        <!-- La paginación se genera con JavaScript -->
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal -->
    <div id="modalDetalle" class="modal-historial-overlay">
        <div class="modal-historial-content">
            <div class="modal-historial-header">
                <h2 id="modalTitulo">Detalles de la Solicitud</h2>
                <button class="close-modal-historial" onclick="cerrarModal()">×</button>
            </div>
            <div id="modalContenido">
                <!-- Contenido dinámico -->
            </div>
            <div style="margin-top: 25px; text-align: center;">
                <button onclick="cerrarModal()" style="padding: 12px 25px; background: #6c757d; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-family: inherit;">
                    Cerrar Vista
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript aquí (igual que antes, pero con los nombres de clases actualizados)
        // Variables para paginación
        let solicitudes = <?php echo json_encode($solicitudes); ?>;
        const itemsPorPagina = 10;
        let paginaActual = 1;
        let solicitudesFiltradas = [...solicitudes];
        
        // Inicializar paginación
        document.addEventListener('DOMContentLoaded', function() {
            configurarPaginacion();
        });
        
        function aplicarFiltros() {
            const estado = document.getElementById('filtro-estado').value;
            const nombre = document.getElementById('filtro-nombre').value.toLowerCase();
            const email = document.getElementById('filtro-email').value.toLowerCase();
            const semestre = document.getElementById('filtro-semestre').value;
            
            solicitudesFiltradas = solicitudes.filter(solicitud => {
                let pasaFiltro = true;
                
                // Filtrar por estado
                if (estado && solicitud.status !== estado) {
                    pasaFiltro = false;
                }
                
                // Filtrar por nombre
                if (nombre) {
                    const nombreCompleto = (solicitud.estudiante_nombre + ' ' + solicitud.estudiante_apellido).toLowerCase();
                    if (!nombreCompleto.includes(nombre)) {
                        pasaFiltro = false;
                    }
                }
                
                // Filtrar por email
                if (email && !solicitud.estudiante_email.toLowerCase().includes(email)) {
                    pasaFiltro = false;
                }
                
                // Filtrar por semestre
                if (semestre) {
                    const semestreActual = solicitud.Period + ' ' + solicitud.Year;
                    if (semestreActual !== semestre) {
                        pasaFiltro = false;
                    }
                }
                
                return pasaFiltro;
            });
            
            // Actualizar estadísticas visibles
            actualizarEstadisticasFiltradas();
            
            // Actualizar tabla
            paginaActual = 1;
            configurarPaginacion();
        }
        
        function actualizarEstadisticasFiltradas() {
            const total = solicitudesFiltradas.length;
            const pendientes = solicitudesFiltradas.filter(s => s.status == 'Pending').length;
            const aprobadas = solicitudesFiltradas.filter(s => s.status == 'Approved').length;
            const rechazadas = solicitudesFiltradas.filter(s => s.status == 'Rejected').length;
            const entregadas = solicitudesFiltradas.filter(s => s.status == 'Delivered').length;
            
            const stats = document.querySelectorAll('.stat-number-historial');
            if (stats.length >= 5) {
                stats[0].textContent = total;
                stats[1].textContent = pendientes;
                stats[2].textContent = aprobadas;
                stats[3].textContent = rechazadas;
                stats[4].textContent = entregadas;
            }
        }
        
        function limpiarFiltros() {
            document.getElementById('filtro-estado').value = '';
            document.getElementById('filtro-nombre').value = '';
            document.getElementById('filtro-email').value = '';
            document.getElementById('filtro-semestre').value = '';
            
            solicitudesFiltradas = [...solicitudes];
            
            // Restaurar estadísticas originales
            const stats = document.querySelectorAll('.stat-number-historial');
            if (stats.length >= 5) {
                stats[0].textContent = <?php echo $total; ?>;
                stats[1].textContent = <?php echo count($pendientes); ?>;
                stats[2].textContent = <?php echo count($aprobadas); ?>;
                stats[3].textContent = <?php echo count($rechazadas); ?>;
                stats[4].textContent = <?php echo count($entregadas); ?>;
            }
            
            paginaActual = 1;
            configurarPaginacion();
        }
        
        function verDetalle(idSolicitud) {
            // Buscar la solicitud
            const solicitud = solicitudes.find(s => s.ID_status == idSolicitud);
            
            if (solicitud) {
                document.getElementById('modalTitulo').textContent = `Solicitud #${solicitud.ID_status}`;
                
                const estadoTexto = {
                    'Pending': 'Pendiente',
                    'Approved': 'Aprobado',
                    'Rejected': 'Rechazado',
                    'Delivered': 'Entregado'
                };
                
                const contenido = `
                    <div class="info-estudiante-historial">
                        <h3 style="margin-bottom: 10px; color: #0D47A1;">📋 Información del Estudiante</h3>
                        <div class="detalle-item-historial">
                            <strong>Nombre completo:</strong>
                            ${solicitud.estudiante_nombre} ${solicitud.estudiante_apellido}
                        </div>
                        <div class="detalle-item-historial">
                            <strong>Correo electrónico:</strong>
                            ${solicitud.estudiante_email}
                        </div>
                    </div>
                    
                    <div class="detalle-item-historial">
                        <strong>Semestre:</strong>
                        ${solicitud.Period} ${solicitud.Year}
                    </div>
                    
                    ${solicitud.Start_date != 'No disponible' ? `
                    <div class="detalle-item-historial">
                        <strong>Período del kit:</strong>
                        Del ${new Date(solicitud.Start_date).toLocaleDateString('es-MX')} al ${new Date(solicitud.End_date).toLocaleDateString('es-MX')}
                    </div>
                    ` : ''}
                    
                    <div class="detalle-item-historial">
                        <strong>Materiales solicitados:</strong>
                        <ul class="materiales-lista-historial">
                            ${solicitud.materiales.split(', ').map(mat => `<li>${mat}</li>`).join('')}
                        </ul>
                    </div>
                    
                    <div class="detalle-item-historial">
                        <strong>Estado actual:</strong>
                        <span class="estado-solicitud ${getClaseEstado(solicitud.status)}" style="margin-left: 10px;">
                            ${estadoTexto[solicitud.status]}
                        </span>
                    </div>
                    
                    <div class="detalle-item-historial">
                        <strong>ID de Solicitud:</strong>
                        #${solicitud.ID_status}
                    </div>
                    
                    ${solicitud.FK_ID_Kit ? `
                    <div class="detalle-item-historial">
                        <strong>ID del Kit:</strong>
                        #${solicitud.FK_ID_Kit}
                    </div>
                    ` : ''}
                `;
                
                document.getElementById('modalContenido').innerHTML = contenido;
                document.getElementById('modalDetalle').style.display = 'flex';
            }
        }
        
        function getClaseEstado(estado) {
            switch(estado) {
                case 'Pending': return 'estado-pendiente';
                case 'Approved': return 'estado-aprobado';
                case 'Rejected': return 'estado-rechazado';
                case 'Delivered': return 'estado-entregado';
                default: return '';
            }
        }
        
        function cerrarModal() {
            document.getElementById('modalDetalle').style.display = 'none';
        }
        
        function cambiarEstado(idSolicitud, nuevoEstado) {
            const estadoTexto = {
                'Approved': 'APROBAR',
                'Rejected': 'RECHAZAR',
                'Delivered': 'MARCAR COMO ENTREGADO'
            };
            
            if (confirm(`¿Estás seguro de ${estadoTexto[nuevoEstado]} la solicitud #${idSolicitud}?`)) {
                // Mostrar carga
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '⌛ Procesando...';
                btn.disabled = true;
                
                // Enviar solicitud AJAX
                fetch('actualizar_estado.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_solicitud=${idSolicitud}&nuevo_estado=${nuevoEstado}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data.includes('success')) {
                        alert('✅ Estado actualizado correctamente');
                        location.reload();
                    } else {
                        alert('❌ Error al actualizar el estado');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('❌ Error de conexión');
                    console.error('Error:', error);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            }
        }
        
        function marcarEntregado(idSolicitud) {
            cambiarEstado(idSolicitud, 'Delivered');
        }
        
        // Configurar paginación
        function configurarPaginacion() {
            const tbody = document.querySelector('#tabla-body');
            tbody.innerHTML = '';
            
            const totalPaginas = Math.ceil(solicitudesFiltradas.length / itemsPorPagina);
            const inicio = (paginaActual - 1) * itemsPorPagina;
            const fin = inicio + itemsPorPagina;
            const solicitudesPagina = solicitudesFiltradas.slice(inicio, fin);
            
            // Renderizar filas
            solicitudesPagina.forEach(solicitud => {
                const estadoTexto = {
                    'Pending': 'Pendiente',
                    'Approved': 'Aprobado',
                    'Rejected': 'Rechazado',
                    'Delivered': 'Entregado'
                };
                
                const clase_estado = getClaseEstado(solicitud.status);
                const periodo = solicitud.Start_date != 'No disponible' ? 
                    new Date(solicitud.Start_date).toLocaleDateString('es-MX') + ' a ' + 
                    new Date(solicitud.End_date).toLocaleDateString('es-MX') : 'N/A';
                
                const materialTexto = solicitud.materiales.length > 50 ? 
                    solicitud.materiales.substring(0, 50) + '...' : solicitud.materiales;
                
                let accionesHTML = '';
                accionesHTML += `<button class="btn-accion-historial btn-ver-historial" onclick="verDetalle(${solicitud.ID_status})">Ver Detalles</button>`;
                
                if (solicitud.status == 'Pending') {
                    accionesHTML += `<button class="btn-accion-historial btn-aprobar-historial" onclick="cambiarEstado(${solicitud.ID_status}, 'Approved')">✓ Aprobar</button>`;
                    accionesHTML += `<button class="btn-accion-historial btn-rechazar-historial" onclick="cambiarEstado(${solicitud.ID_status}, 'Rejected')">✗ Rechazar</button>`;
                }
                
                if (solicitud.status == 'Approved') {
                    accionesHTML += `<button class="btn-accion-historial btn-entregar-historial" onclick="marcarEntregado(${solicitud.ID_status})">🚚 Marcar Entregado</button>`;
                }
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>#${solicitud.ID_status}</strong></td>
                    <td><strong>${solicitud.estudiante_nombre} ${solicitud.estudiante_apellido}</strong></td>
                    <td>${solicitud.estudiante_email}</td>
                    <td>${solicitud.Period} ${solicitud.Year}</td>
                    <td title="${solicitud.materiales}">${materialTexto}</td>
                    <td>${periodo}</td>
                    <td><span class="estado-solicitud ${clase_estado}">${estadoTexto[solicitud.status]}</span></td>
                    <td><div class="acciones-container">${accionesHTML}</div></td>
                `;
                
                tbody.appendChild(tr);
            });
            
            // Actualizar paginación
            const paginacionDiv = document.getElementById('paginacion');
            paginacionDiv.innerHTML = '';
            
            if (solicitudesFiltradas.length <= itemsPorPagina) return;
            
            // Botón anterior
            if (paginaActual > 1) {
                const btnPrev = document.createElement('button');
                btnPrev.className = 'pagina-historial';
                btnPrev.innerHTML = '←';
                btnPrev.onclick = () => cambiarPagina(paginaActual - 1);
                paginacionDiv.appendChild(btnPrev);
            }
            
            // Páginas
            for (let i = 1; i <= totalPaginas; i++) {
                if (i === 1 || i === totalPaginas || (i >= paginaActual - 1 && i <= paginaActual + 1)) {
                    const btn = document.createElement('button');
                    btn.className = `pagina-historial ${i === paginaActual ? 'activa' : ''}`;
                    btn.textContent = i;
                    btn.onclick = () => cambiarPagina(i);
                    paginacionDiv.appendChild(btn);
                } else if (i === paginaActual - 2 || i === paginaActual + 2) {
                    const dots = document.createElement('span');
                    dots.className = 'pagina-historial';
                    dots.textContent = '...';
                    dots.style.cursor = 'default';
                    paginacionDiv.appendChild(dots);
                }
            }
            
            // Botón siguiente
            if (paginaActual < totalPaginas) {
                const btnNext = document.createElement('button');
                btnNext.className = 'pagina-historial';
                btnNext.innerHTML = '→';
                btnNext.onclick = () => cambiarPagina(paginaActual + 1);
                paginacionDiv.appendChild(btnNext);
            }
        }
        
        function cambiarPagina(pagina) {
            paginaActual = pagina;
            configurarPaginacion();
        }
        
        // Cerrar modal con ESC o click fuera
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });
        
        document.getElementById('modalDetalle').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });
    </script>
</body>
</html>