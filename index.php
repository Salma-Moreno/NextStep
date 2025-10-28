<?php
include 'Conexiones/db.php';
// Define el título específico para esta página
$pageTitle = 'NextStep - Start';

// Incluye el archivo del encabezado
include 'Includes/header.php';
?>

    <div class="container">
        <h1>¡Welcome!</h1>
        <p>Please, select your role to continue:</p>
        
        <div class="botones">
            <a href="Staff/StaffLogin.php" rel="noopener noreferrer">
                <button class="boton">I'm Staff</button>
            </a>
            <a href="Student/StudentLogin.php" rel="noopener noreferrer">
                <button class="boton">I'm Student</button>
            </a>
        </div>
    </div>

<?php
// Incluye el archivo del pie de página
include 'Includes/footer.php';
?>