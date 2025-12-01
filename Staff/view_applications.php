<?php
// Archivo: view_applications.php

session_start();
require '../Conexiones/db.php'; // Asegúrate que este archivo define $conn (mysqli)

// Opciones de estatus disponibles para validación
$VALID_STATUSES = ['Enviada', 'En revisión', 'Aprobada', 'Entrega'];

// 1. Guardián de sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// Inicializar variables de mensaje
$message_type = '';
$message_text = '';

// --- LÓGICA DE ACTUALIZACIÓN DEL ESTATUS (Procesamiento POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id_post = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $kit_id_post = filter_input(INPUT_POST, 'kit_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

    if ($student_id_post && $kit_id_post && in_array($new_status, $VALID_STATUSES)) {
        
        // Consulta para actualizar el estatus
        $update_query = "
            UPDATE aplication
            SET status = ?
            WHERE FK_ID_Student = ? AND FK_ID_Kit = ?;
        ";

        $stmt = mysqli_prepare($conn, $update_query);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sii", $new_status, $student_id_post, $kit_id_post);
            
            if (mysqli_stmt_execute($stmt)) {
                $message_type = 'success';
                $message_text = "Estatus actualizado a: " . htmlspecialchars($new_status);
            } else {
                $message_type = 'error';
                $message_text = "Error al actualizar en la BD: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $message_type = 'error';
            $message_text = "Error de consulta preparada: " . mysqli_error($conn);
        }

    } else {
        $message_type = 'error';
        $message_text = "Datos de entrada inválidos o estatus no permitido.";
    }
    
    // REDIRECCIÓN PRG (Post/Redirect/Get) - CRÍTICO para evitar reenvío de formulario
    // Redirigimos a la misma página, pero pasando el mensaje por GET.
    $redirect_url = 'view_applications.php?id=' . ($student_id_post ?: $_GET['id']);
    $redirect_url .= '&msg_type=' . $message_type . '&msg_text=' . urlencode($message_text);
    header('Location: ' . $redirect_url);
    exit;
}
// --- FIN LÓGICA DE ACTUALIZACIÓN ---


// 2. Obtener el ID del estudiante (GET request)
$student_id = $_GET['id'] ?? null;

// Validar ID
if (!$student_id || !is_numeric($student_id)) {
    die("ID de estudiante inválido.");
}

// 3. Obtener el nombre del estudiante (para el título)
$name_query = "SELECT Name, Last_Name FROM student WHERE ID_Student = ? LIMIT 1";
$name_stmt = mysqli_prepare($conn, $name_query);
mysqli_stmt_bind_param($name_stmt, "i", $student_id);
mysqli_stmt_execute($name_stmt);
$name_result = mysqli_stmt_get_result($name_stmt);
$student_name = mysqli_fetch_assoc($name_result);

if (!$student_name) {
    die("Estudiante no encontrado.");
}
$full_name = htmlspecialchars($student_name['Name'] . ' ' . $student_name['Last_Name']);

// 4. Consulta para obtener todas las aplicaciones de beca (kit) para ese estudiante.
$applications_query = "
    SELECT 
        k.ID_Kit,  
        k.Name AS KitName, 
        k.Description AS KitDescription,
        a.status AS ApplicationStatus,
        k.Start_date,
        k.End_date
    FROM aplication a
    JOIN kit k ON a.FK_ID_Kit = k.ID_Kit
    WHERE a.FK_ID_Student = ?
    ORDER BY k.Start_date DESC;
";

$stmt = mysqli_prepare($conn, $applications_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$applications_result = mysqli_stmt_get_result($stmt);

// Verificar si se encontraron aplicaciones
$has_applications = mysqli_num_rows($applications_result) > 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitudes de Beca - <?= $full_name ?></title>
    <link rel="stylesheet" href="../assets/Staff/list.css"> 
    <style>
        /* Estilos CSS para tarjetas y mensajes (puedes moverlos a list.css) */
        .application-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        /* Clases CSS para el borde de la tarjeta según el estatus */
        .status-enviada { background-color: #e6f7ff; border-left: 5px solid #1890ff; }
        .status-en-revisión { background-color: #fffbe6; border-left: 5px solid #faad14; }
        .status-aprobada { background-color: #f6ffed; border-left: 5px solid #52c41a; }
        .status-entrega { background-color: #e8f9f7; border-left: 5px solid #009688; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
    </style>
</head>
<body>
<?php include '../includes/HeaderMenuStaff.php'; ?> 

<div class="container">
    <?php 
    // 5. Mostrar mensajes de éxito o error (vienen de la redirección GET)
    if (isset($_GET['msg_type'])): 
        $msg_type = htmlspecialchars($_GET['msg_type']);
        $msg_text = htmlspecialchars(urldecode($_GET['msg_text']));
    ?>
        <div class="alert-<?= $msg_type ?>">
            <?= $msg_type === 'success' ? '✅ Éxito:' : '❌ Error:' ?> <?= $msg_text ?>
        </div>
    <?php endif; ?>
    
    <h2>📝 Solicitudes de Beca de <?= $full_name ?></h2>


    <hr>

    <?php if ($has_applications): ?>
        <?php while ($app = mysqli_fetch_assoc($applications_result)): ?>
            <?php 
                // Normaliza el estado para usarlo en la clase CSS
                $status_class = strtolower(str_replace(' ', '-', $app['ApplicationStatus']));
            ?>
            <div class="application-card status-<?= htmlspecialchars($status_class) ?>">
                <h3><?= htmlspecialchars($app['KitName']) ?></h3>
                
                <p><strong>Estatus Actual:</strong> <span style="font-weight: bold;"><?= htmlspecialchars($app['ApplicationStatus']) ?></span></p>
                <p><strong>Periodo:</strong> <?= date('d/m/Y', strtotime($app['Start_date'])) ?> al <?= date('d/m/Y', strtotime($app['End_date'])) ?></p>
                <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($app['KitDescription'])) ?></p>

                <form action="view_applications.php?id=<?= $student_id ?>" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($student_id) ?>">
                    <input type="hidden" name="kit_id" value="<?= htmlspecialchars($app['ID_Kit']) ?>"> 

                    <label for="new_status_<?= $app['ID_Kit'] ?>">Cambiar estatus a:</label>
                    <select name="new_status" id="new_status_<?= $app['ID_Kit'] ?>">
                        <?php foreach ($VALID_STATUSES as $option): ?>
                            <option value="<?= htmlspecialchars($option) ?>" 
                                <?= ($option == $app['ApplicationStatus']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn-action" style="margin-left: 10px; background-color: #f7b000;">Actualizar</button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>Este estudiante no tiene solicitudes de beca registradas.</p>
    <?php endif; ?>


</div>

</body>
</html>