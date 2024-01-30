<?php
require('conexion/db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;
    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'create':
                
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
            case 'getbrands':
                getBrands($conn);
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
function getBrands($conn){
    $query = "SELECT id_brand, name_brand
                FROM brands";
    $result = $conn->query($query);
    $brands = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $conn->close();
    echo json_encode(['brands'=>$brands]);
}