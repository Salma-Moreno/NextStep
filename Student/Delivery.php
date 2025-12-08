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
    <p id="locationInfo" style="font-size: 0.9rem; color: #555;"></p>

    <label for="radiusSelect"><strong>Radio de búsqueda: </strong></label>
    <select id="radiusSelect">
        <option value="1000">1 km</option>
        <option value="2000">2 km</option>
        <option value="5000" selected>5 km</option>
        <option value="10000">10 km</option>
    </select>

    <div class="map-layout">
        <!-- PANEL IZQUIERDO: SUCURSALES + BUSCADOR -->
        <div class="branches-panel">
            <h3>Sucursales disponibles</h3>
            <input 
                type="text" 
                id="branchSearch" 
                class="branch-search" 
                placeholder="Buscar por sucursal o dirección...">
            
            <div id="branches"></div>
                <div id="saveMessage" class="save-message"></div>

        </div>

        <!-- PANEL DERECHO: MAPA -->
        <div class="map-panel">
            <div id="map"></div>
        </div>
    </div>

</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- OpenRouteService (distancias/rutas) -->
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<script>
let searchRadius = 5000; // valor por defecto (5 km)

const ORS_API_KEY = "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjVmYzZkY2JjNGZmNDQ4NTlhNjUzMmNhYzk5M2U5NzU5IiwiaCI6Im11cm11cjY0In0=";

// Datos obtenidos desde PHP
const points = <?php echo json_encode($points); ?>;

// Mapa base (Tijuana por defecto)
let map = L.map('map').setView([32.5149, -117.0382], 12);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Mapa por OpenStreetMap'
}).addTo(map);

// Marcadores
let userMarker = null;
let searchCircle = null;     // círculo del radio de búsqueda
let markers = [];            // sucursales
const branchMarkers = {};    // ID_Point -> marker
let selectedPointId = null;
let userLat = null;
let userLng = null;

// Permitir que el usuario ajuste su ubicación haciendo clic en el mapa
map.on('click', function(e) {
    userLat = e.latlng.lat;
    userLng = e.latlng.lng;

    console.log('Ubicación ajustada manualmente:', {
        lat: userLat,
        lng: userLng
    });

    // Mover marcador del usuario
    if (userMarker) {
        map.removeLayer(userMarker);
    }
    userMarker = L.circleMarker([userLat, userLng], {
        radius: 6,
        color: '#23487cff',
        fillColor: '#0d223f',
        fillOpacity: 0.9
    }).addTo(map).bindPopup('Tu ubicación (ajustada)').openPopup();

    // Actualizar círculo de búsqueda y sucursales
    updateSearchCircle();
    renderPoints(userLat, userLng);

    const infoEl = document.getElementById('locationInfo');
    if (infoEl) {
        infoEl.textContent = `Tu ubicación ajustada: ${userLat.toFixed(5)}, ${userLng.toFixed(5)}`;
    }
});

// Obtener la ubicación del estudiante con alta precisión
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        position => {
            userLat = position.coords.latitude;
            userLng = position.coords.longitude;
            const accuracy = position.coords.accuracy; // en metros

            console.log('Ubicación obtenida:', {
                lat: userLat,
                lng: userLng,
                accuracy: accuracy
            });

            const infoEl = document.getElementById('locationInfo');
            if (infoEl) {
                infoEl.textContent = `Tu ubicación aprox: ${userLat.toFixed(5)}, ${userLng.toFixed(5)} (± ${Math.round(accuracy)} m)`;
            }

            // Centrar mapa cerca del usuario
            map.setView([userLat, userLng], 15);

            // Marcador del usuario
            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.circleMarker([userLat, userLng], {
                radius: 6,
                color: '#681d1dff',
                fillColor: '#681d1dff',
                fillOpacity: 0.9
            }).addTo(map).bindPopup('Tu ubicación');

            // Dibujar círculo del radio de búsqueda
            updateSearchCircle();

            // Mostrar puntos y calcular distancias
            renderPoints(userLat, userLng);
        },
        error => {
            console.error('Error de geolocalización:', error);
            alert('No se pudo obtener tu ubicación precisa. Puedes hacer clic en el mapa para ajustar tu ubicación.');

            const infoEl = document.getElementById('locationInfo');
            if (infoEl) {
                infoEl.textContent = 'No se pudo obtener tu ubicación automáticamente. Haz clic en el mapa para indicar dónde estás.';
            }

            // Sin ubicación: mostrar todas las sucursales sin filtro de radio
            renderPoints(null, null);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
} else {
    alert("Tu navegador no permite obtener la ubicación. Puedes hacer clic en el mapa para indicar dónde estás.");
    renderPoints(null, null);
}

// Actualiza radio cuando el usuario cambia el select
document.getElementById("radiusSelect").addEventListener("change", function() {
    searchRadius = parseInt(this.value, 10);

    if (userLat !== null && userLng !== null) {
        updateSearchCircle();
        renderPoints(userLat, userLng);  // volver a dibujar con el nuevo radio
    }
});

