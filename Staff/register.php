<?php
// 1. Iniciar la sesión
session_start();

// Si el formulario NO fue enviado (al refrescar), limpia las banderas de sesión.
// Esto evita que el formulario muestre datos persistentes si el usuario refresca la página.
/*if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['validation_in_progress_staff']);
    unset($_SESSION['form_data_staff']);
    unset($_SESSION['password_error_staff']);
}*/

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

    // === AÑADIDO: Guardar datos en sesión para persistencia en caso de error ===
    $_SESSION['form_data_staff'] = [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'phone' => $phone,
        'username' => $username
    ];
    $_SESSION['validation_in_progress_staff'] = true;

    // 5. Validaciones
    if (empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password)) {
        $_SESSION['error'] = "Por favor, completa todos los campos obligatorios.";
        header('Location: ../Staff/register.php'); 
        exit;
    }
    //  Validar que nombre y apellido solo contengan letras
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $firstname)) {
        $_SESSION['error'] = "El nombre solo puede contener letras y espacios.";
        header('Location: ../Staff/register.php');
        exit;
    }
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $lastname)) {
        $_SESSION['error'] = "El apellido solo puede contener letras y espacios.";
        header('Location: ../Staff/register.php');
        exit;
    }

    // Validar formato básico de correo
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Por favor, ingresa un correo electrónico válido (ej: usuario@dominio.com).";
        header('Location: ../Staff/register.php');
        exit;
    }

    //Validación de teléfono - SOLO NÚMEROS y 10 dígitos (si se proporciona)
    if (!empty($phone)) {
        // Validar que solo contenga números
        if (!preg_match('/^[0-9]+$/', $phone)) {
            $_SESSION['error'] = "El teléfono solo puede contener números (sin letras, espacios, guiones o signos).";
            header('Location: ../Staff/register.php');
            exit;
        }
        // Validar longitud (10 dígitos)
        if (strlen($phone) !== 10 ) {
            $_SESSION['error'] = "El número de teléfono debe tener exactamente 10 dígitos.";
            header('Location: ../Staff/register.php');
            exit;
        }
    }

    // 5.5 Validaciones de complejidad de contraseña (MEJORA DE SEGURIDAD)
    $password_error = [];
    if (strlen($password) < 8) {
        $password_error[] = "Mínimo 8 caracteres";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $password_error[] = "Al menos una letra mayúscula (A-Z)";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $password_error[] = "Al menos un número (0-9)";
    }
    if (!preg_match('/[^a-zA-Z0-9\s]/', $password)) { 
        $password_error[] = "Al menos un símbolo o carácter especial";
    }

    if (!empty($password_error)) { 
        // Guarda el mensaje de error específico para mostrarlo debajo del campo de contraseña
        $_SESSION['password_error_staff'] = "Debe incluir: " . implode(", ", $password_error) . ".";
        $_SESSION['error'] = "La contraseña no cumple con los requisitos.";
        header('Location: ../Staff/register.php');
        exit;
    }
    
    // Validar coincidencia de contraseñas (después de la validación de complejidad)
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Las contraseñas no coinciden.";
        header('Location: ../Staff/register.php');
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
            header('Location: ../Staff/register.php');
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT ID_User FROM user WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result(); 
        if ($stmt->num_rows > 0) { 
            $_SESSION['error'] = "El nombre de usuario ya está en uso.";
            header('Location: ../Staff/register.php');
            exit;
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error'] = "Error al verificar duplicados: " . $e->getMessage();
        header('Location: ../Staff/register.php');
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
        
        // === AÑADIDO: Limpiar datos de sesión al éxito ===
        unset($_SESSION['validation_in_progress_staff']); 
        unset($_SESSION['form_data_staff']);

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <input type="text" id="firstname" name="firstname" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['firstname'])) ? htmlspecialchars($_SESSION['form_data_staff']['firstname']) : ''; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="lastname">Apellido:</label>
        <input type="text" id="lastname" name="lastname" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['lastname'])) ? htmlspecialchars($_SESSION['form_data_staff']['lastname']) : ''; ?>" required>
    </div>

    <div class="form-group">
        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['email'])) ? htmlspecialchars($_SESSION['form_data_staff']['email']) : ''; ?>" placeholder="ejemplo@dominio.com" required
            onfocus="showHint('email-hint')" onblur="hideHint('email-hint')">
        <small id="email-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
            Asegúrate de ingresar un correo electrónico válido.
        </small>
    </div>

    <div class="form-group">
        <label for="phone">Teléfono (Opcional):</label>
        <input type="tel" id="phone" name="phone" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['phone'])) ? htmlspecialchars($_SESSION['form_data_staff']['phone']) : ''; ?>" placeholder="Ej: 6641234567" pattern="[0-9]*" inputmode="numeric"
            onfocus="showHint('phone-hint')" onblur="hideHint('phone-hint')">
        <small id="phone-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
            Solo números, se requieren exactamente 10 dígitos.
        </small>
    </div>

    <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

    <div class="form-group">
        <label for="username">Nombre de Usuario:</label>
        <input type="text" id="username" name="username" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['username'])) ? htmlspecialchars($_SESSION['form_data_staff']['username']) : ''; ?>" required>
    </div>


        <div class="form-group">
    <label for="password">Contraseña:</label>
    
    <div class="password-wrapper">
        <input type="password" id="password" name="password" required onkeyup="checkPassword()" onfocus="showRequirements()" onblur="hideRequirements()">
        <span class="toggle-password" onclick="togglePasswordIcon('password')">
            <i class="fa-solid fa-eye"></i>
        </span>
    </div>
    <ul id="password-requirements" style="list-style-type: none; padding-left: 10px; margin-top: 5px; font-size: 13px; display: none;">
        <li id="req-length" style="color: red;">✖ Mínimo 8 caracteres</li>
        <li id="req-upper" style="color: red;">✖ Al menos una mayúscula (A-Z)</li>
        <li id="req-number" style="color: red;">✖ Al menos un número (0-9)</li>
        <li id="req-symbol" style="color: red;">✖ Al menos un símbolo o carácter especial</li>
    </ul>

    <?php
    if (isset($_SESSION['password_error_staff'])) {
        echo '<p style="color: red; font-size: 13px; margin-top: 5px;">' . htmlspecialchars($_SESSION['password_error_staff']) . '</p>';
        unset($_SESSION['password_error_staff']); // Limpia el error específico
    }
    ?>
