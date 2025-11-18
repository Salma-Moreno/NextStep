<?php
session_start();

// Código válido de prueba (puedes luego obtenerlo de la base de datos)
define("AUTH_CODE", "STAFF-2025-ABC");

// Variable para mostrar mensajes en pantalla
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputCode = trim($_POST['auth-code']);

    if ($inputCode === AUTH_CODE) {
        // Si el código es correcto, asignamos una sesión temporal
        $_SESSION['staff_authorized'] = true;
        header('Location: register.php');
        exit;
    } else {
        $error = "Código inválido. Por favor revisa o contacta al administrador.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Autorización Staff</title>
    <link rel="stylesheet" href="../assets/Staff/Auth.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>  
    <div class="auth-container">
        <h2>Autorización para Staff</h2>
        <p>Ingresa el código proporcionado por el administrador para continuar con tu registro.</p>

        <form class="auth-form" method="POST">
            <label for="auth-code">Código de Autorización:</label>
            <input type="text" id="auth-code" name="auth-code" placeholder="Ej. STAFF-2025-ABC" required>

            <button type="submit" class="btn">Verificar código</button>

            <?php if ($error): ?>
                <p class="error-msg"><?= $error ?></p>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>

