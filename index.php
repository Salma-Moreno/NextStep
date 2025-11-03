<?php
include 'Conexiones/db.php';

// Define el título de la página
$pageTitle = 'NextStep - Start';

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
        <h1>¡Welcome to <span class="brand">NextStep</span>!</h1>
        <p class="subtitle">Please, select your role to continue:</p>

        <div class="botones">
            <a href="Staff/StaffLogin.php" class="boton">I'm Staff</a>
            <a href="Student/StudentLogin.php" class="boton">I'm Student</a>
        </div>
    </div>
</main>

<?php
// Pie de página común
include 'Includes/footer.php';
?>
