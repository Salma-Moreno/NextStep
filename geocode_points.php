<?php
require 'Conexiones/db.php';  // tu conexión a MySQL

// TU API KEY DE OPENROUTESERVICE
$apiKey = "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjVmYzZkY2JjNGZmNDQ4NTlhNjUzMmNhYzk5M2U5NzU5IiwiaCI6Im11cm11cjY0In0=";

// Obtener todos los puntos que no tengan coordenadas
$query = "SELECT ID_Point, address FROM collection_point WHERE latitude IS NULL OR longitude IS NULL";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error en la consulta: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($result)) {

    $id = $row["ID_Point"];
    $address = urlencode($row["address"]);

    // Llamada a la API
    $url = "https://api.openrouteservice.org/geocode/search?api_key=$apiKey&text=$address";

    $json = file_get_contents($url);
    $data = json_decode($json, true);

    // Validar respuesta
    if (isset($data["features"][0]["geometry"]["coordinates"])) {

        $lon = $data["features"][0]["geometry"]["coordinates"][0];
        $lat = $data["features"][0]["geometry"]["coordinates"][1];

        echo "<p>✔ Dirección: $address → LAT: $lat | LON: $lon</p>";

        // Actualizar coordenadas
        $update = "UPDATE collection_point SET latitude=$lat, longitude=$lon WHERE ID_Point=$id";
        mysqli_query($conn, $update);

    } else {
        echo "<p>❌ No se pudo geocodificar: $address</p>";
    }
}

echo "<h3>Proceso terminado.</h3>";
