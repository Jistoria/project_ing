<?php
require('conexion/db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;
    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'newrating':
                newRating($conn, $data);
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
            case 'getstars':
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getStars($conn, $id);
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

function getStars($conn, $id){
    $query = "SELECT AVG(rating) as average_rating, COUNT(*) as count
                FROM product_ratings 
                WHERE id_product = '$id'";
    $result = $conn->query($query);
    
    if ($result) {
        if ($result->num_rows > 0) {
            $stars = $result->fetch_assoc();
            $conn->close();
            echo json_encode(['stars' => $stars]);
        }
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}

function newRating($conn, $data) {
    $id_product = $data->id_product;
    $id_user = $data->id_user;
    $rating = $data->rating;
    // Verificar si ya existe una puntuación para este usuario y producto
    $checkQuery = "SELECT * FROM product_ratings WHERE id_product = $id_product AND id_user = $id_user";
    $checkResult = $conn->query($checkQuery);
    if ($checkResult->num_rows > 0) {
        // Si ya existe una puntuación, actualizamos la existente
        $updateQuery = "UPDATE product_ratings SET rating = $rating WHERE id_product = $id_product AND id_user = $id_user";
        $conn->query($updateQuery);
    } else {
        // Si no existe una puntuación, la insertamos
        $insertQuery = "INSERT INTO product_ratings (id_product, id_user, rating) VALUES ($id_product, $id_user, $rating)";
        $conn->query($insertQuery);
    }
    $stars = getAfter($conn, $id_product);
    $conn->close();
    echo json_encode(['success' => true, 'message' => "Puntuación enviada",'stars' => $stars]);
}

function getAfter($conn, $id){
    $query = "SELECT AVG(rating) as average_rating, COUNT(*) as count
                FROM product_ratings 
                WHERE id_product = '$id'";
    $result = $conn->query($query);
    $stars = $result->fetch_assoc();
    return $stars;
    
}