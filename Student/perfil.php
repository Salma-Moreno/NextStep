<?php
session_start();

// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}
//Conexion a la base de datos
include '../Conexiones/db.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Personales - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/viewStudent.css">
    
</head>
<body>

    <?php include '../Includes/HeaderMenuE.php'; ?>
<!-- === CONTENIDO PRINCIPAL === -->
    <div class="container">
        <div class="profile-section">
            <img src="https://via.placeholder.com/140" alt="Foto del estudiante" class="profile-pic">
            <button class="change-photo-btn">Cambiar foto</button>
        </div>

        <h2>Ficha del Estudiante</h2>

        <div class="form-grid">
            <!-- Columna 1 -->
            <div class="form-section">
                <h3>Datos del estudiante</h3>
                <div class="form-group">
                    <label>Nombre:</label>
                    <input type="text" placeholder="Nombre">
                </div>
                <div class="form-group">
                    <label>Apellido:</label>
                    <input type="text" placeholder="Apellido">
                </div>
                <div class="form-group">
                    <label>Teléfono:</label>
                    <input type="tel" placeholder="Ej. 5512345678">
                </div>
                <div class="form-group">
                    <label>Correo electrónico:</label>
                    <input type="email" placeholder="correo@ejemplo.com">
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="form-section">
                <h3>Detalles académicos</h3>
                <div class="form-group">
                    <label>Fecha de nacimiento:</label>
                    <input type="date">
                </div>
                <div class="form-group">
                    <label>Preparatoria:</label>
                    <input type="text" placeholder="Nombre de la preparatoria">
                </div>
                <div class="form-group">
                    <label>Grado:</label>
                    <input type="text" placeholder="Ej. 3°">
                </div>
                <div class="form-group">
                    <label>Licencia:</label>
                    <input type="text" placeholder="Ej. B-12345">
                </div>
                <div class="form-group">
                    <label>Promedio:</label>
                    <input type="number" placeholder="0 - 100" min="0" max="100">
                </div>
            </div>

            <!-- Columna 3 -->
            <div class="form-section">
                <h3>Datos del tutor</h3>
                <div class="form-group">
                    <label>Nombre:</label>
                    <input type="text" placeholder="Nombre del tutor">
                </div>
                <div class="form-group">
                    <label>Apellidos:</label>
                    <input type="text" placeholder="Apellido del tutor">
                </div>
                <div class="form-group">
                    <label>Teléfono:</label>
                    <input type="tel" placeholder="Teléfono del tutor">
                </div>
                <div class="form-group">
                    <label>Dirección:</label>
                    <input type="text" placeholder="Domicilio del tutor">
                </div>
            </div>
        </div>

        <!-- Dirección del estudiante (debajo del grid) -->
        <div class="form-section address">
            <h3>Domicilio del estudiante</h3>
            <div class="form-group">
                <label>Calle:</label>
                <input type="text" placeholder="Ej. Reforma #123">
            </div>
            <div class="form-group">
                <label>Ciudad:</label>
                <input type="text" placeholder="Ej. Guadalajara">
            </div>
            <div class="form-group">
                <label>Código postal:</label>
                <input type="text" placeholder="Ej. 44100">
            </div>
        </div>

        <button class="btn">Guardar información</button>
    </div>

</body>
</html>
