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
    <title>Entregas - Estudiante</title>
    <link rel="stylesheet" href="../assets/mapa.css">
    
    <!-- Simulación de integración de mapa (más adelante podrías usar Google Maps o Leaflet) -->
    <style>
        #mapa {
            width: 100%;
            height: 400px;
            border-radius: 12px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>

    <?php include '../Includes/HeaderMenuE.php'; ?>

     <div class="container">
        <h2>Puntos de Entrega Cercanos</h2>
        <p class="subtitle">Selecciona el punto de entrega más conveniente según tu ubicación.</p>

        <div class="search-section">
            <label for="codigoPostal">Ingresa tu código postal:</label>
            <input type="text" id="codigoPostal" placeholder="Ej. 24010">
            <button class="btn">Buscar</button>
        </div>

        <!-- Simulación del mapa -->
        <div id="mapa">
            <!-- Aquí se mostrará el mapa dinámico -->
            <p style="text-align:center; padding-top:180px; color:#666;">
                [Aquí se mostrará el mapa con tus sucursales cercanas]
            </p>
        </div>

        <h3 class="list-title">Sucursales disponibles</h3>
        <div class="branches">
            <!-- Simulación de datos dinámicos -->
            <!-- <?php while($row = mysqli_fetch_assoc($result)) { ?> -->
            <div class="branch-card">
                <h4>Centro Comunitario Reforma</h4>
                <p><strong>Dirección:</strong> Av. Reforma #320, Col. Centro</p>
                <p><strong>Código Postal:</strong> 24010</p>
                <p><strong>Horario:</strong> Lunes a Viernes, 9:00 a 17:00</p>
                <button class="btn-select">Seleccionar</button>
            </div>

            <div class="branch-card">
                <h4>Universidad Autónoma Campus Norte</h4>
                <p><strong>Dirección:</strong> Blvd. del Estudiante s/n</p>
                <p><strong>Código Postal:</strong> 24030</p>
                <p><strong>Horario:</strong> Lunes a Viernes, 8:00 a 16:00</p>
                <button class="btn-select">Seleccionar</button>
            </div>

            <div class="branch-card">
                <h4>Biblioteca Estatal Justo Sierra</h4>
                <p><strong>Dirección:</strong> Calle 10 #150, Col. Morelos</p>
                <p><strong>Código Postal:</strong> 24040</p>
                <p><strong>Horario:</strong> Martes a Sábado, 10:00 a 18:00</p>
                <button class="btn-select">Seleccionar</button>
            </div>
            <!-- <?php } ?> -->
        </div>
    </div>

    <!-- Ejemplo de mapa simulado (Leaflet placeholder) -->
    <!-- En implementación real: cargaría marcadores basados en CP -->
    <script>
        // Este script es solo ilustrativo.
        document.querySelector('.btn').addEventListener('click', () => {
            const cp = document.getElementById('codigoPostal').value;
            alert(`Buscar sucursales cercanas al código postal: ${cp}`);
        });
    </script>
</body>
</html>
