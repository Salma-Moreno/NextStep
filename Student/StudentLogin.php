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
    <!-- Agregar Font Awesome para los iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos para el botón de mostrar contraseña */
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
            background: none;
            border: none;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #333;
        }
        
        /* Mejoras visuales para los inputs */
        .form-group input {
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
    </style>
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
                <input type="text" id="username" name="username" required 
                       placeholder="Ingresa tu nombre de usuario">
            </div>
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required 
                           placeholder="Ingresa tu contraseña">
                    <button type="button" class="toggle-password" onclick="togglePassword()" 
                            aria-label="Mostrar/ocultar contraseña">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="boton">Entrar</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            ¿No tienes una cuenta? <a href="register.php">Regístrate</a>
        </p>
    </div>

    <script>
        // Función para mostrar/ocultar contraseña
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
        
        // Opcional: Permitir mostrar/ocultar con tecla Enter en el campo de contraseña
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('form').submit();
            }
        });
        
        // Opcional: Autofocus en el campo de usuario al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>