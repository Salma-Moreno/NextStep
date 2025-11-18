<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil del Staff</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column; 
            align-items: center; 
            background-color: #f4f4f4;
            min-height: 100vh;
            padding-top: 60px; /* Espacio para el header */
        }
        .container {
            width: 85%;
            max-width: 1100px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 50px;
        }

        /* === Encabezado de sección === */
        h2 {
            text-align: center;
            color: #007BFF;
            margin-bottom: 20px;
        }

        /* === Sección de perfil === */
        .profile-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-pic {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007BFF;
            margin-bottom: 10px;
        }

        .change-photo-btn {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
        }

        .change-photo-btn:hover {
            background-color: #0056b3;
        }

        /* === GRID PRINCIPAL === */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 20px;
        }

        .form-section h3 {
            color: #333;
            border-bottom: 2px solid #007BFF;
            padding-bottom: 8px;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
        }

        .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }

        /* === Botón de Guardar === */
        .btn {
            display: block;
            margin: 40px auto 0;
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        /* === RESPONSIVE === */
        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
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

</body>

</html>