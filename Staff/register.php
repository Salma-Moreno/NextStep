<?php
// 1. Iniciar la sesión
session_start();

// 2. Comprobar si el formulario fue enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. Requerir la conexión
    require '../Conexiones/db.php'; // Tu archivo de conexión a la BD

    // 4. Recolectar y limpiar los datos del formulario
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : NULL;
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 5. Validaciones
    if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password)) {
        $_SESSION['error'] = "Por favor, completa todos los campos obligatorios.";
        header('Location: register_staff.php'); 
        exit;
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Las contraseñas no coinciden.";
        header('Location: register_staff.php');
        exit;
    }

    // 6. Cifrar la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 7. Verificar duplicados
    try {
        $stmt = $conn->prepare("SELECT ID_Staff FROM staff WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result(); 
        if ($stmt->num_rows > 0) { 
            $_SESSION['error'] = "El correo electrónico ya está en uso.";
            header('Location: register_staff.php');
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT ID_User FROM user WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result(); 
        if ($stmt->num_rows > 0) { 
            $_SESSION['error'] = "El nombre de usuario ya está en uso.";
            header('Location: register_staff.php');
            exit;
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error'] = "Error al verificar duplicados: " . $e->getMessage();
        header('Location: register_staff.php');
        exit;
    }

    // 8. Definir Rol y Status
    $staff_role_id = 2; // O el ID que corresponda a 'Staff'
    $status = 'Active';

    // 9. Iniciar la transacción
    $conn->begin_transaction();

    try {
        // --- Insertar en 'user' ---
        $sql_user = "INSERT INTO user (FK_ID_Role, Username, Password, Status) VALUES (?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("isss", $staff_role_id, $username, $hashed_password, $status);
        $stmt_user->execute();
        
        $new_user_id = $conn->insert_id;

        // --- Insertar en 'staff' ---
        $sql_staff = "INSERT INTO staff (FK_ID_User, Firstname, Lastname, Email, Phone) VALUES (?, ?, ?, ?, ?)";
        $stmt_staff = $conn->prepare($sql_staff);
        $stmt_staff->bind_param("issss", $new_user_id, $firstname, $lastname, $email, $phone);
        $stmt_staff->execute();

        // --- Confirmar ---
        $conn->commit();

        $_SESSION['success'] = "¡Cuenta de Staff registrada exitosamente! Ahora puedes iniciar sesión.";
        header('Location: ../Staff/StaffLogin.php'); 
        exit;

    } catch (mysqli_sql_exception $e) {
        // --- Revertir ---
        $conn->rollback();
        
        $_SESSION['error'] = "Error al registrar la cuenta: " . $e->getMessage();
        header('Location: ../Staff/register.php');
        exit;

    } finally {
        if (isset($stmt_user)) $stmt_user->close();
        if (isset($stmt_staff)) $stmt_staff->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Staff</title>
    <link rel="stylesheet" href="../assets/register.css"> 
</head>
<body>

    <div class="container">
        <h1>Registro de Staff</h1>
        <p style="text-align: center; color: #666; margin-top: -15px; margin-bottom: 25px;">Crea una nueva cuenta de administrador.</p>

        <?php
        // Mostrar mensaje de error si existe
        if (isset($_SESSION['error'])) {
            echo '<div class="error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        // Mostrar mensaje de éxito si existe
        if (isset($_SESSION['success'])) {
            echo '<div class="success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <form action="" method="POST">
            
            <div class="form-group">
                <label for="firstname">Nombre:</label>
                <input type="text" id="firstname" name="firstname" required>
            </div>
            
            <div class="form-group">
                <label for="lastname">Apellido:</label>
                <input type="text" id="lastname" name="lastname" required>
            </div>

            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="phone">Teléfono (Opcional):</label>
                <input type="tel" id="phone" name="phone">
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="boton">Registrar Cuenta</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            ¿Ya tienes cuenta? <a href="../Staff/StaffLogin.php">Inicia Sesión aquí</a>
        </p>
    </div>

</body>
</html>