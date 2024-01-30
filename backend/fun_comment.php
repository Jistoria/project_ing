<?php
require('conexion/db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;
    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'newcomment':
                newComment($conn, $data);
                break;
            // case 'register':
            //     registerUser($conn, $data);
            //     break;
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
            case 'getcomments':
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getComments($conn, $id);
                break;
            // case 'logout':
            //     logoutUser();
            //     break;
            default:
                echo json_encode('Acción no válida');
                break;
        }
    } else {
        echo json_encode('Acción no especificada');
    }
} else {
    echo json_encode('Método de solicitud no válido');
}

function getComments($conn, $id){
    $query = "SELECT comments.*, users.name AS user_name
                FROM comments 
                INNER JOIN users ON comments.id_user = users.id_user
                WHERE id_product = '$id'";
    $result = $conn->query($query);
    
    if ($result) {
            $comments = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $conn->close();
            echo json_encode(['comments' => $comments]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}

function newComment($conn, $data) {
    $id_product = $data->id_product;
    $id_user = $data->id_user;
    $content = $data->content;
    $query = "INSERT INTO comments (id_product, id_user, content) VALUES ($id_product, $id_user, '$content')";
    $conn->query($query);
    
    $comments = getAfter($conn, $id_product);
    $conn->close();
    echo json_encode(['success' => true,'comments' => $comments]);
}

function getAfter($conn, $id){
    $query = "SELECT comments.*, users.name AS user_name
                FROM comments 
                INNER JOIN users ON comments.id_user = users.id_user
                WHERE id_product = '$id'";
    $result = $conn->query($query);
    $comments =  mysqli_fetch_all($result, MYSQLI_ASSOC);
    return $comments;
    
}