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
    
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    
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

                <div id="mapa">
                    </div>

        <h3 class="list-title">Sucursales disponibles</h3>
        <div class="branches">
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
                    </div>
    </div>

        <script>
        // Coordenadas de ejemplo (Centro de México)
        const LATITUD_INICIAL = 19.4326; 
        const LONGITUD_INICIAL = -99.1332;
        const ZOOM_INICIAL = 12;

        // 1. Crea la instancia del mapa en el div con id="mapa"
        const map = L.map('mapa').setView([LATITUD_INICIAL, LONGITUD_INICIAL], ZOOM_INICIAL);

        // 2. Agrega la capa de mosaicos (tiles) de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Función para agregar marcadores (ejemplo estático)
        function agregarMarcadoresSucursales() {
            // Estas coordenadas son ejemplos. En la realidad, las obtendrías de tu base de datos.
            const sucursales = [
                { lat: 19.4326 + 0.01, lng: -99.1332 + 0.01, nombre: "Centro Comunitario Reforma" },
                { lat: 19.4326 - 0.01, lng: -99.1332 - 0.01, nombre: "Universidad Autónoma Campus Norte" },
                { lat: 19.4326 + 0.02, lng: -99.1332, nombre: "Biblioteca Estatal Justo Sierra" }
            ];

            sucursales.forEach(sucursal => {
                L.marker([sucursal.lat, sucursal.lng])
                    .addTo(map)
                    .bindPopup(`<b>${sucursal.nombre}</b><br>Punto de entrega.`);
            });
        }
        
        // Llama a la función para mostrar los marcadores iniciales
        agregarMarcadoresSucursales();


        // Lógica de búsqueda del Código Postal
        document.querySelector('.btn').addEventListener('click', () => {
            const cp = document.getElementById('codigoPostal').value;
            alert(`Buscando sucursales cercanas al código postal: ${cp}`);
            
            // *** Pendiente de implementación real: ***
            // 1. Llamada a PHP/BD con AJAX usando el CP.
            // 2. Obtener las coordenadas del centro del CP y las sucursales.
            // 3. Usar map.setView([nueva_lat, nueva_lng], 13); para centrar el mapa.
            // 4. Limpiar marcadores viejos y llamar a agregarMarcadoresSucursales(nuevos_datos).
        });
    </script>
</body>
</html>
