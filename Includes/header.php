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
    background-color: #007BFF;
    border-bottom: 2px solid #0056b3;
    padding: 1rem 2rem;
    position: sticky;
    top: 0;
    z-index: 1000;
    display: flex;
    justify-content: flex-start;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.header .logo {
    color: #fff;
    font-weight: bold;
    font-size: 1.5rem;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.3s;
}

.header .logo:hover {
    color: #ffdd57;
}
</style>
<header class="header">
    <div class="container-header">
        <a href="../index.php" class="logo">NextStep</a>
    </div>
</header>
