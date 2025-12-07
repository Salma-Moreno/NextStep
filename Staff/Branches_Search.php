<?php
session_start();
require "../Conexiones/db.php";

header("Content-Type: application/json; charset=utf-8");

// Solo Staff
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode(["success" => false, "error" => "Acceso no autorizado"]);
    exit;
}

// Validar dirección
if (!isset($_POST['address']) || trim($_POST['address']) === "") {
    echo json_encode(["success" => false, "error" => "No se recibió la dirección"]);
    exit;
}

$rawAddress = trim($_POST['address']);

// Normalizar dirección:
// - Quitar palabras que confunden a Nominatim
$cleanAddress = preg_replace([
    "/\bLateral\b/i",
    "/\bB\.?C\.?\b/i",
    "/\bII\b/i",
    "/\bIII\b/i",
], "", $rawAddress);

// Convertir múltiples espacios en uno
$cleanAddress = preg_replace('/\s+/', ' ', $cleanAddress);

// Función auxiliar para consultar Nominatim
function nominatimSearch($query) {
    $q = urlencode($query);
    $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=$q";

    $opts = ["http" => [
        "header" => "User-Agent: NextStep-System/1.0 (contacto@localhost)\r\n"
    ]];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;

    $data = json_decode($response, true);
    return ($data && count($data) > 0) ? $data[0] : null;
}


// ======================
// 1️er Intento: dirección original
// ======================
$res = nominatimSearch($rawAddress);

if ($res) {
    echo json_encode([
        "success" => true,
        "latitude" => $res["lat"],
        "longitude" => $res["lon"],
        "display_name" => $res["display_name"]
    ]);
    exit;
}


// ======================
// 2️do Intento: dirección limpiada
// ======================
$res = nominatimSearch($cleanAddress);

if ($res) {
    echo json_encode([
        "success" => true,
        "latitude" => $res["lat"],
        "longitude" => $res["lon"],
        "display_name" => $res["display_name"]
    ]);
    exit;
}


// ======================
// 3️er Intento: solo calle + Tijuana
// ======================
preg_match('/^[^,]+/', $cleanAddress, $streetOnly);
$streetOnly = $streetOnly[0] ?? $cleanAddress;
$res = nominatimSearch($streetOnly . " Tijuana Baja California Mexico");

if ($res) {
    echo json_encode([
        "success" => true,
        "latitude" => $res["lat"],
        "longitude" => $res["lon"],
        "display_name" => $res["display_name"]
    ]);
    exit;
}


// ======================
// 4to Intento: solo CP + Tijuana
// ======================
if (preg_match('/\b\d{5}\b/', $cleanAddress, $cp)) {
    $res = nominatimSearch("Tijuana $cp[0] Mexico");

    if ($res) {
        echo json_encode([
            "success" => true,
            "latitude" => $res["lat"],
            "longitude" => $res["lon"],
            "display_name" => $res["display_name"]
        ]);
        exit;
    }
}


// ======================
// ❌ No encontrado
// ======================
echo json_encode([
    "success" => false,
    "error" => "No se encontró la dirección. Prueba una más general o revisa la ortografía."
]);
exit;

?>

