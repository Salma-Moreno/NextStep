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
    <!-- Agregar Font Awesome para el icono del ojo -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos adicionales para el botón de mostrar contraseña */
        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-wrapper input {
            width: 100%;
            padding-right: 40px;
            box-sizing: border-box;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
        }
        
        .toggle-password:hover {
            color: #333;
        }
    </style>
</head>
<body> 
    <?php include '../includes/header.php'; ?>  

    <div class="container">
        <h1>Iniciar Sesión <br> (Staff)</h1>

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
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePassword()">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
            </div>
            <button type="submit" class="boton">Entrar</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            ¿No tienes una cuenta? <a href="../Staff/authorization.php">¡Regístrate!</a>
        </p>

    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.className = "fa-solid fa-eye-slash";
            } else {
                passwordInput.type = "password";
                eyeIcon.className = "fa-solid fa-eye";
            }
        }
        
        // También puedes agregar soporte para la tecla Enter
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>