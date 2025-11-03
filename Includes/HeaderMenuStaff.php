<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>
<link rel="stylesheet" href="../assets/IndexStaff.css">

<header class="header">
    <div class="container-header">
        <a href="../index.php" class="logo">NextStep</a>
    </div>

    <ul class="menu">
        <li><a href="#" class="pagina">Datos personales</a></li>

        <!-- Dropdown -->
        <li class="dropdown">
            <a href="#" class="pagina">Becas ▾</a>
            <ul class="dropdown-content">
                <li><a href="#" class="submenu">Inventario</a></li>
                <li><a href="#" class="submenu">Historial de Solicitudes</a></li>
                <li><a href="#" class="submenu">Colaboradores</a></li>
            </ul>
        </li>

        <li><a href="#" class="pagina">Sucursales</a></li>
        <li><a href="../Student/logout.php" class="pagina logout">Cerrar sesión</a></li>
    </ul>
</header>