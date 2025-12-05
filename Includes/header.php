<?php
// Si la variable $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = 'NextStep';
}
?>
<style>
   /* === Header / Navbar === */
.header {
    width: 100%;
    background-color: #071630;
    border-bottom: 2px solid #0d223f;
    padding: 1rem 2rem;
    position: sticky;
    top: 0;
    z-index: 1000;
    display: flex;
    justify-content: flex-start;
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

</style>
<header class="header">
    <a href="../index.php" class="logo">
            <div class="logo-box">
                <img src="../assets/Imagenes/Logo3.png" alt="Logo NextStep" class="logo-img">
            </div>
            <span>NextStep</span>
        </a>
</header>
