<?php
session_start();

// 1. Guardián de seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}

include '../Conexiones/db.php';

$puntos_recogida = [];

if (isset($conn)) {
    // 2. Consulta SQL
    $sql = "SELECT 
                cp.ID_Point, 
                cp.Name, 
                cp.address, 
                cp.latitud, 
                cp.longitud,
                GROUP_CONCAT(DISTINCT s.Name SEPARATOR ', ') AS MaterialesAceptados
            FROM collection_point cp
            LEFT JOIN company c ON cp.FK_ID_Company = c.ID_Company
            LEFT JOIN donation d ON c.ID_Company = d.FK_ID_Company
            LEFT JOIN supplies s ON d.FK_ID_Supply = s.ID_Supply
            GROUP BY cp.ID_Point";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $puntos_recogida[] = $row;
        }
    }
}
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
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
        }
        
        .branch-card { 
            border: 1px solid #e0e0e0; 
            padding: 20px; 
            margin-bottom: 15px; 
            border-radius: 10px; 
            background: #fff; 
            border-left: 5px solid #3498db; 
            transition: transform 0.2s;
        }
        
        .branch-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .branch-card h4 { margin-top: 0; color: #2c3e50; font-size: 1.4rem; margin-bottom: 10px; }
        .info-label { color: #555; font-weight: 600; }
        .lista-materiales { color: #d35400; font-weight: bold; }

        /* Estilo modificado para que el enlace parezca botón */
        .btn-select {
            display: inline-block; /* Importante para que tome ancho/alto */
            background-color: #3498db; 
            color: white; 
            text-decoration: none; /* Quita el subrayado del link */
            padding: 10px 20px;
            border-radius: 5px; 
            margin-top: 15px;
            font-weight: bold;
            transition: background 0.3s;
            text-align: center;
        }
        
        .btn-select:hover { background-color: #2980b9; }
    </style>
</head>
<body>

    <?php include '../Includes/HeaderMenuE.php'; ?>

    <div class="container">
        <h2>Puntos de Entrega Cercanos</h2>
        <div id="mapa"></div>

        <h3 class="list-title">Sucursales y Materiales Aceptados</h3>
        
        <div class="branches">
            <?php foreach ($puntos_recogida as $punto): ?>
                <div class="branch-card">
                    <h4><?php echo htmlspecialchars($punto['Name']); ?></h4>
                    
                    <p>
                        <span class="info-label">Se recibe:</span> 
                        <span class="lista-materiales">
                            <?php 
                            if (!empty($punto['MaterialesAceptados'])) {
                                echo htmlspecialchars($punto['MaterialesAceptados']); 
                            } else {
                                echo "Material escolar general";
                            }
                            ?>
                        </span>
                    </p>

                    <p><span class="info-label">Dirección:</span> <?php echo htmlspecialchars($punto['address']); ?></p>
                    
                    <a href="application.php?punto_id=<?php echo $punto['ID_Point']; ?>" class="btn-select">
                        Solicitar
                    </a>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        const map = L.map('mapa').setView([19.4326, -99.1332], 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const sucursales = <?php echo json_encode($puntos_recogida); ?>;

        sucursales.forEach(punto => {
            if(punto.latitud && punto.longitud) {
                let materiales = punto.MaterialesAceptados ? punto.MaterialesAceptados : "Material general";
                
                L.marker([punto.latitud, punto.longitud])
                    .addTo(map)
                    .bindPopup(`
                        <div style="text-align:center;">
                            <b>${punto.Name}</b><br>
                            <span style="color:#d35400; font-size:0.9em;">${materiales}</span><br>
                            <a href="application.php?punto_id=${punto.ID_Point}" style="color:#3498db; text-decoration:none; font-weight:bold;">Ir a Solicitar</a>
                        </div>
                    `);
            }
        });
    </script>
</body>
</html>