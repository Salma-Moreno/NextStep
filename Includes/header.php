<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>