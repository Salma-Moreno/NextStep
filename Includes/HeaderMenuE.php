<?php
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
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
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
        position: relative;
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

    /* === DROPDOWN CORREGIDO === */
    .dropdown-content {
        display: none;
        position: absolute;
        top: 100%; /* Cambiado de 35px a 100% */
        left: 0;
        background-color: #6D86A4;
        border-radius: 6px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        list-style: none;
        padding: 0.5rem 0;
        min-width: 180px;
        z-index: 2000;
        margin-top: 5px; /* Espacio entre botón y dropdown */
    }

    /* ZONA DE SEGURIDAD CRÍTICA */
    .dropdown::before {
        content: '';
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        height: 15px; /* Zona para mover el cursor */
        background: transparent;
        z-index: 1999;
    }

    /* Mostrar dropdown - SOLUCIÓN DEFINITIVA */
    .dropdown:hover .dropdown-content,
    .dropdown:focus-within .dropdown-content {
        display: block;
    }

    /* Mantener visible cuando el cursor está sobre el dropdown */
    .dropdown-content:hover {
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
        position: relative;
        z-index: 2001;
    }

    .dropdown-content .submenu:hover {
        background-color: #556C85;
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

    /* Para móviles - clic en lugar de hover */
    @media (max-width: 768px) {
        .dropdown-content {
            position: static;
            display: none;
            box-shadow: none;
            background-color: #556C85;
            margin-top: 5px;
            margin-left: 20px;
        }
        
        .dropdown.active .dropdown-content {
            display: block;
        }
        
        .dropdown::before {
            display: none; /* No necesaria en móvil */
        }
    }
</style>

<header class="header">
    <div class="container-header">
        <a href="../Student/index.php" class="logo">
            <div class="logo-box">
                <img src="../assets/Imagenes/Logo3.png" alt="Logo NextStep" class="logo-img">
            </div>
            <span>NextStep</span>
        </a>
    </div>

    <ul class="menu">
        <li><a href="../Student/perfil.php" class="pagina">Perfil</a></li>

        <!-- Dropdown -->
        <li class="dropdown">
            <a href="#" class="pagina dropdown-toggle">Becas ▾</a>
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

<!-- JAVASCRIPT SOLUCIÓN DEFINITIVA -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.querySelector('.dropdown');
    const dropdownContent = dropdown.querySelector('.dropdown-content');
    const dropdownLink = dropdown.querySelector('.dropdown-toggle');
    
    // Variables para control
    let isHovering = false;
    let isMouseOverDropdown = false;
    let closeTimeout;
    
    // Función para abrir dropdown
    function openDropdown() {
        dropdownContent.style.display = 'block';
        dropdown.classList.add('active');
    }
    
    // Función para cerrar dropdown con retraso
    function closeDropdown() {
        closeTimeout = setTimeout(() => {
            if (!isHovering && !isMouseOverDropdown) {
                dropdownContent.style.display = 'none';
                dropdown.classList.remove('active');
            }
        }, 300); // 300ms de retraso
    }
    
    // Cancelar cierre
    function cancelClose() {
        clearTimeout(closeTimeout);
    }
    
    // Eventos para el enlace "Becas"
    dropdownLink.addEventListener('mouseenter', function() {
        isHovering = true;
        cancelClose();
        openDropdown();
    });
    
    dropdownLink.addEventListener('mouseleave', function() {
        isHovering = false;
        closeDropdown();
    });
    
    // Eventos para el dropdown
    dropdownContent.addEventListener('mouseenter', function() {
        isMouseOverDropdown = true;
        cancelClose();
        openDropdown();
    });
    
    dropdownContent.addEventListener('mouseleave', function() {
        isMouseOverDropdown = false;
        closeDropdown();
    });
    
    // Para dispositivos táctiles/móviles
    dropdownLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (window.innerWidth <= 768) {
            // En móviles, alternar con clic
            if (dropdownContent.style.display === 'block') {
                dropdownContent.style.display = 'none';
                dropdown.classList.remove('active');
            } else {
                dropdownContent.style.display = 'block';
                dropdown.classList.add('active');
            }
        }
    });
    
    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && window.innerWidth <= 768) {
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        }
    });
    
    // Cerrar al presionar Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        }
    });
    
    // Asegurar que los clics en los enlaces funcionen
    dropdownContent.querySelectorAll('.submenu').forEach(link => {
        link.addEventListener('click', function() {
            // Navegará normalmente, el dropdown se cerrará automáticamente
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        });
    });
});
</script>