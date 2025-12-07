<?php
session_start();
require '../Conexiones/db.php';

// TODOS los estados posibles
$VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Rechazada', 'Entrega', 'Cancelada'];

// 1. Guardián de sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Inicializar variables de mensaje
$message_type = '';
$message_text = '';

// --- LÓGICA DE ACTUALIZACIÓN DEL ESTATUS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id_post = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $kit_id_post = filter_input(INPUT_POST, 'kit_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

    if ($student_id_post && $kit_id_post && in_array($new_status, $VALID_STATUSES)) {
        
        // Actualizar estado en la tabla aplication
        $update_query = "UPDATE aplication SET status = ? WHERE FK_ID_Student = ? AND FK_ID_Kit = ?";
        $stmt = $conn->prepare($update_query);
        
        if ($stmt) {
            $stmt->bind_param("sii", $new_status, $student_id_post, $kit_id_post);
            
            if ($stmt->execute()) {
                $message_type = 'success';
                $message_text = "Estatus actualizado a: " . htmlspecialchars($new_status);
                
                // Si fue cancelada, mostrar mensaje específico
                if ($new_status === 'Cancelada') {
                    $message_text = "Solicitud cancelada exitosamente.";
                }
            } else {
                $message_type = 'error';
                $message_text = "Error al actualizar: " . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $message_type = 'error';
        $message_text = "Datos inválidos o estatus no permitido.";
    }
    
    // REDIRECCIÓN PRG
    $redirect_url = 'view_applications.php?id=' . ($student_id_post ?: $_GET['id']);
    $redirect_url .= '&msg_type=' . $message_type . '&msg_text=' . urlencode($message_text);
    header('Location: ' . $redirect_url);
    exit;
}
// --- FIN LÓGICA DE ACTUALIZACIÓN ---

// 2. Obtener el ID del estudiante
$student_id = $_GET['id'] ?? null;
if (!$student_id || !is_numeric($student_id)) {
    die("ID de estudiante inválido.");
}

// 3. Obtener nombre del estudiante
$name_query = "SELECT Name, Last_Name FROM student WHERE ID_Student = ? LIMIT 1";
$name_stmt = $conn->prepare($name_query);
$name_stmt->bind_param("i", $student_id);
$name_stmt->execute();
$name_result = $name_stmt->get_result();
$student_name = $name_result->fetch_assoc();

if (!$student_name) {
    die("Estudiante no encontrado.");
}
$full_name = htmlspecialchars($student_name['Name'] . ' ' . $student_name['Last_Name']);

// 4. Consulta para obtener aplicaciones
$applications_query = "
    SELECT 
        k.ID_Kit,  
        k.Name AS KitName, 
        k.Description AS KitDescription,
        a.status AS ApplicationStatus,
        a.Application_date,
        k.Start_date,
        k.End_date,
        a.ID_status
    FROM aplication a
    JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
    WHERE a.FK_ID_Student = ?
    ORDER BY a.Application_date DESC, a.ID_status DESC;
";

$stmt = $conn->prepare($applications_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications_result = $stmt->get_result();
$has_applications = $applications_result->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Beca - <?= $full_name ?></title>
    <link rel="stylesheet" href="../assets/Staff/list.css"> 
    <style>
        .application-card {
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .status-enviada { background-color: #e6f7ff; border-left: 5px solid #1890ff; }
        .status-en-revisión { background-color: #fffbe6; border-left: 5px solid #faad14; }
        .status-aprobada { background-color: #f6ffed; border-left: 5px solid #52c41a; }
        .status-rechazada { background-color: #fff2f0; border-left: 5px solid #ff4d4f; }
        .status-entrega { background-color: #e8f9f7; border-left: 5px solid #009688; }
        .status-cancelada { background-color: #f5f5f5; border-left: 5px solid #8c8c8c; }
        .alert-success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
            padding: 10px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
        }
        .alert-error { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
            padding: 10px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
        }
        .btn-action { 
            padding: 8px 16px; 
            background-color: #f7b000; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 10px;
        }
        .btn-action:hover { background-color: #e09c00; }
        select {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-right: 10px;
        }
        .cancel-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 14px;
            display: none;
        }
    </style>
    <script>
      
        
        function validarFormulario(formId) {
            var form = document.getElementById(formId);
            var select = form.querySelector('select[name="new_status"]');
            
            if (select.value === 'Cancelada') {
                return confirm('¿Estás seguro de cancelar esta solicitud?\n\nLa solicitud será marcada como "Cancelada" y el estudiante será notificado.');
            } else if (select.value === 'Rechazada') {
                return confirm('¿Estás seguro de rechazar esta solicitud?\n\nLa solicitud será marcada como "Rechazada" y el estudiante será notificado.');
            } else {
                return confirm('¿Confirmar cambio de estado a: ' + select.value + '?');
            }
        }
    </script>
</head>
<body>
<?php include '../includes/HeaderMenuStaff.php'; ?> 

<div class="container">
    <?php if (isset($_GET['msg_type'])): 
        $msg_type = htmlspecialchars($_GET['msg_type']);
        $msg_text = htmlspecialchars(urldecode($_GET['msg_text']));
    ?>
        <div class="alert-<?= $msg_type ?>">
            <?= $msg_type === 'success' ? ' Éxito:' : ' Error:' ?> <?= $msg_text ?>
        </div>
    <?php endif; ?>
    
    <h2> Solicitudes de Beca de <?= $full_name ?></h2>
    <hr>

    <?php if ($has_applications): ?>
        <?php while ($app = $applications_result->fetch_assoc()): ?>
            <?php 
                $status_class = strtolower(str_replace(' ', '-', $app['ApplicationStatus']));
                $form_id = 'form_' . $app['ID_Kit'];
                $select_id = 'select_' . $app['ID_Kit'];
            ?>
            <div class="application-card status-<?= htmlspecialchars($status_class) ?>">
                <h3><?= htmlspecialchars($app['KitName']) ?></h3>
                <p><strong>ID Solicitud:</strong> #<?= $app['ID_status'] ?></p>
                <p><strong>Estatus Actual:</strong> 
                    <span style="font-weight: bold; padding: 3px 8px; border-radius: 3px; 
                        <?php 
                        switch($app['ApplicationStatus']) {
                            case 'Enviada': echo 'background-color: #e6f7ff; color: #1890ff;'; break;
                            case 'En revisión': echo 'background-color: #fffbe6; color: #faad14;'; break;
                            case 'Aprobada': echo 'background-color: #f6ffed; color: #52c41a;'; break;
                            case 'Rechazada': echo 'background-color: #fff2f0; color: #ff4d4f;'; break;
                            case 'Entrega': echo 'background-color: #e8f9f7; color: #009688;'; break;
                            case 'Cancelada': echo 'background-color: #f5f5f5; color: #8c8c8c;'; break;
                        }
                        ?>">
                        <?= htmlspecialchars($app['ApplicationStatus']) ?>
                    </span>
                </p>
                <p><strong>Fecha solicitud:</strong> <?= date('d/m/Y H:i', strtotime($app['Application_date'])) ?></p>
                <p><strong>Periodo:</strong> <?= date('d/m/Y', strtotime($app['Start_date'])) ?> al <?= date('d/m/Y', strtotime($app['End_date'])) ?></p>
                <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($app['KitDescription'])) ?></p>

                <form id="<?= $form_id ?>" action="view_applications.php?id=<?= $student_id ?>" method="POST" onsubmit="return validarFormulario('<?= $form_id ?>')">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                    <input type="hidden" name="kit_id" value="<?= htmlspecialchars($app['ID_Kit']) ?>"> 

                    <label for="<?= $select_id ?>">Cambiar estatus a:</label>
                    <select name="new_status" id="<?= $select_id ?>" onchange="mostrarAdvertencia('<?= $select_id ?>')">
                        <?php foreach ($VALID_STATUSES as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" 
                                <?= ($option == $app['ApplicationStatus']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Advertencia para cancelación -->
                    <div id="warning_<?= $select_id ?>" class="cancel-warning"></div>
                    
                    <button type="submit" class="btn-action">
                        <i class="fas fa-save"></i> Actualizar Estatus
                    </button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background-color: #f8f9fa; border-radius: 8px;">
            <p style="font-size: 18px; color: #6c757d;">Este estudiante no tiene solicitudes de beca registradas.</p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; text-align: center;">
        <a href="javascript:history.back()" style="color: #007bff; text-decoration: none;">
            ← Volver atrás
        </a>
    </div>
</div>

</body>
</html>