</div>

<div class="form-group">
    <label for="confirm_password">Confirmar Contraseña:</label>
    
    <div class="password-wrapper">
        <input type="password" id="confirm_password" name="confirm_password" required>
        <span class="toggle-password" onclick="togglePasswordIcon('confirm_password')">
            <i class="fa-solid fa-eye"></i>
        </span>
    </div>
</div>

            
            <button type="submit" class="boton">Registrar Cuenta</button>
        </form>
        
        <p style="text-align: center; margin-top: 20px;">
            ¿Ya tienes cuenta? <a href="../Staff/StaffLogin.php">Inicia Sesión aquí</a>
        </p>
    </div>
<script>
    function checkPassword() {
        const password = document.getElementById('password').value;
        // 1. Reglas de validación (deben coincidir con las de tu PHP)
        const lengthMet = (password.length >= 8);
        const upperMet = /[A-Z]/.test(password);
        const numberMet = /[0-9]/.test(password);
        const symbolMet = /[^a-zA-Z0-9\s]/.test(password); 
        // 2. Actualizar el estado visual de cada requisito
        updateRequirement('req-length', lengthMet);
        updateRequirement('req-upper', upperMet);
        updateRequirement('req-number', numberMet);
        updateRequirement('req-symbol', symbolMet);
    }

    function updateRequirement(id, isMet) {
        const element = document.getElementById(id);
        if (element) {
            if (isMet) {
                element.innerHTML = '✔' + element.innerHTML.substring(1); 
                element.style.color = 'green';
            } else {
                element.innerHTML = '✖' + element.innerHTML.substring(1); 
                element.style.color = 'red';
            }
        }
    }

    function showRequirements() {
        // Muestra la lista de requisitos al hacer focus
        document.getElementById('password-requirements').style.display = 'block';
        checkPassword(); 
    }

    function hideRequirements() {
        const password = document.getElementById('password').value;
        // Oculta la lista SOLAMENTE si el campo de contraseña está vacío al salir.
        if (password.length === 0) {
            document.getElementById('password-requirements').style.display = 'none';
        }
    }

    function togglePasswordIcon(id) {
        const input = document.getElementById(id);
        // El span del icono es el siguiente elemento hermano del input
        const iconSpan = input.parentElement.querySelector('.toggle-password'); 

        if (input.type === "password") {
            input.type = "text";
            iconSpan.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
        } else {
            input.type = "password";
            iconSpan.innerHTML = '<i class="fa-solid fa-eye"></i>';
        }
    }

    // Lógica para asegurar que la lista se muestre si hay datos por error de PHP
    document.addEventListener('DOMContentLoaded', () => {
        const passwordInput = document.getElementById('password');
        if (passwordInput && passwordInput.value.length > 0) {
            showRequirements();
        }
    });

    function showHint(id) {
        const hintElement = document.getElementById(id);
        if (hintElement) {
            hintElement.style.display = 'block';
        }
    }

    function hideHint(id) {
        const hintElement = document.getElementById(id);
        if (hintElement) {
            hintElement.style.display = 'none';
        }
    }
</script>

</body>
</html>