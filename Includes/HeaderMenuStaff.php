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
        font-size: 1rem;
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

    /* ZONA DE SEGURIDAD - EVITA QUE DESAPAREZCA */
    .dropdown::before {
        content: '';
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        height: 15px; /* Espacio para mover el cursor */
        background: transparent;
        z-index: 1999;
    }

    /* Mostrar dropdown - FUNCIONA BIEN AHORA */
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
        
        <!-- Dropdown CORREGIDO -->
        <li class="dropdown">
            <a href="#" class="pagina dropdown-toggle">Becas ▾</a>
            <ul class="dropdown-content">
                <li><a href="../Staff/Inventario.php" class="submenu">Inventario</a></li>
                <li><a href="../Staff/HistorialSolicitudes.php" class="submenu">Historial de Solicitudes</a></li>
                <li><a href="../Staff/Company.php" class="submenu">Colaboradores</a></li>
            </ul>
        </li>

        <li><a href="../Staff/Branches.php" class="pagina">Sucursales</a></li>
        <li><a href="../Staff/logout.php" class="pagina logout">Cerrar sesión</a></li>
    </ul>
</header>

<!-- JAVASCRIPT PARA DROPDOWN QUE NO DESAPARECE -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdown = document.querySelector('.dropdown');
    const dropdownContent = dropdown.querySelector('.dropdown-content');
    const dropdownLink = dropdown.querySelector('.dropdown-toggle');
    
    // Variables para controlar el hover
    let isHoveringLink = false;
    let isHoveringMenu = false;
    let closeTimer;
    
    // Función para abrir el dropdown
    function openDropdown() {
        dropdownContent.style.display = 'block';
        dropdown.classList.add('active');
    }
    
    // Función para cerrar el dropdown con retraso
    function closeDropdown() {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            if (!isHoveringLink && !isHoveringMenu) {
                dropdownContent.style.display = 'none';
                dropdown.classList.remove('active');
            }
        }, 200); // 200ms de retraso
    }
    
    // Cancelar el cierre
    function cancelClose() {
        clearTimeout(closeTimer);
    }
    
    // EVENTOS PARA EL ENLACE "BECAS"
    dropdownLink.addEventListener('mouseenter', function() {
        isHoveringLink = true;
        cancelClose();
        openDropdown();
    });
    
    dropdownLink.addEventListener('mouseleave', function() {
        isHoveringLink = false;
        closeDropdown();
    });
    
    // EVENTOS PARA EL DROPDOWN
    dropdownContent.addEventListener('mouseenter', function() {
        isHoveringMenu = true;
        cancelClose();
        openDropdown();
    });
    
    dropdownContent.addEventListener('mouseleave', function() {
        isHoveringMenu = false;
        closeDropdown();
    });
    
    // Para móviles - clic para abrir/cerrar
    dropdownLink.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            e.preventDefault();
            
            if (dropdownContent.style.display === 'block') {
                dropdownContent.style.display = 'none';
                dropdown.classList.remove('active');
            } else {
                dropdownContent.style.display = 'block';
                dropdown.classList.add('active');
            }
        }
    });
    
    // Cerrar al hacer clic fuera (solo en móviles)
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && window.innerWidth <= 768) {
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        }
    });
    
    // Cerrar con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        }
    });
    
    // Asegurar que los enlaces del dropdown funcionen
    dropdownContent.querySelectorAll('.submenu').forEach(link => {
        link.addEventListener('click', function() {
            // El dropdown se cerrará automáticamente al navegar
            dropdownContent.style.display = 'none';
            dropdown.classList.remove('active');
        });
    });
});
</script>