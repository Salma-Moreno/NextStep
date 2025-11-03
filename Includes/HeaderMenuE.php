<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>

<header class="header">
    <div class="container-header">
        <a href="../index.php" class="logo">NextStep</a>
    </div>

    <ul class="menu">
        <li><a href="../Student/perfil.php" class="pagina">Perfil</a></li>

        <!-- Dropdown -->
        <li class="dropdown">
            <a href="#" class="pagina">Becas ▾</a>
            <ul class="dropdown-content">
                <li><a href="../Student/application.php" class="submenu">Solicitud de Beca</a></li>
                <li><a href="../Student/Historical.php" class="submenu">Historial de Solicitudes</a></li>
                <li><a href="../Student/Status.php" class="submenu">Estatus</a></li>
            </ul>
        </li>

        <li><a href="../Student/Delivery.php" class="pagina">Puntos de entrega</a></li>
        <li><a href="../Student/logout.php" class="pagina logout">Cerrar sesión</a></li>
    </ul>
</header>

