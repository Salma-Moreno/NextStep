<?php
session_start();
require 'Conexiones/db.php'; // conexión a la BD

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Detectar desde qué formulario se envió
    $from_staff_login = strpos($_SERVER['HTTP_REFERER'], 'StaffLogin.php') !== false;
    $from_student_login = strpos($_SERVER['HTTP_REFERER'], 'StudentLogin.php') !== false;

    // Consulta usuario
    $sql = "SELECT u.ID_User, u.Password, r.Type, u.Status
            FROM user u
            JOIN role r ON u.FK_ID_Role = r.ID_Role
            WHERE u.Username = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) die("Error al preparar consulta: " . $conn->error);

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verificar contraseña
        if (password_verify($password, $user['Password'])) {

            // Verificar cuenta activa
            if ($user['Status'] !== 'Active') {
                $_SESSION['error'] = "Tu cuenta está inactiva. Contacta al administrador.";
                redirect_back($user['Type']);
            }

            // --- Validar desde qué login viene ---
            if ($from_staff_login && $user['Type'] !== 'Staff') {
                $_SESSION['error'] = "Solo el personal puede iniciar sesión desde esta pantalla.";
                header('Location: Staff/StaffLogin.php');
                exit;
            }
            if ($from_student_login && $user['Type'] !== 'Student') {
                $_SESSION['error'] = "Solo los estudiantes pueden iniciar sesión desde esta pantalla.";
                header('Location: Student/StudentLogin.php');
                exit;
            }

            // Iniciar sesión
            $_SESSION['usuario_id'] = $user['ID_User'];
            $_SESSION['usuario_rol'] = $user['Type'];

            // Obtener nombre
            $nombre = '';
            if ($user['Type'] === 'Student') {
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

            // Redirigir al dashboard correcto
            if ($user['Type'] === 'Student') {
                header('Location: Student/index.php');
            } else {
                header('Location: Staff/index.php');
            }
            exit;
        }
    }

    // Usuario o contraseña incorrectos
    $_SESSION['error'] = "Usuario o contraseña incorrectos.";

    if ($from_staff_login) {
        header('Location: Staff/StaffLogin.php');
    } else {
        header('Location: Student/StudentLogin.php');
    }
    exit;

} else {
    // Si alguien entra directamente al login.php
    header('Location: index.php');
    exit;
}

// Función auxiliar
function redirect_back($role) {
    if ($role === 'Staff') {
        header('Location: Staff/StaffLogin.php');
    } else {
        header('Location: Student/StudentLogin.php');
    }
    exit;
}
?>

