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
    background-color: #071630;
    border-bottom: 2px solid #0d223f;
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
    font-size: 1rem;
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
    background-color: #0056b3;
    color: #ffdd57;
}

/* === BOTÓN DE CERRAR SESIÓN === */
.menu .logout {
    background-color: #0c2652ff;
    padding: 8px 15px;
    border-radius: 5px;
    transition: background-color 0.3s;
}

.menu .logout:hover {
    background-color: #2d2b82ff;
    border-bottom: none;
}
</style>
<header class="header">
    <div class="container-header">
        <a href="../Staff/index.php" class="logo">
            <div class="logo-box">
                <img src="../assets/Imagenes/Logo3.png" alt="Logo NextStep" class="logo-img">
            </div>
            <span>NextStep - Staff</span>
        </a>
    </div>

    <ul class="menu">
        <li><a href="../Staff/Perfil.php" class="pagina">Datos personales</a></li>
        <li><a href="../Staff/listStudents.php" class="pagina">Estudiantes</a></li>
        <!-- Dropdown -->
        <li class="dropdown">
            <a href="#" class="pagina">Becas ▾</a>
            <ul class="dropdown-content">
                <li><a href="../Staff/Inventario.php" class="submenu">Inventario</a></li>
                <li><a href="../Staff/HistorialSolicitudes.php" class="submenu">Historial de Solicitudes</a></li>
                <li><a href="../Staff/Company.php" class="submenu">Colaboradores</a>
            </li>
            </ul>
        </li>

        <li><a href="../Staff/Branches.php" class="pagina">Sucursales</a></li>
        <li><a href="../Staff/logout.php" class="pagina logout">Cerrar sesión</a></li>
    </ul>
</header>