<?php
session_start();


//-----Añadi esto
//datos se borren al refrescar y solo se guarden al llenar datos
/*if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    unset($_SESSION['validation_in_progress']);
    unset($_SESSION['form_data']);
}*/


//  NUEVO: Limpiar errores al refrescar la página
if (!isset($_SESSION['error'])) {
    unset($_SESSION['error_fields']);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require '../Conexiones/db.php'; // conexión a la BD


// 1. Recolectar y limpiar datos
$name = trim($_POST['name']);
$last_name = trim($_POST['last_name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$confirm_password = trim($_POST['confirm_password']);

$errors = [];
$error_fields = [];
$password_errors = [];

//no aceptar espacios 
$fields_no_spaces = ['name', 'last_name', 'email', 'phone', 'username', 'password', 'confirm_password'];
foreach ($fields_no_spaces as $field) {
    if (!empty($$field) && preg_match('/\s/', $$field)) {
        $errors[] = ucfirst(str_replace('_', ' ', $field)) . " no puede contener espacios.";
        $error_fields[] = $field;
    }
}
    //Para que no se borren todos los datos si las validaciones fallan
    //Guardar datos en sesión antes de validar
    $_SESSION['form_data'] = [
        'name' => $name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'username' => $username
    ];
    $_SESSION['validation_in_progress'] = true;


   // 2. Array para acumular errores Y campos con error
    $errors = [];
    $password_errors = [];
    $error_fields = []; // Para guardar qué campos tienen error


    // Validaciones básicas de campos vacíos
    if (empty($name)) {
        $errors[] = "El nombre es obligatorio.";
        $error_fields[] = 'name';
    }


    if (empty($last_name)) {
        $errors[] = "El apellido es obligatorio.";
        $error_fields[] = 'last_name';
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


    // Validar que nombre y apellido solo contengan letras
    if (!empty($name) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $name)) {
        $errors[] = "El nombre solo puede contener letras.";
        $error_fields[] = 'name';
    }
   
    if (!empty($last_name) && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $last_name)) {
        $errors[] = "El apellido solo puede contener letras.";
        $error_fields[] = 'last_name';
    }


    // Validación de correo electrónico
    if (!empty($email)) {
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


    // Validación de teléfono
    if (!empty($phone)) {
        if (!preg_match('/^[0-9]+$/', $phone)) {
            $errors[] = "El teléfono solo puede contener números (sin letras, espacios, guiones o signos).";
            $error_fields[] = 'phone';
        }
        if (strlen($phone) !== 10) {
            $errors[] = "El número de teléfono debe tener exactamente 10 dígitos.";
            $error_fields[] = 'phone';
        }
    }


    // Validaciones de contraseña (solo si no está vacía)
    if (!empty($password)) {
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
        $_SESSION['error'] = "Hay " . count($errors) . " campo(s) con error. Por favor revisa los datos ingresados y corrige según lo solicitado.";
        $_SESSION['error_fields'] = $error_fields; // Guardamos los campos con error
        header('Location: register.php');
        exit;
    }


    // CIFRAR CONTRASEÑA - ¡IMPORTANTE!
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);


    //Verificar duplicados
    try {
        // Email duplicado
        $stmt = $conn->prepare("SELECT ID_Student FROM student WHERE Email_Address = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "El correo electrónico ya está en uso.";
            $_SESSION['error_fields'] = ['email'];
            header('Location: register.php');
            exit;
        }
        $stmt->close();


        // Username duplicado
        $username_lower = strtolower($username);
        $stmt = $conn->prepare("SELECT ID_User FROM user WHERE LOWER(Username) = ?");
        $stmt->bind_param("s", $username_lower);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "El nombre de usuario ya está en uso.";
            $_SESSION['error_fields'] = ['username'];
            header('Location: register.php');
            exit;
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error al verificar duplicados: " . $e->getMessage();
        header('Location: register.php');
        exit;
    }




    //Definir rol y status
    $student_role_id = 1; // el ID que corresponde a 'Student'
    $status = 'Active';


    //Transacción
    $conn->begin_transaction();


    try {
        // Insertar en tabla user
        $sql_user = "INSERT INTO user (FK_ID_Role, Username, Password, Status) VALUES (?, ?, ?, ?)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("isss", $student_role_id, $username, $hashed_password, $status);
        $stmt_user->execute();


        $new_user_id = $conn->insert_id;


        // Insertar en tabla student
        $sql_student = "INSERT INTO student (FK_ID_User, Name, Last_Name, Phone_Number, Email_Address) VALUES (?, ?, ?, ?, ?)";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("issss", $new_user_id, $name, $last_name, $phone, $email);
        $stmt_student->execute();


        // Confirmar transacción
        $conn->commit();


            // LIMPIAR DATOS DE SESIÓN CUANDO EL REGISTRO ES EXITOSO
            //corregido
            unset($_SESSION['validation_in_progress']); // <-- ¡Añade esta línea!
        unset($_SESSION['form_data']);
       
        $_SESSION['success'] = "¡Cuenta de estudiante registrada exitosamente! Ahora puedes iniciar sesión.";
        header('Location: StudentLogin.php');
        exit;


    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error al registrar la cuenta: " . $e->getMessage();
        header('Location: register.php');
        exit;


    } finally {
        if (isset($stmt_user)) $stmt_user->close();
        if (isset($stmt_student)) $stmt_student->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Estudiante</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
     
    <link rel="stylesheet" href="../assets/register.css">
</head>
<body>
   
   <div class="container">
        <h1>Registro de Estudiante</h1>
        <p style="text-align: center; color: #666; margin-top: -15px; margin-bottom: 25px;">Crea tu cuenta de estudiante.</p>


        <?php if (isset($_SESSION['error'])): ?>
            <div class="error" style="background: #fff3f3; border: 2px solid #ff6b6b; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #ff6b6b; font-size: 20px;"></i>
                    <strong style="color: #d63031;">Revisa tu información</strong>
                </div>
                <p style="margin: 10px 0 0 0; color: #666;">
                    <?php
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </p>
            </div>
        <?php endif; ?>


        <?php if (isset($_SESSION['success'])): ?>
            <div class="success" style="background: #f0fff4; border: 2px solid #48bb78; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="color: #48bb78; font-size: 20px;"></i>
                    <strong style="color: #2d774a;">¡Éxito!</strong>
                </div>
                <p style="margin: 10px 0 0 0; color: #666;">
                    <?php
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </p>
            </div>
        <?php endif; ?>


        <form action="" method="POST">
            <div class="form-group">
    <label for="name">Nombre:</label>
    <input type="text" id="name" name="name" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['name'])) ? htmlspecialchars($_SESSION['form_data']['name']) : ''; ?>" required pattern="^\S+$" title="No se permiten espacios"
           onfocus="showHint('name-hint')" onblur="hideHint('name-hint')"
           <?php if (isset($_SESSION['error_fields']) && in_array('name', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
    <small id="name-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
        Solo letras y espacios (no se permiten números ni símbolos)
    </small>
</div>
<div class="form-group">
    <label for="last_name">Apellido:</label>
    <input type="text" id="last_name" name="last_name" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['last_name'])) ? htmlspecialchars($_SESSION['form_data']['last_name']) : ''; ?>" required pattern="^\S+$" title="No se permiten espacios"
           onfocus="showHint('lastname-hint')" onblur="hideHint('lastname-hint')"
           <?php if (isset($_SESSION['error_fields']) && in_array('last_name', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
    <small id="lastname-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
        Solo letras y espacios (no se permiten números ni símbolos)
    </small>
</div>
           <!-- =========================================== -->
           <!-- Modifique para email -->
           <!-- =========================================== -->
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="text" id="email" name="email" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['email'])) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" placeholder="ejemplo@universidad.edu.mx" required pattern="^\S+$" title="No se permiten espacios"
                       onfocus="showHint('email-hint')" onblur="hideHint('email-hint')"
                       <?php if (isset($_SESSION['error_fields']) && in_array('email', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                <small id="email-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Usa tu correo institucional o personal válido (debe incluir dominio como .com, .edu.mx, etc.)
                </small>
            </div>


            <div class="form-group">
                <label for="phone">Teléfono (Opcional):</label>
                <input type="tel" id="phone" name="phone" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['phone'])) ? htmlspecialchars($_SESSION['form_data']['phone']) : ''; ?>" placeholder="Ej: 6641234567" pattern="[0-9]*" inputmode="numeric" pattern="^\S+$" title="No se permiten espacios" 
                       onfocus="showHint('phone-hint')" onblur="hideHint('phone-hint')"
                       <?php if (isset($_SESSION['error_fields']) && in_array('phone', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                <small id="phone-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Solo números, exactamente 10 dígitos (sin letras, espacios o signos)
                </small>
            </div>


            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">


<div class="form-group">
    <label for="username">Nombre de Usuario:</label>
    <input type="text" id="username" name="username" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['username'])) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>" required pattern="^\S+$" title="No se permiten espacios" 
           onfocus="showHint('username-hint')" onblur="hideHint('username-hint')"
           <?php if (isset($_SESSION['error_fields']) && in_array('username', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
    <small id="username-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
        Elige un nombre único (puede contener letras, números, guiones y puntos)
    </small>
</div>


 <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required onkeyup="checkPassword()" onfocus="showRequirements()" onblur="hideRequirements()" pattern="^\S+$" title="No se permiten espacios" 
                           <?php if (isset($_SESSION['error_fields']) && in_array('password', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
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
                           <?php if (isset($_SESSION['error_fields']) && in_array('confirm_password', $_SESSION['error_fields'])) echo 'style="border: 2px solid red; background-color: #fff5f5;"'; ?>>
                    <span class="toggle-password" onclick="togglePasswordIcon('confirm_password')">
                        <i class="fa-solid fa-eye"></i>
                    </span>
                </div>
                <small id="confirm-hint" style="color: #666; font-size: 12px; display: none; margin-top: 5px;">
                    Debe ser exactamente igual a la contraseña que escribiste arriba
                </small>
                <!-- Mensaje de coincidencia -->
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
            ¿Ya tienes cuenta? <a href="StudentLogin.php">Inicia Sesión aquí</a>
        </p>
    </div>


    <script>
    function checkPassword() {
        const password = document.getElementById('password').value;
        //para validar la contraseña campo por campo y cambie de color segun se cumpla
        //solo cuando el cursor esta en ese campo aparece
        // 1. Reglas de validación (deben coincidir con las de tu PHP)
        const lengthMet = (password.length >= 8);
        const upperMet = /[A-Z]/.test(password);
        const numberMet = /[0-9]/.test(password);
        // Símbolo: Cualquier cosa que NO sea letra, número o espacio
        const symbolMet = /[^a-zA-Z0-9\s]/.test(password);
        // 2. Actualizar el estado visual de cada requisito
        updateRequirement('req-length', lengthMet);
        updateRequirement('req-upper', upperMet);
        updateRequirement('req-number', numberMet);
        updateRequirement('req-symbol', symbolMet);
    }


    function updateRequirement(id, isMet) {
        const element = document.getElementById(id);
        if (element) { // Comprobación de seguridad
            if (isMet) {
                // Requisito cumplido: Poner marca de verificación y color verde
                element.innerHTML = '✔' + element.innerHTML.substring(1);
                element.style.color = 'green';
            } else {
                // Requisito fallido: Poner cruz y color rojo
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
    const iconSpan = input.nextElementSibling;


    if (input.type === "password") {
        input.type = "text";
        iconSpan.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    } else {
        input.type = "password";
        iconSpan.innerHTML = '<i class="fa-solid fa-eye"></i>';
    }
}




    // Lógica para asegurar que la lista se muestre si hay datos  por error de PHP
    const initialPassword = document.getElementById('password').value;
    if (initialPassword.length > 0) {
        showRequirements();
    }


    // Agrega estas funciones al final de tu bloque <script>


function showHint(id) {
    // Muestra el elemento de ayuda por su ID
    const hintElement = document.getElementById(id);
    if (hintElement) {
        hintElement.style.display = 'block';
    }
}


function hideHint(id) {
    // Oculta el elemento de ayuda por su ID
    const hintElement = document.getElementById(id);
    if (hintElement) {
        // En este caso, lo ocultamos inmediatamente al salir (a diferencia de la contraseña)
        hintElement.style.display = 'none';
    }
}


// Función para verificar si las contraseñas coinciden
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


// También verificar cuando se escribe en la contraseña principal
document.getElementById('password').addEventListener('keyup', checkPasswordMatch);






    </script>




</body>
</html>
<?php
unset($_SESSION['validation_in_progress']); //bandera conservar datos en validaciones
?>

