<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../assets/Staff/PerfilStaff.css">
    <title>Perfil del Staff</title>
</head>

<body>
    <?php include '../includes/HeaderMenuStaff.php'; ?> 
    <!-- Contenedor principal -->
    <div class="container">
        <div class="profile-section">
            <img src="https://via.placeholder.com/140" alt="Foto del staff" class="profile-pic">
            <button class="change-photo-btn">Cambiar foto</button>
        </div>

        <h2>Perfil del Staff</h2>

        <div class="form-grid">
            <!-- Columna 1 -->
            <div class="form-section">
                <h3>Información personal</h3>
                <div class="form-group">
                    <label>Nombre/s:</label>
                    <input type="text" placeholder="Nombre">
                </div>
                <div class="form-group">
                    <label>Apellido:</label>
                    <input type="text" placeholder="Apellido">
                </div>
                <div class="form-group">
                    <label>Teléfono:</label>
                    <input type="tel" placeholder="Ej. 526640774531">
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="form-section">
                <h3>Datos de acceso</h3>
                <div class="form-group">
                    <label>Correo electrónico:</label>
                    <input type="email" placeholder="correo@ejemplo.com">
                </div>
                <div class="form-group password-group">
                    <label>Contraseña:</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" placeholder="********" oninput="validatePasswords()">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                    </div>
                </div>
                <div class="form-group password-group">
                    <label>Confirmar contraseña:</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm-password" placeholder="********" oninput="validatePasswords()">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm-password')">👁️</button>
                    </div>
                    <p id="password-error" class="error-message"></p>
                </div>
            </div>

            <!-- Columna 3 -->
            <div class="form-section">
                <h3>Datos del sistema</h3>
                <div class="form-group">
                    <label>ID del Usuario:</label>
                    <input type="text" placeholder="ID en base de datos" disabled>
                </div>
                <div class="form-group">
                    <label>Registrado desde:</label>
                    <input type="text" placeholder="20/11/2025" disabled>
                </div>
            </div>
        </div>

        <button class="btn" id="save-btn" disabled>Guardar cambios</button>
    </div>
    <script src="../Java.js"></script>
</body>
</html>