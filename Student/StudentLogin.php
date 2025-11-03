<?php
session_start();

// Si el estudiante ya inició sesión, redirigir al dashboard
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] === 'Student') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextStep - Student Login</title>
    <link rel="stylesheet" href="../assets/Login.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h1>Iniciar Sesión (Student)</h1>

        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <form action="../login.php" method="POST">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="boton">Entrar</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            ¿No tienes una cuenta? <a href="register.php">Regístrate</a>
        </p>
    </div>
</body>
</html>
