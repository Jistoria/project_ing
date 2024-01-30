<?php
require ('conexion/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;

    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'login':
                loginUser($conn, $data);
                break;
            case 'register':
                registerUser($conn, $data);
                break;
            case 'registerStylist':
                registerStylist($conn, $data);
                break;
            case 'deleteStylist':
                deleteStylist($conn, $data);
                break;
            case 'changePass':
                changePass($conn, $data);
                break;
            default:
                echo json_encode('Acción no válida');
                break;
        }
    } else {
        echo json_encode('Acción no especificada');
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['get']) ? $_GET['get'] : null;
    if ($action) {
        switch ($action) {
            case 'getSession':
                getSession();
                break;
            case 'logout':
                logoutUser();
                break;
            case 'isAdmin':
                session_start();
                $isAdmin = isset($_SESSION['user']['type_rol']) && $_SESSION['user']['type_rol'] === 'Administrador';
                echo json_encode(['isAdmin' => $isAdmin]);
                break;
            case 'isStylist':
                session_start();
                $isStylist = isset($_SESSION['user']['type_rol']) && $_SESSION['user']['type_rol'] === 'Estilista';
                echo json_encode(['isStylist' => $isStylist]);
                break;
            case 'getstylits':
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                getStylists($conn, $search);
                break;
            case 'my_product_rating':
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getProductRating($conn, $id);
                break;
            case 'getRecuperationPass':
                $email = isset($_GET['email']) ? $_GET['email'] : '';
                getRecuperationPass($conn, $email);
                break;
            default:
                echo json_encode('Acción no válida');
                break;
        }
    } else {
        echo json_encode('Acción no especificada');
    }
}else if($_SERVER['REQUEST_METHOD'] === 'PUT'){
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['put']) ? $_GET['put'] : null;
    switch ($action) {
        case 'updateStylist':
            updateStylist($conn, $data);
            break;
        case 'editUser':
            editUser($conn, $data);
            break;
        }
} else {
    echo json_encode('Método de solicitud no válido');
}



function loginUser($conn, $data) {
    // Procesa los datos del formulario de inicio de sesión
    $email = $data->email;
    $password = $data->password;

    // Realiza la autenticación del usuario (debes implementar tu lógica de autenticación)
    $user = getUser($email, $conn);

    if (!empty($user)) {
        //Verificar la contraseña, regresa un boolean
        $verified = autenthicationPassword($password, $conn, $user['id_user']);
        //esto solo es para volver a $user en null en caso que la contraseña sea incorrecta
        $user = $verified ? $user : null;
        if ($verified) {
            //Deberia guardar desde aqui los datos del usuario
            session_start();
            $_SESSION['user'] = $user;
            // Devuelve una respuesta JSON de éxito
            $conn->close();
            echo json_encode([
                'user' => $user,
                'success' => true,
                'message' => "Inicio de sesión exitoso"
            ]);
            exit;
        } else {
            // Devuelve una respuesta JSON de error de autenticación
            $conn->close();
            echo json_encode([
                'success' => false,
                'message' => 'Error de autenticación. Credenciales incorrectas.'
            ]);
        }
    } else {
        // Devuelve una respuesta JSON de error de autenticación
        $conn->close();
        echo json_encode([
            'success' => false,
            'message' => 'Error de autenticación. Credenciales incorrectas.'
        ]);
    }
}
function logoutUser(){
    session_start();
    if(session_destroy()){
        echo json_encode(['success' => true, 'message' => 'Se ha cerrado sesion']);
    }else{
        echo json_encode(['success' => false, 'message' => 'Ha existido un problema']);
    }
}

function getSession(){
    session_start(); 
    if(isset($_SESSION['user'])){
        echo json_encode(['success' => true, 'user' => $_SESSION['user'] ]);
    }else{
        echo json_encode(['success' => false, 'message' => 'no' ]);
    }
}
function getStylists($conn){
    $query = "SELECT
                    u.id_user,
                    u.name,
                    u.lastname,
                    u.cellphone,
                    u.color,
                    IFNULL(SUM(CASE WHEN r.id_state = 3 THEN 1 ELSE 0 END), 0) as reservations_count
                FROM
                    users u
                LEFT JOIN
                    reservation r ON r.id_employee = u.id_user
                WHERE
                    u.id_rol = 2
                GROUP BY
                    u.id_user, u.name, u.lastname, u.cellphone, u.color;
                ";
    $result = $conn->query($query);
    $list_stylist = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $conn->close();
    echo json_encode(['success' => true, 'list_stylists' => $list_stylist]);
}

function registerStylist($conn, $data){
        $name = $data->name;
        $lastname =  $data->lastname;
        $email =  $data->email;
        $cellphone = $data->cellphone;
        $color = $data->color;
        $password = $data->password;
        $r_password = $data->confirm_password;
        $id_rol = $data->id_rol;
        // Reglas de validación
        $squal = $password === $r_password; // Igualdad de contraseñas
        $n_email = uniqueEmail($email, $conn); // Comprobar email único
        if ($squal && $n_email) {
            // Encriptar la contraseña
            $hashpassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, lastname, email, cellphone, color, password, id_rol) VALUES ('$name', '$lastname', '$email', '$cellphone', '$color', '$hashpassword', $id_rol)";
            $conn->query($sql);
            $response = [
                'success' => true,
                'message' => "Estilista registrado correctamente: $name"
            ];
        } else {
            // Iniciar un array de errores para acumular los mensajes de error
            $errors = [];
            if (!$squal) {
                $errors[] = "Las contraseñas no coinciden.";
            }
            if (!$n_email) {
                $errors[] = "El email ingresado ya está registrado.";
            }
            $response = [
                'success' => false,
                'errors' => $errors
            ];
        }
        $conn->close();
        echo json_encode($response);
}
function deleteStylist($conn, $id_stylist){
    $query = "DELETE FROM users WHERE id_user = '$id_stylist'";
    $conn->query($query);
    if ($conn->error) {
        $conn->close();
        echo json_encode(['success' => false, 'message' =>"Error al eliminar el estilista: $conn->error"]);
    } else {
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Eliminado con exito']);
    }
}
function updateStylist($conn, $data){
    $id = $data->id_user;
    $name = $data->name;
    $lastname =  $data->lastname;
    $email =  $data->email;
    $cellphone = $data->cellphone;
    $color = $data->color;
        // Encriptar la contraseña
        $sql = "UPDATE users SET name = '$name', lastname = '$lastname', email = '$email', cellphone = '$cellphone', color = '$color' 
                    WHERE id_user = $id";
        $conn->query($sql);
        $response = [
            'success' => true,
            'message' => "Estilista actualizado correctamente: $name"
        ];
    $conn->close();
    echo json_encode($response);
}

