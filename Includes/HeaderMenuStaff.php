<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>
<link rel="stylesheet" href="assets/style.css">
<header class="header">
    <div class="container-header">
        <a href="../index.php" class="logo">NextStep</a>
    </div>
    <div class = "menu">
        <li><a href="" class="pagina">Datos personales</a></li>
        <li><a href="" class="pagina">Sucursales</a></li>
        <li><a href="" class="pagina">Inventario</a></li>
        <li><a href="../Staff/logout.php" class="pagina">Cerrar sesión</a></li>
    </div>
</header>