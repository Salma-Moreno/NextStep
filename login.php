<?php
session_start();
require 'db_conexion.php'; // Incluye la conexión

// Verificar que se envíen datos por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'];
    $password = $_POST['password'];

    // --- 1. Consulta preparada para seguridad ---
    // Usamos JOIN para obtener el tipo de rol (Student, Staff) de una vez
    $sql = "SELECT 
                u.ID_User, 
                u.Password, 
                r.Type,
                u.Status
            FROM 
                user u
            JOIN 
                role r ON u.FK_ID_Role = r.ID_Role
            WHERE 
                u.Username = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // --- 2. Verificar si el usuario existe ---
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // --- 3. Verificar la contraseña hasheada ---
        if (password_verify($password, $user['Password'])) {
            
            // --- 4. Verificar si el usuario está activo ---
            if ($user['Status'] !== 'Active') {
                $_SESSION['error'] = "Tu cuenta está inactiva. Contacta al administrador.";
                header('Location: index.php');
                exit;
            }

            // --- 5. Iniciar Sesión (ÉXITO) ---
            $_SESSION['usuario_id'] = $user['ID_User'];
            $_SESSION['usuario_rol'] = $user['Type']; // Ej: "Student" o "Staff"

            // --- 6. Obtener el nombre del usuario (Student o Staff) ---
            $nombre = '';
            if ($user['Type'] == 'Student') {
                $stmt_nombre = $conn->prepare("SELECT Name FROM student WHERE FK_ID_User = ?");
            } else {
                $stmt_nombre = $conn->prepare("SELECT Firstname FROM staff WHERE FK_ID_User = ?");
            }
            
            $stmt_nombre->bind_param("i", $user['ID_User']);
            $stmt_nombre->execute();
            $stmt_nombre->bind_result($nombre);
            $stmt_nombre->fetch();
            $stmt_nombre->close();

            $_SESSION['usuario_nombre'] = $nombre;
            
            // --- 7. Redirigir al dashboard correspondiente ---
            if ($user['Type'] == 'Student') {
                header('Location: ../Student/index.php');
            } else {
                header('Location: ../Staff/index.php');
            }
            exit;

        }
    }
    
    // --- FALLO: Usuario no encontrado o contraseña incorrecta ---
    $_SESSION['error'] = "Usuario o contraseña incorrectos.";
    header('Location: index.php');
    exit;

} else {
    // Redirigir si no es POST
    header('Location: index.php');
    exit;
}
?>