function registerUser($conn, $data){
        $name = $data->name;
        $lastname =  $data->lastname;
        $email =  $data->email;
        $cellphone = $data->cellphone;
        $color = $data->color;
        $password = $data->password;
        $r_password = $data->confirm_password;
        // Reglas de validación
        $squal = $password === $r_password; // Igualdad de contraseñas
        $n_email = uniqueEmail($email, $conn); // Comprobar email único
        if ($squal && $n_email) {
            // Encriptar la contraseña
            $hashpassword = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, lastname, email, cellphone, color, password) VALUES ('$name', '$lastname', '$email', '$cellphone', '$color', '$hashpassword')";
            $conn->query($sql);
            $response = [
                'success' => true,
                'message' => "Registrado Correctamente: $name"
            ];
        } else {
            // Iniciar un array de errores para acumular los mensajes de error
            $errors = [];
            if (!$squal) {
                $errors[] = "Las contraseñas no coinciden.";
            }
            if (!$n_email) {
                $errors[] = "El email ingresado ya está registrado.";
            }
            $response = [
                'success' => false,
                'errors' => $errors
            ];
        }
        $conn->close();
        echo json_encode($response);
}



// Función para verificar si el email es único
function uniqueEmail($em, $conn) {
    $query = "SELECT email FROM `users` WHERE email = '$em' LIMIT 1";
    $statement = $conn->query($query);
    $result = mysqli_fetch_all($statement, MYSQLI_ASSOC);
    return empty($result); // Devuelve true si el email es único, false si ya existe en la base de data$data
}


function autenthicationPassword($password, $conn, $id_user) {
    $query =  "SELECT password FROM users WHERE id_user = '$id_user' LIMIT 1";
    $result = $conn->query($query);
    $row = mysqli_fetch_assoc($result);
    return password_verify($password, $row['password']);
}
function getUser($email, $conn) {
    $query =  "SELECT u.id_user, u.email, u.name, u.color, u.cellphone, r.type_rol FROM users u LEFT JOIN roles r ON u.id_rol = r.id_rol WHERE email = '$email' LIMIT 1";
    $result = $conn->query($query);
    $user  = mysqli_fetch_assoc($result) ?? null;
    return $user;
}
function getProductRating($conn, $id){
    $query = "SELECT pr.id, pr.rating, p.name_product FROM product_ratings pr
                INNER JOIN products p ON pr.id_product = p.id_product
                WHERE id_user=$id LIMIT 5";
    $result = $conn->query($query);
    $my_product_rating = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $conn->close();
    echo json_encode(['success'=>true,'my_product_rating' => $my_product_rating]);
}

function changePass($conn, $data) {
    // Validar que se proporcionen tanto el correo electrónico como la nueva contraseña
    if (!isset($data->email) || !isset($data->new_password)) {
        echo json_encode(['success' => false, 'message' => 'Correo electrónico o contraseña no proporcionados']);
        return;
    }

    // Hash de la nueva contraseña
    $hashedPassword = password_hash($data->new_password, PASSWORD_DEFAULT);

    // Usar consultas preparadas para prevenir SQL injection
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashedPassword, $data->email);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al cambiar la contraseña']);
    }

    $stmt->close();
}

function getRecuperationPass($conn, $email) {
    $email = mysqli_real_escape_string($conn, $email);

    $stmt = $conn->prepare("SELECT answer_recuperation FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $answer_recuperation = $row['answer_recuperation'];
        echo json_encode(['success' => true, 'answer_recuperation' => $answer_recuperation]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email no encontrado']);
    }

    $stmt->close();
}

function editUser($conn, $data) {
    // Validar que los datos necesarios están presentes
    if (!isset($data->id_user, $data->email, $data->name, $data->color)) {
        // Manejar el caso en el que faltan datos
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
        return;
    }

    // Sanitizar y escapar los datos para prevenir SQL injection
    $id_user = mysqli_real_escape_string($conn, $data->id_user);
    $email = mysqli_real_escape_string($conn, $data->email);
    $name = mysqli_real_escape_string($conn, $data->name);
    $color = mysqli_real_escape_string($conn, $data->color);
    $cellphone = mysqli_real_escape_string($conn, $data->cellphone);
    
    // Consulta SQL para actualizar el usuario
    $sql = "UPDATE users SET email = '$email', name = '$name', color = '$color', cellphone='$cellphone' WHERE id_user = '$id_user'";
    
    // Ejecutar la consulta
    if ($conn->query($sql)) {
        $user = getUser($email, $conn);
        session_start();
            $_SESSION['user'] = $user;
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario: ' . mysqli_error($conn)]);
    }
}
