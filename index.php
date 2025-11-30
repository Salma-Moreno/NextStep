<?php
include 'Conexiones/db.php';

// Define el título de la página
$pageTitle = 'NextStep - Inicio';

// Encabezado común
?>
<link rel="stylesheet" href="assets/style.css">
<header class="header">
    <div class="container-header">
        <p class="logo">NextStep</p>
    </div>
</header>
<main class="main-container">
    <div class="welcome-box">
        <h1>¡Bienvenido a <span class="brand">NextStep</span>!</h1>
        <p class="subtitle">Por favor, selecciona tu rol para continuar:</p>

        <div class="botones">
            <a href="Staff/StaffLogin.php" class="boton">Soy Empleado</a>
            <a href="Student/StudentLogin.php" class="boton">Soy Estudiante</a>
        </div>
    </div>
</main>

<?php
// Pie de página común
include 'Includes/footer.php';
?>
