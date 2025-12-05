<?php
session_start();

//  NUEVO: Limpiar errores al refrescar la página
if (!isset($_SESSION['error_staff'])) {
    unset($_SESSION['error_fields_staff']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require '../Conexiones/db.php'; // conexión a la BD

    // 1. Recolectar y limpiar datos
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // SOLO UNA DECLARACIÓN DE ARRAYS
    $errors = [];
    $error_fields = [];
    $password_errors = [];

    // Guardar datos en sesión antes de validar
    $_SESSION['form_data_staff'] = [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'phone' => $phone,
        'username' => $username
    ];
    $_SESSION['validation_in_progress_staff'] = true;

    // ========== VALIDACIONES ==========

    // 1. Validar campos vacíos
    if (empty($firstname)) {
        $errors[] = "El nombre es obligatorio.";
        $error_fields[] = 'firstname';
    }

    if (empty($lastname)) {
        $errors[] = "El apellido es obligatorio.";
        $error_fields[] = 'lastname';
    }

    if (empty($email)) {
        $errors[] = "El correo electrónico es obligatorio.";
        $error_fields[] = 'email';
    }

    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio.";
        $error_fields[] = 'username';
    }

    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria.";
        $error_fields[] = 'password';
    }

    // 2. Validar que no contengan espacios
    $fields_no_spaces = ['email', 'phone', 'username', 'password', 'confirm_password'];
    foreach ($fields_no_spaces as $field) {
        if (!empty($$field) && preg_match('/\s/', $$field)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " no puede contener espacios.";
            if (!in_array($field, $error_fields)) {
                $error_fields[] = $field;
            }
        }
    }

// 3. Validar que nombre y apellido solo contengan letras y espacios
if (!empty($firstname)) {
    // Como ya aplicamos trim(), si $firstname está vacío significa que solo tenía espacios
    if ($firstname === '') {
        $errors[] = "El nombre no puede contener solo espacios.";
        $error_fields[] = 'firstname';
    }
    // Validar que solo contenga letras y espacios
    else if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $firstname)) {
        $errors[] = "El nombre solo puede contener letras y espacios.";
        $error_fields[] = 'firstname';
    }
}

if (!empty($lastname)) {
    // Como ya aplicamos trim(), si $lastname está vacío significa que solo tenía espacios
    if ($lastname === '') {
        $errors[] = "El apellido no puede contener solo espacios.";
        $error_fields[] = 'lastname';
    }
    // Validar que solo contenga letras y espacios
    else if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $lastname)) {
        $errors[] = "El apellido solo puede contener letras y espacios.";
        $error_fields[] = 'lastname';
    }
}

    // 4. Validación de correo electrónico (solo si no está vacío y no tiene espacios)
    if (!empty($email) && !in_array('email', $error_fields)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Por favor, ingresa un correo electrónico válido con dominio (ej: usuario@dominio.com).";
            $error_fields[] = 'email';
        } else {
            // Validar dominio permitido
            $email_parts = explode('@', $email);
            $domain = strtolower(end($email_parts));
            $allowed_domains = [
                'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'live.com',
                'msn.com', 'icloud.com', 'tecnm.mx', 'tectijuana.edu.mx', 'uabc.mx',
                'unam.mx', 'ipn.mx', 'udg.mx', 'uanl.mx',
            ];
           
            if (!in_array($domain, $allowed_domains)) {
                $errors[] = "El dominio de correo electrónico ('@" . htmlspecialchars($domain) . "') no está en la lista de dominios autorizados.";
                $error_fields[] = 'email';
            }
        }
    }

    // 5. Validación de teléfono (solo si no está vacío y no tiene espacios)
    if (!empty($phone) && !in_array('phone', $error_fields)) {
        if (!preg_match('/^[0-9]+$/', $phone)) {
            $errors[] = "El teléfono solo puede contener números (sin letras, espacios, guiones o signos).";
            $error_fields[] = 'phone';
        }
        if (strlen($phone) !== 10) {
            $errors[] = "El número de teléfono debe tener exactamente 10 dígitos.";
            $error_fields[] = 'phone';
        }
    }

    // 6. Validaciones de contraseña (solo si no está vacía y no tiene espacios)
    if (!empty($password) && !in_array('password', $error_fields)) {
        if (strlen($password) < 8) {
            $password_errors[] = "Mínimo 8 caracteres";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $password_errors[] = "Al menos una letra mayúscula (A-Z)";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $password_errors[] = "Al menos un número (0-9)";
        }
        if (!preg_match('/[^a-zA-Z0-9\s]/', $password)) {
            $password_errors[] = "Al menos un símbolo o carácter especial";
        }
       
        // Si hay errores específicos de contraseña, los agregamos al array general
        if (!empty($password_errors)) {
            $errors[] = "La contraseña debe incluir: " . implode(", ", $password_errors) . ".";
            $error_fields[] = 'password';
        }
       
        // Validar que las contraseñas coincidan
        if ($password !== $confirm_password) {
            $errors[] = "Las contraseñas no coinciden.";
            $error_fields[] = 'password';
            $error_fields[] = 'confirm_password';
        }
    }

    // Si hay errores, los mostramos todos juntos
    if (!empty($errors)) {
        $_SESSION['error_staff'] = "Hay " . count($errors) . " campo(s) con error. Por favor revisa los datos ingresados y corrige según lo solicitado.";
        $_SESSION['error_fields_staff'] = array_unique($error_fields); // Eliminar duplicados
        header('Location: ../Staff/register.php');
        exit;
    }

    // ... el resto del código (verificación de duplicados, inserción en BD, etc.)
    // CIFRAR CONTRASEÑA - ¡IMPORTANTE!
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
// Verificar si el correo ya existe en students o staff
$stmt = $conn->prepare("
    SELECT Email_Address FROM student WHERE Email_Address = ?
    UNION
    SELECT Email FROM staff WHERE Email = ?
");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error_staff'] = "El correo electrónico ya está en uso.";
    $_SESSION['error_fields_staff'] = ['email'];
    header('Location: ../Staff/register.php');
    exit;
}
$stmt->close();

    // Verificar username duplicado en staff (igual que antes)
