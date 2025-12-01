<?php
// Guardián: Si no hay sesión o el rol no es 'Student', expulsar al login
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'Student') {
    header('Location: StudentLogin.php');
    exit;
}
//Conexion a la base de datos
include '../Conexiones/db.php';
?>
<head>
    <meta charset="UTF-8">
    <title>Panel Estudiante</title>
    <!--Hoja de estilos-->
    <link rel="stylesheet" href="../assets/Student/StudentStyle.css">
    <!--Permite acceder a los iconos utilizados-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
<!--      MENÚ SUPERIOR           -->
<header class="menu-student">

    <div class="menu-left">
        <img src="../assets/Imagenes/Logo3.png" class="logo-img">
    </div>

    <div class="menu-center">
        <span class="student-name">
            <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
        </span>
    </div>

    <div class="menu-right">
        <i class="fa-solid fa-circle-user"></i>
    </div>

</header>
<!--     CONTENIDO PRINCIPAL      -->
<div class="dashboard-container">

    <!-- Sección superior contenedor de bienbenida -->
    <div class="top-section">
        <!-- Parte izquierda del contenedor -->
        <div class="welcome-text">
            <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?> !</h2>
            <p>
                Nextstep es una plataforma sin fines de lucro que brinda materiales
                escolares a estudiantes, ofreciendo un impulso hacia un futuro mejor.
            </p>
        </div>
        <!-- Parte derecha del contenedor -->
        <div class="welcome-img-box">
            <div class="image-mask">
            <img src="../assets/Imagenes/UtilesE.png">
            </div>
        </div>
    </div>
    <!-- Sección inferior con botones-->
    <div class="bottom-section">

        <div class="left-buttons">
        <a href="../Student/perfil.php" class="btn-option">
            <i class="fa-solid fa-user"></i>
            <span>Datos Personales</span>
        </a>

        <a href="../Student/application.php" class="btn-option">
            <i class="fa-solid fa-box"></i>
            <span>Solicitar becas</span>
        </a>

        <a href="../Student/Delivery.php" class="btn-option">
            <i class="fa-solid fa-location-dot"></i>
            <span>Puntos de entrega</span>
        </a>

        <a href="../Student/Status.php" class="btn-option">
            <i class="fa-solid fa-circle-info"></i>
            <span>Estatus de becas solicitadas</span>
        </a>

        <a href="../Student/Historical.php" class="btn-option">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>Historial de solicitudes</span>
        </a>
        </div>
        <!-- Panel con imagen de procesos -->
        <div class="big-content-box">
           <div class="image-left">
            <img src="../assets/Imagenes/Proceso estudiantes.png">
            </div> 
        </div>
    </div>
</div>
</body>
