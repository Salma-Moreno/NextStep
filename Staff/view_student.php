<?php
session_start();
require '../Conexiones/db.php'; // Asegúrate que este archivo define $conn (mysqli)

// Guardián de sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    header('Location: StaffLogin.php');
    exit;
}

// 1. Obtener el ID del estudiante de la URL (GET request)
$student_id = $_GET['id'] ?? null;

// Validar que se recibió un ID y que es numérico (seguridad básica)
if (!$student_id || !is_numeric($student_id)) {
    die("ID de estudiante inválido.");
}

// 2. Consulta para obtener todos los detalles del estudiante específico
// *** Se ha ELIMINADO la unión con la tabla 'aplication' y la columna 'a.status' ***
$query = "
    SELECT 
        s.ID_Student, s.Name, s.Last_Name, s.Email_Address, 
        sd.License, sd.Average
    FROM student s
    LEFT JOIN student_details sd ON s.ID_Student = sd.FK_ID_Student
    WHERE s.ID_Student = ? 
    LIMIT 1;
";

// Usar prepared statements para prevenir inyección SQL (MUY RECOMENDADO)
$stmt = mysqli_prepare($conn, $query);
// Verificar si la preparación falló (por si acaso hay errores en la sintaxis)
if ($stmt === false) {
    die("Error en la preparación de la consulta: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $student_id); // "i" indica que el parámetro es un entero
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$student_data = mysqli_fetch_assoc($result);

// 3. Verificar si se encontraron datos
if (!$student_data) {
    die("Estudiante no encontrado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?= htmlspecialchars($student_data['Name'] . ' ' . $student_data['Last_Name']) ?></title>
    <link rel="stylesheet" href="../assets/Staff/view.css"> 
</head>
<body>
<?php include '../includes/HeaderMenuStaff.php'; ?> 

<div class="container">
    <h2>Perfil del Estudiante: <?= htmlspecialchars($student_data['Name'] . ' ' . $student_data['Last_Name']) ?></h2>

    <div class="profile-card">
        <h3>Datos Personales</h3>
        <p><strong>Nombre Completo:</strong> <?= htmlspecialchars($student_data['Name'] . ' ' . $student_data['Last_Name']) ?></p>
        <p><strong>Correo Electrónico:</strong> <?= htmlspecialchars($student_data['Email_Address'] ?? '—') ?></p>
        

        <h3>Detalles Académicos</h3>
        <p><strong>Matrícula:</strong> <?= htmlspecialchars($student_data['License'] ?? 'No asignada') ?></p>
        <p><strong>Promedio:</strong> <?= htmlspecialchars($student_data['Average'] ?? '—') ?></p>
        
        </div>

    <a href="listStudents.php" class="btn-back">← Volver a la lista de estudiantes</a>
</div>

</body>
</html>