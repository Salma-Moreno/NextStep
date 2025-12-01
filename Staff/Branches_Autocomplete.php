<?php
session_start();
header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Staff') {
    echo json_encode([]);
    exit;
}

if (!isset($_GET["q"]) || trim($_GET["q"]) === "") {
    echo json_encode([]);
    exit;
}

$q = trim($_GET["q"]);

// Bounding box Tijuana
$viewbox = "-117.18,32.58,-116.80,32.38";

// Query Nominatim
$url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
    "format"   => "json",
    "addressdetails" => 1,
    "limit"    => 8,
    "bounded"  => 1,
    "viewbox"  => $viewbox,
    "q"        => $q . " Tijuana Baja California"
]);

$opts = [
    "http" => [
        "header" => "User-Agent: NextStep-System/1.0 (contacto@localhost)\r\n"
    ]
];

$ctx = stream_context_create($opts);
$res = @file_get_contents($url, false, $ctx);

if (!$res) {
    echo json_encode([]);
    exit;
}

$data = json_decode($res, true);

echo json_encode($data);
?>