// Dibuja / actualiza círculo de radio de búsqueda y ajusta el mapa
function updateSearchCircle() {
    if (userLat === null || userLng === null) return;

    if (searchCircle) {
        map.removeLayer(searchCircle);
    }

    searchCircle = L.circle([userLat, userLng], {
        radius: searchRadius,
        color: '#0d223f',
        fillColor: '#0d223f',
        fillOpacity: 0.08,
        weight: 1,
        dashArray: '4 4'
    }).addTo(map);

    // Ajustar el mapa para que se vea bien el círculo
    map.fitBounds(searchCircle.getBounds(), { padding: [30, 30] });
}

async function renderPoints(userLatParam, userLngParam) {
    const container = document.getElementById("branches");
    container.innerHTML = "";

    // limpiar marcadores anteriores de sucursales
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    Object.keys(branchMarkers).forEach(id => delete branchMarkers[id]);

    const hasUser = (userLatParam !== null && userLngParam !== null);

    for (const p of points) {
        let distanceText = "Ubicación no disponible";
        let dist = null;

        if (hasUser) {
            dist = await getDistance(userLatParam, userLngParam, p.latitude, p.longitude);

            if (dist !== null) {
                // filtro por radio de búsqueda
                if (dist > searchRadius) continue;
                distanceText = (dist / 1000).toFixed(2) + " km";
            }
        }

        // Crear marcador de sucursal
        const marker = L.marker([p.latitude, p.longitude]).addTo(map);
        marker.bindPopup(`<strong>${p.Name}</strong><br>${p.address}`);
        markers.push(marker);
        branchMarkers[p.ID_Point] = marker;

        // Crear tarjeta
        container.insertAdjacentHTML('beforeend', `
            <div class="branch-card" data-point-id="${p.ID_Point}">
                <h4>${p.Name}</h4>
                <p><strong>Dirección:</strong> ${p.address}</p>
                <p><strong>Teléfono:</strong> ${p.Phone_number || 'No disponible'}</p>
                <p class="distance"><strong>Distancia:</strong> ${distanceText}</p>
                <button class="btn-select" data-id="${p.ID_Point}">Seleccionar</button>
            </div>
        `);
    }

    if (!container.innerHTML.trim()) {
        container.innerHTML = `<p>No hay sucursales dentro del radio seleccionado.</p>`;
    }
}

// Seleccionar sucursal
function handleSelectBranch(pointId, cardElement) {
    selectedPointId = pointId;

    // 1) Resaltar la tarjeta seleccionada
    document.querySelectorAll('.branch-card').forEach(card => {
        card.classList.remove('selected');
    });
    if (cardElement) {
        cardElement.classList.add('selected');
    }

    // 2) Centrar el mapa en esa sucursal y abrir popup
    const marker = branchMarkers[pointId];
    if (marker) {
        map.setView(marker.getLatLng(), 17);
        marker.openPopup();
    }

    // 3) Mostrar mensaje de estado
    const msgBox = document.getElementById('saveMessage');
    if (msgBox) {
        msgBox.textContent = 'Guardando punto de entrega...';
        msgBox.style.color = '#333';
    }

    // 4) Llamar a save_delivery.php para guardar en la tabla delivery
    fetch('save_delivery.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ point_id: pointId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (msgBox) {
                msgBox.textContent = data.message || 'Punto de entrega guardado correctamente.';
                msgBox.style.color = 'green';
            }
        } else {
            if (msgBox) {
                msgBox.textContent = data.message || 'No se pudo guardar el punto de entrega.';
                msgBox.style.color = 'red';
            }
            alert(data.message || 'No se pudo guardar el punto de entrega.');
        }
    })
    .catch(err => {
        console.error('Error al guardar punto de entrega:', err);
        if (msgBox) {
            msgBox.textContent = 'Error de comunicación con el servidor.';
            msgBox.style.color = 'red';
        }
        alert('Ocurrió un error al guardar el punto de entrega.');
    });
}

// Delegación de eventos para botones "Seleccionar"
document.getElementById('branches').addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-select')) {
        const pointId = e.target.getAttribute('data-id');
        const card = e.target.closest('.branch-card');
        handleSelectBranch(pointId, card);
    }
});

// Distancia con ORS, con fallback Haversine en caso de error
async function getDistance(lat1, lng1, lat2, lng2) {
    try {
        const response = await axios.post(
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

        const dist = response.data?.distances?.[0]?.[1];
        if (typeof dist === 'number') {
            return dist; // metros (ruta por carretera)
        }
        console.warn("Respuesta ORS sin distancia válida, usando Haversine.");
        return haversineDistance(lat1, lng1, lat2, lng2);
    } catch (err) {
        console.error("Error ORS, usando Haversine:", err);
        return haversineDistance(lat1, lng1, lat2, lng2);
    }
}

// Fallback: distancia aproximada en línea recta (Haversine)
function haversineDistance(lat1, lng1, lat2, lng2) {
    const R = 6371000; // radio de la Tierra en metros
    const toRad = deg => deg * Math.PI / 180;

    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);

    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
              Math.sin(dLng / 2) * Math.sin(dLng / 2);

    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c; // metros
}

// Buscador de sucursales por texto
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('branchSearch');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            const cards = document.querySelectorAll('.branch-card');

            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(term) ? '' : 'none';
            });
        });
    }
});

// Delegación de eventos para botones "Seleccionar"
document.getElementById('branches').addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-select')) {
        const pointId = e.target.getAttribute('data-id');
        const card = e.target.closest('.branch-card');
        handleSelectBranch(pointId, card);
    }
});

</script>

</body>
</html>
