<?php
include 'Conexiones/db.php';

// Define el título de la página
$pageTitle = 'NextStep - Inicio';
// Contar estudiantes
$sql = "SELECT COUNT(*) AS total FROM student";
$result = $conn->query($sql);
$total_estudiantes = $result->fetch_assoc()['total'];

// Contar ubicaciones
$sql = "SELECT COUNT(*) AS total FROM collection_point";
$result = $conn->query($sql);
$total_ubicaciones = $result->fetch_assoc()['total'];

// Contar empresas
$sql = "SELECT COUNT(*) AS total FROM company";
$result = $conn->query($sql);
$total_empresas = $result->fetch_assoc()['total'];
// Encabezado común
?>
<head>
    <meta charset="UTF-8">
    <title>NextStep</title>
    <!--Permite acceder a los iconos utilizados -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Hoja de estilos utilizados-->
    <link rel="stylesheet" href="./assets/PPrincipal.css">
</head>
<body>

<div class="main-container">
    <!-- Top Container/ Parte superior de la página-->
    <div class="top-container">
        <div class="logo-wrapper">
            <img src="./assets/Imagenes/Logo.png" alt="Logo" class="logo-img">
        </div>

        <div class="middle-wrapper">
            <div class="title">¡Bienvenido a NextStep!</div>
            <div class="subtitle">"Impulsando mentes Jóvenes"</div>
        </div>
        <!-- Botones superiores-->
        <div class="button-wrapper">
            <button class="menu-btn" onclick="window.location.href='Student/StudentLogin.php'">Soy alumno</button>
            <button class="menu-btn" onclick="window.location.href='Staff/StaffLogin.php'">Soy empleado</button>
        </div>
    </div>

    <!-- Flip Cards -->
    <div class="paneles">
        <!-- Misión -->
        <div class="flip-card auto-rotate-1">
            <div class="flip-card-inner">
                <!--Parte delantera del panel/carta (muestra la imagen)-->
                <div class="flip-card-front"></div>
                <!--Parte trasera del panel/carta (muestra el texto)-->
                <div class="flip-card-back">
                    <div class="back-content">
                        <div class="back-header">
                            <i class="fa-solid fa-bullseye"></i>
                            <span class="subtitle-card">Misión</span>
                        </div>
                        <div class="back-text">
                            NextStep tiene como misión garantizar que los estudiantes de preparatoria
                            en situación vulnerable puedan acceder a materiales escolares esenciales
                            para su desarrollo académico. La plataforma busca conectar de manera
                            eficiente a donantes comprometidos con jóvenes que requieren apoyo real.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visión -->
        <div class="flip-card auto-rotate-2">
            <div class="flip-card-inner">
                <!--Parte delantera del panel/carta (muestra la imagen)-->
                <div class="flip-card-front"></div>
                <!--Parte trasera del panel/carta (muestra el texto)-->
                <div class="flip-card-back">
                    <div class="back-content">
                        <div class="back-header">
                            <i class="fa-solid fa-eye"></i>
                            <span class="subtitle-card">Visión</span>
                        </div>
                        <div class="back-text">
                            La visión de NextStep es consolidarse como la plataforma líder de apoyo
                            educativo a nivel nacional. Se proyecta evolucionar hacia un ecosistema
                            integral que no solo gestione donaciones, sino también becas, recursos digitales
                            y orientación académica.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alcance -->
        <div class="flip-card auto-rotate-3">
            <div class="flip-card-inner">
                <!--Parte delantera del panel/carta (muestra la imagen)-->
                <div class="flip-card-front"></div>
                <!--Parte trasera del panel/carta (muestra el texto)-->
                <div class="flip-card-back">
                    <div class="back-content">
                        <div class="back-header">
                            <i class="fa-solid fa-chart-line"></i>
                            <span class="subtitle-card">Alcance</span>
                        </div>
                        <div class="back-text">
                            El alcance de NextStep contempla la gestión completa, la centralización de
                            solicitudes, aprobaciones y reportes para una operación ordenada y auditable.
                            Considera la interacción entre estudiantes, donantes y administradores en un
                            entorno seguro y confiable.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Sobre nosotros -->
        <div class="flip-card auto-rotate-4">
            <div class="flip-card-inner">
                <!--Parte delantera del panel/carta (muestra la imagen)-->
                <div class="flip-card-front"></div>
                <!--Parte trasera del panel/carta (muestra el texto)-->
                <div class="flip-card-back">
                    <div class="back-content">
                        <div class="back-header">
                            <i class="fa-solid fa-users"></i>
                            <span class="subtitle-card">Sobre nosotros</span>
                        </div>
                        <div class="back-text">
                            NextStep es una iniciativa social que busca mejorar las condiciones educativas
                            de estudiantes con recursos limitados. Su compromiso es generar un impacto real 
                            mediante un sistema transparente y eficiente de gestión de donaciones escolares.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Section -->
    <div class="info-section">
        <div class="info-box">
            <div class="num-icon">
                <!-- Función con php que muestra el total de alumnos de la base de datos-->
                <strong class="big-number"><?= $total_estudiantes ?></strong>
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="desc">Estudiantes</div>
        </div>
        <div class="info-box">
            <div class="num-icon">
                <!-- Función con php que muestra el total de ubicaciones de la base de datos-->
                <strong class="big-number"><?= $total_ubicaciones ?></strong>
                <i class="fa-solid fa-location-dot"></i>
            </div>
            <div class="desc">Ubicaciones</div>
        </div>
        <div class="info-box">
            <div class="num-icon">
                <!-- Función con php que muestra el total de ubicaciones de la base de datos-->
                <strong class="big-number"><?= $total_empresas ?></strong>
                <i class="fa-solid fa-building"></i>
            </div>
            <div class="desc">Empresas</div>
        </div>
    </div>
</div>
<!-- Script: detener animación al hacer click -->
<script>
    document.querySelectorAll('.flip-card').forEach(card => {
        card.addEventListener('click', () => {
            card.classList.toggle('paused');
        });
    });
</script>
</body>
<?php
// Pie de página común
include 'Includes/footer.php';
?>
