<?php
session_start();

//-----Añadi esto
if (!isset($_SESSION['validation_in_progress'])) {
    unset($_SESSION['form_data']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    require '../Conexiones/db.php'; // conexión a la BD

    // 1. Recolectar y limpiar datos
    $name = $_POST['name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : NULL;
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    //Añadido
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

    // 2. Validaciones básicas
    if (empty($name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $_SESSION['error'] = "Por favor, completa todos los campos obligatorios.";
        header('Location: register.php');
        exit;
    }

     //  Validar que nombre y apellido solo contengan letras
    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $name)) {
        $_SESSION['error'] = "El nombre solo puede contener letras y espacios.";
        header('Location: register.php');
        exit;
    }

    if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $last_name)) {
        $_SESSION['error'] = "El apellido solo puede contener letras y espacios.";
        header('Location: register.php');
        exit;
    }
    
    //-------Añadi validacion para correo .com etc
    // En la sección de validaciones, después de verificar campos vacíos
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Por favor, ingresa un correo electrónico válido con dominio (ej: usuario@dominio.com).";
        header('Location: register.php');
        exit;
    }

    // Validar que el correo tenga un dominio con extensión válida
          if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|mx|edu\.mx|tecnm\.mx|org|gob\.mx|net|info|edu|ac\.mx|unam\.mx|ipn\.mx|uabc\.mx|udg\.mx|uanl\.mx)$/', $email)) {
        $_SESSION['error'] = "El correo electrónico debe incluir un dominio válido (ej: .com, .edu.mx, .tecnm.mx, .org, etc.).";
        header('Location: register.php');
        exit;
    }


       // Validación de teléfono - SOLO NÚMEROS
    if (!empty($phone)) {
        // Validar que solo contenga números
        if (!preg_match('/^[0-9]+$/', $phone)) {
            $_SESSION['error'] = "El teléfono solo puede contener números (sin letras, espacios, guiones o signos).";
            header('Location: register.php');
            exit;
        }
        
        // Validar longitud (10 dígitos)
        if (strlen($phone) !== 10 ) { // <--- CAMBIO AQUÍ
        $_SESSION['error'] = "El número de teléfono debe tener exactamente 10 dígitos."; // <--- CAMBIO EN EL MENSAJE
        header('Location: register.php');
            exit;
        }
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Las contraseñas no coinciden.";
        header('Location: register.php');
        exit;
    }

    // 3. Cifrar contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 4. Verificar duplicados
    try {
        // Email duplicado en tabla student
        $stmt = $conn->prepare("SELECT ID_Student FROM student WHERE Email_Address = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "El correo electrónico ya está en uso.";
            header('Location: register.php');
            exit;
        }
        $stmt->close();

        // Username duplicado en tabla user
        $stmt = $conn->prepare("SELECT ID_User FROM user WHERE Username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['error'] = "El nombre de usuario ya está en uso.";
            header('Location: register.php');
            exit;
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error'] = "Error al verificar duplicados: " . $e->getMessage();
        header('Location: register.php');
        exit;
    }

    // 5. Definir rol y status
    $student_role_id = 1; // el ID que corresponde a 'Student'
    $status = 'Active';

    // 6. Transacción
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

       /* $_SESSION['success'] = "¡Cuenta de estudiante registrada exitosamente! Ahora puedes iniciar sesión.";
        header('Location: StudentLogin.php');
        exit;*/

            // LIMPIAR DATOS DE SESIÓN CUANDO EL REGISTRO ES EXITOSO
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
    <link rel="stylesheet" href="../assets/register.css">
</head>
<body>
    <div class="container">
        <h1>Registro de Estudiante</h1>
        <p style="text-align: center; color: #666; margin-top: -15px; margin-bottom: 25px;">Crea tu cuenta de estudiante.</p>

        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="name">Nombre:</label>
                  <!--input type="text" id="name" name="name" required-->

                 <!--  AGREGADO: value con datos de sesión -->
                <input type="text" id="name" name="name" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['name'])) ? htmlspecialchars($_SESSION['form_data']['name']) : ''; ?>" required>            </div>

            <div class="form-group">
                <label for="last_name">Apellido:</label>
                <!--input type="text" id="last_name" name="last_name" required-->
               
                <!--AGREGADO -->
                <input type="text" id="last_name" name="last_name" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['last_name'])) ? htmlspecialchars($_SESSION['form_data']['last_name']) : ''; ?>" required>            </div>

           <!-- =========================================== -->
           <!-- Modifique para email -->
           <!-- =========================================== --> 
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['email'])) ? htmlspecialchars($_SESSION['form_data']['email']) : ''; ?>" placeholder="ejemplo@universidad.edu.mx" required>                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                    Usa tu correo institucional o personal válido (debe incluir dominio como .com, .edu.mx, etc.)
                </small>
            </div>

            <div class="form-group">
                <label for="phone">Teléfono (Opcional):</label>
                <!--input type="tel" id="phone" name="phone"-->

                 <!-- AGREGADO: value con datos de sesión -->
                 <input type="tel" id="phone" name="phone" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['phone'])) ? htmlspecialchars($_SESSION['form_data']['phone']) : ''; ?>" placeholder="Ej: 6641234567" pattern="[0-9]*" inputmode="numeric">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                    Solo números, exactamente 10 dígitos (sin letras, espacios o signos)
                </small>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">


            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <!--input type="text" id="username" name="username" required-->

                   <!--AGREGADO: value con datos de sesión -->
                <input type="text" id="username" name="username" value="<?php echo (isset($_SESSION['validation_in_progress']) && isset($_SESSION['form_data']['username'])) ? htmlspecialchars($_SESSION['form_data']['username']) : ''; ?>" required>            </div>

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
            ¿Ya tienes cuenta? <a href="StudentLogin.php">Inicia Sesión aquí</a>
        </p>
    </div>
</body>
</html>
