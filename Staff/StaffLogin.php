<?php
session_start();
// Si el usuario YA inició sesión Y es 'Staff', enviarlo al dashboard
if (isset($_SESSION['usuario_id']) && $_SESSION['usuario_rol'] === 'Staff') {
    header('Location: index.php'); // O la ruta a tu dashboard de staff
    exit;
} 

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextStep - Staff LogIn</title>
    <link rel="stylesheet" href="../assets/Login.css">
</head>
<body> 
    <?php include '../includes/header.php'; ?>  

<div class="container">
        <h1>Iniciar Sesión</h1>

        <?php
        // Esto ahora sí funcionará
        if (isset($_SESSION['error'])) {
            echo '<div class="error">' . $_SESSION['error'] . '</div>';
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
            Don't have an account? <a href="../Staff/register.php">Sign up!</a>
        </p>

    </div>
</body>
</html>