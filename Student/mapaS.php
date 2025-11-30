<?php
session_start();

// Verificar sesión
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

include '../Conexiones/db.php';

// Obtener todos los puntos con coordenadas válidas
$query = "SELECT ID_Point, Name, address, Phone_number, latitude, longitude 
          FROM collection_point
          WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = mysqli_query($conn, $query);

// Convertir a array para usar en JS
$points = [];
while ($row = mysqli_fetch_assoc($result)) {
    $points[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos de Entrega - Estudiante</title>
    <link rel="stylesheet" href="../assets/Student/Maps.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

</head>
<body>

<?php include '../Includes/HeaderMenuE.php'; ?>

<div class="container">

    <h2>Puntos de Entrega Cercanos</h2>
    <p class="subtitle">Selecciona un punto de entrega cercano según tu ubicación.</p>

    <!-- MAPA -->
    <div id="map"></div>

    <h3>Sucursales disponibles</h3>
    <div id="branches"></div>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- OpenRouteService (distancias/rutas) -->
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<script>
const ORS_API_KEY = "AQUI_TU_API_KEY";

// Datos obtenidos desde PHP
const points = <?php echo json_encode($points); ?>;

let map = L.map('map').setView([19.4326, -99.1332], 12); // CDMX por defecto

// Capa del mapa (OpenStreetMap gratuito)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Mapa por OpenStreetMap'
}).addTo(map);

let userMarker = null;
let markers = [];

// Obtener la ubicación del estudiante
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(position => {
        let lat = position.coords.latitude;
        let lng = position.coords.longitude;

        map.setView([lat, lng], 14);

        // Agregar marcador del usuario
        userMarker = L.marker([lat, lng], { title: "Tu ubicación" }).addTo(map);

        // Mostrar puntos y calcular distancias
        renderPoints(lat, lng);
    });
} else {
    alert("Tu navegador no permite obtener la ubicación.");
    renderPoints(null, null);
}

function renderPoints(userLat, userLng) {
    const container = document.getElementById("branches");
    container.innerHTML = "";

    points.forEach(async p => {
        // Agregar marcador al mapa
        let marker = L.marker([p.latitude, p.longitude]).addTo(map);
        markers.push(marker);

        let distanceText = "Ubicación no disponible";

        if (userLat !== null) {
            let dist = await getDistance(userLat, userLng, p.latitude, p.longitude);
            distanceText = (dist / 1000).toFixed(2) + " km";
        }

        // Agregar card HTML
        container.innerHTML += `
            <div class="branch-card">
                <h4>${p.Name}</h4>
                <p><strong>Dirección:</strong> ${p.address}</p>
                <p><strong>Teléfono:</strong> ${p.Phone_number}</p>
                <p class="distance"><strong>Distancia:</strong> ${distanceText}</p>
                <button class="btn-select">Seleccionar</button>
            </div>
        `;
    });
}

// Función para obtener distancia con OpenRouteService
async function getDistance(lat1, lng1, lat2, lng2) {
    try {
        let response = await axios.post(
            "https://api.openrouteservice.org/v2/matrix/driving-car",
            {
                locations: [
                    [lng1, lat1], // usuario
                    [lng2, lat2]  // sucursal
                ]
            },
            {
                headers: {
                    "Authorization": ORS_API_KEY,
                    "Content-Type": "application/json"
                }
            }
        );

        return response.data.distances[0][1]; // distancia en metros

    } catch (err) {
        console.error("Error ORS:", err);
        return null;
    }
}
</script>

</body>
</html>
