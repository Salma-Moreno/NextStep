<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>
<style>
    /* === HEADER BASE === */
.header {
    width: 100%;
    background-color: #071630;   /* Azul marino */
    border-bottom: 2px solid #0d223f; /* Un poco más claro */
    padding: 1rem 2rem;
    position: sticky;
    top: 0;
    z-index: 1000;

    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* === LOGO === */
.header .logo {
    color: #fff;
    font-weight: bold;
    font-size: 1.7rem;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.3s;

    /* AÑADIDOS para alinear logo + texto */
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
/* Ajustar tamaño del logo dentro de la caja */
.logo-img {
    height: auto;      
    width: 80px;
    display: block;
}
.header .logo:hover {
    color: #ffdd57;
}

/* === MENÚ PRINCIPAL === */
.menu {
    display: flex;
    list-style: none;
    gap: 2rem;
    margin: 0;
    padding: 0;
}

.menu li {
    position: relative; /* Necesario para dropdown */
}

.menu .pagina {
    color: #fff;
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: 500;
    transition: color 0.3s, border-bottom 0.3s;
    padding-bottom: 4px;
}

.menu .pagina:hover {
    color: #ffdd57;
    border-bottom: 2px solid #ffdd57;
}

/* === DROPDOWN === */
.dropdown-content {
    display: none;
    position: absolute;
    top: 35px;
    left: 0;
    background-color: #6D86A4;
    border-radius: 6px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    list-style: none;
    padding: 0.5rem 0;
    min-width: 180px;
    z-index: 2000;
}

/* Mostrar el menú al pasar el cursor */
.dropdown:hover .dropdown-content {
    display: block;
}

/* Estilo de los enlaces dentro del dropdown */
.dropdown-content .submenu {
    display: block;
    padding: 10px 15px;
    color: #fff;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background-color 0.3s, color 0.3s;
}

.dropdown-content .submenu:hover {
    background-color: #556C85;
    color: #ffdd57;
}

/* === BOTÓN DE CERRAR SESIÓN === */
.menu .logout {
    background-color: #ff4444;
    padding: 8px 15px;
    border-radius: 5px;
    transition: background-color 0.3s;
}

.menu .logout:hover {
    background-color: #cc0000;
    border-bottom: none;
}
</style>
<header class="header">
    <div class="container-header">
        <a href="../Student/index.php" class="logo">
            <div class="logo-box">
                <img src="../../img/Logo3.png" alt="Logo NextStep" class="logo-img">
            </div>
            <span>NextStep</span>
        </a>
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