$username_lower = strtolower($username); // <-- esto faltaba
$stmt = $conn->prepare("SELECT ID_User FROM user WHERE LOWER(Username) = ?");
$stmt->bind_param("s", $username_lower);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $_SESSION['error_staff'] = "El nombre de usuario ya está en uso.";
    $_SESSION['error_fields_staff'] = ['username'];
    header('Location: ../Staff/register.php');
    exit;
}
$stmt->close();

    //Definir rol y status
    $staff_role_id = 2; // el ID que corresponde a 'Staff'
    $status = 'Active';

    //Transacción
    $conn->begin_transaction();

    try {
        // Insertar en tabla user
        $sql_user = "INSERT INTO user (FK_ID_Role, Username, Password, Status) VALUES (?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("isss", $staff_role_id, $username, $hashed_password, $status);
        $stmt_user->execute();

        $new_user_id = $conn->insert_id;

        // Insertar en tabla staff
        $sql_staff = "INSERT INTO staff (FK_ID_User, Firstname, Lastname, Email, Phone) VALUES (?, ?, ?, ?, ?)";
        $stmt_staff = $conn->prepare($sql_staff);
        $stmt_staff->bind_param("issss", $new_user_id, $firstname, $lastname, $email, $phone);
        $stmt_staff->execute();

        // Confirmar transacción
        $conn->commit();

        // LIMPIAR DATOS DE SESIÓN CUANDO EL REGISTRO ES EXITOSO
        unset($_SESSION['validation_in_progress_staff']);
        unset($_SESSION['form_data_staff']);
       
        $_SESSION['success_staff'] = "¡Cuenta de staff registrada exitosamente! Ahora puedes iniciar sesión.";
        header('Location: ../Staff/StaffLogin.php');
        exit;

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['error_staff'] = "Error al registrar la cuenta: " . $e->getMessage();
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
    <title>Registro de Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/register.css">
</head>
<body>
   
   <div class="container">
        <h1>Registro de Staff</h1>
        <p style="text-align: center; color: #666; margin-top: -15px; margin-bottom: 25px;">Crea tu cuenta de staff.</p>

        <?php if (isset($_SESSION['error_staff'])): ?>
            <div class="error" style="background: #fff3f3; border: 2px solid #ff6b6b; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ff6b6b; font-size: 20px;"></i>
                    <strong style="color: #d63031;">Revisa tu información</strong>
                </div>
                <p style="margin: 10px 0 0 0; color: #666;">
                    <?php
                    echo $_SESSION['error_staff'];
                    unset($_SESSION['error_staff']);
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_staff'])): ?>
            <div class="success" style="background: #f0fff4; border: 2px solid #48bb78; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="color: #48bb78; font-size: 20px;"></i>
                    <strong style="color: #2d774a;">¡Éxito!</strong>
                </div>
                <p style="margin: 10px 0 0 0; color: #666;">
                    <?php
                    echo $_SESSION['success_staff'];
                    unset($_SESSION['success_staff']);
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="firstname">Nombre:</label>
                <input type="text" id="firstname" name="firstname" 
       value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['firstname'])) ? htmlspecialchars($_SESSION['form_data_staff']['firstname']) : ''; ?>" 
       required 
       pattern="^(?!\s+$)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" 
       title="Solo letras y espacios, pero no solo espacios"
       onfocus="showHint('firstname-hint')" 
       onblur="hideHint('firstname-hint')"
       <?php if (isset($_SESSION['error_fields_staff']) && in_array('firstname', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
<small id="firstname-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
    Solo letras y espacios (no se permiten números ni símbolos, y no puede ser solo espacios)
</small>
            </div>

            <div class="form-group">
                <label for="lastname">Apellido:</label>
                <input type="text" id="lastname" name="lastname" 
       value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['lastname'])) ? htmlspecialchars($_SESSION['form_data_staff']['lastname']) : ''; ?>" 
       required 
       pattern="^(?!\s+$)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$" 
       title="Solo letras y espacios, pero no solo espacios"
       onfocus="showHint('lastname-hint')" 
       onblur="hideHint('lastname-hint')"
       <?php if (isset($_SESSION['error_fields_staff']) && in_array('lastname', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
<small id="lastname-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
    Solo letras y espacios (no se permiten números ni símbolos, y no puede ser solo espacios)
</small>
            </div>
           
            <div class="form-group">
                <label for="text">Correo Electrónico:</label>
                <input type="text" id="email" name="email" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['email'])) ? htmlspecialchars($_SESSION['form_data_staff']['email']) : ''; ?>" placeholder="ejemplo@universidad.edu.mx"
                       onfocus="showHint('email-hint')" onblur="hideHint('email-hint')"
                       <?php if (isset($_SESSION['error_fields_staff']) && in_array('email', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                <small id="email-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Usa tu correo institucional o personal válido (debe incluir dominio como .com, .edu.mx, etc.)
                </small>
            </div>

            <div class="form-group">
                <label for="phone">Teléfono (Opcional):</label>
                <input type="tel" id="phone" name="phone" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['phone'])) ? htmlspecialchars($_SESSION['form_data_staff']['phone']) : ''; ?>" placeholder="Ej: 6641234567" pattern="[0-9]*" inputmode="numeric" 
                       onfocus="showHint('phone-hint')" onblur="hideHint('phone-hint')"
                       <?php if (isset($_SESSION['error_fields_staff']) && in_array('phone', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                <small id="phone-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Solo números, exactamente 10 dígitos (sin letras, espacios o signos)
                </small>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" value="<?php echo (isset($_SESSION['validation_in_progress_staff']) && isset($_SESSION['form_data_staff']['username'])) ? htmlspecialchars($_SESSION['form_data_staff']['username']) : ''; ?>" required pattern="^\S+$" title="No se permiten espacios" 
                       onfocus="showHint('username-hint')" onblur="hideHint('username-hint')"
                       <?php if (isset($_SESSION['error_fields_staff']) && in_array('username', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                <small id="username-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Elige un nombre único (puede contener letras, números, guiones y puntos)
                </small>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required onkeyup="checkPassword()" onfocus="showRequirements()" onblur="hideRequirements()" pattern="^\S+$" title="No se permiten espacios" 
                           <?php if (isset($_SESSION['error_fields_staff']) && in_array('password', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
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
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required pattern="^\S+$" title="No se permiten espacios" 
                           onfocus="showHint('confirm-hint')" onblur="hideHint('confirm-hint')"
                           onkeyup="checkPasswordMatch()"
                           <?php if (isset($_SESSION['error_fields_staff']) && in_array('confirm_password', $_SESSION['error_fields_staff'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                    <span class="toggle-password" onclick="togglePasswordIcon('confirm_password')">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
                <small id="confirm-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Debe ser exactamente igual a la contraseña que escribiste arriba
                </small>
                <small id="password-match-error" style="color: red; font-size: 12px; display: none; margin-top: 5px;">
                    ❌ Las contraseñas no coinciden
                </small>
                <small id="password-match-success" style="color: green; font-size: 12px; display: none; margin-top: 5px;">
                    ✅ Las contraseñas coinciden
                </small>
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
        const lengthMet = (password.length >= 8);
        const upperMet = /[A-Z]/.test(password);
        const numberMet = /[0-9]/.test(password);
        const symbolMet = /[^a-zA-Z0-9\s]/.test(password);
        
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
        document.getElementById('password-requirements').style.display = 'block';
        checkPassword();
    }

    function hideRequirements() {
        const password = document.getElementById('password').value;
        if (password.length === 0) {
            document.getElementById('password-requirements').style.display = 'none';
        }
    }

    function togglePasswordIcon(id) {
        const input = document.getElementById(id);
        const iconSpan = input.nextElementSibling;

        if (input.type === "password") {
            input.type = "text";
            iconSpan.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
        } else {
            input.type = "password";
            iconSpan.innerHTML = '<i class="fa-solid fa-eye"></i>';
        }
    }

    const initialPassword = document.getElementById('password').value;
    if (initialPassword.length > 0) {
        showRequirements();
    }

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

    function checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const errorElement = document.getElementById('password-match-error');
        const successElement = document.getElementById('password-match-success');
       
        if (password.length > 0 && confirmPassword.length > 0) {
            if (password === confirmPassword) {
                errorElement.style.display = 'none';
                successElement.style.display = 'block';
            } else {
                errorElement.style.display = 'block';
                successElement.style.display = 'none';
            }
        } else {
            errorElement.style.display = 'none';
            successElement.style.display = 'none';
        }
    }

    document.getElementById('password').addEventListener('keyup', checkPasswordMatch);
    </script>

</body>
</html>
<?php
unset($_SESSION['validation_in_progress_staff']);
?>