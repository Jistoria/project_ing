<?php
require('conexion/db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;
    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'acceptShopp':
                acceptShopp($conn, $data);
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
            case 'getorders':
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                $f_category = isset($_GET['f_category']) ? $_GET['f_category'] : '';
                getOrders($conn, $search);
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
} else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['put']) ? $_GET['put'] : null;
    if ($action) {
        switch ($action) {
            case 'confirmPayment':
                confirmPayment($conn, $data);
                break;
            case 'cancelPayment':
                cancelPayment($conn, $data);
                break;
            case 'deletePayment':
                deletePayment($conn, $data);
                break;
            // ... Resto del código ...
        }
    } else {
        echo json_encode('Acción no especificada');
    }
}
else {
    echo json_encode('Método de solicitud no válido');
}

function getOrders($conn, $search) {
    $query = "SELECT o.id_order, u.name as user_name, u.email, u.cellphone, 
                    ol.id_order_line, p.name_product, ol.quantity, 
                    ol.amount_line, o.created_at, o.amount_total, so.state_order
                FROM orders o
                INNER JOIN order_line ol ON o.id_order = ol.id_order
                INNER JOIN products p ON ol.id_product = p.id_product
                INNER JOIN users u ON o.id_user = u.id_user
                INNER JOIN state_order so ON o.id_state_order = so.id_state_order";
    $query = searchOrders($search, $query);
    $result = $conn->query($query);

    if ($result) {
        $orders = [];

        while ($row = $result->fetch_assoc()) {
            // Organizar los resultados en una estructura anidada
            $orderIndex = array_search($row['id_order'], array_column($orders, 'id_order'));

            if ($orderIndex === false) {
                $order = [
                    'id_order' => $row['id_order'],
                    'user' => [
                        'name' => $row['user_name'],
                        'email' => $row['email'],
                        'cellphone' => $row['cellphone']
                    ],
                    'order_lines' => [],
                    'created_at' => $row['created_at'],
                    'amount_total' => $row['amount_total'],
                    'state_order' => $row['state_order']
                ];

                $orders[] = $order;
                $orderIndex = count($orders) - 1;
            }

            $orderLine = [
                'id_order_line' => $row['id_order_line'],
                'name_product' => $row['name_product'],
                'quantity' => $row['quantity'],
                'amount_line' => $row['amount_line'],
            ];

            $orders[$orderIndex]['order_lines'][] = $orderLine;
        }

        $conn->close();
        echo json_encode(['orders' => $orders]);
    } else {
        // Manejar errores en caso de que la consulta falle
        echo json_encode(['error' => $conn->error]);
    }
}

function searchOrders($search, $query, $f_category=null){
    if ($search && strlen($search) >= 1) {
        if(!empty($f_category)){
            $query .=  " AND name_product LIKE '%$search%'";
        }else{
            $query .=  " WHERE u.name LIKE '%$search%' 
            OR p.name_product LIKE '%$search%'
            OR o.id_order = $search";
        }
    }
    return $query;
}

function acceptShopp($conn, $data) {
    try {
        // Comenzamos una transacción
        $conn->begin_transaction();

        // Creamos la orden
        $orderQuery = "INSERT INTO orders (id_user, amount_total, created_at) VALUES (?, ?, NOW())";
        $orderStatement = $conn->prepare($orderQuery);
        $orderStatement->bind_param('id', $data->id_user, $data->total_sale);
        $orderStatement->execute();

        // Obtenemos el ID de la orden recién insertada
        $orderId = $conn->insert_id;

        // Creamos las líneas de orden
        foreach ($data->cart_shopp as $item) {
            $amount_line = $item->price * $item->count;

            $lineQuery = "INSERT INTO order_line (id_order, id_product, quantity, amount_line) VALUES (?, ?, ?, ?)";
            $lineStatement = $conn->prepare($lineQuery);
            $lineStatement->bind_param('iiid', $orderId, $item->id_product, $item->count, $amount_line);
            $lineStatement->execute();
        }

        // Confirmamos la transacción
        $conn->commit();

        // Devolvemos algún indicador de éxito si es necesario
        $conn->close();
        echo json_encode(['success'=>true, 'message'=>"Se ha creado la orden, deberá ir a cancelar y recoger en los próximos 3 días"]);

    } catch (Exception $e) {
        // En caso de error, revertimos la transacción
        $conn->rollback();
        // Maneja el error de alguna manera (registra, imprime, etc.)
        echo json_encode(['success'=>false, 'message'=>"Ha existido un error ".$e->getMessage()]);
        // Devuelve algún indicador de fallo si es necesario
    }
}

function confirmPayment($conn, $orderId) {
    // Primero, verifica si la orden existe antes de intentar actualizarla
    $checkOrderQuery = "SELECT id_order FROM orders WHERE id_order = $orderId";
    $checkOrderResult = $conn->query($checkOrderQuery);

    if ($checkOrderResult->num_rows === 0) {
        // La orden no existe, puedes manejar esto según tus necesidades
        echo json_encode(['success' => false, 'message' => 'La orden no existe.']);
        return;
    }

    // La orden existe, ahora actualiza el estado de la orden
    $updateOrderQuery = "UPDATE orders SET id_state_order = 2 WHERE id_order = $orderId";
    $updateOrderResult = $conn->query($updateOrderQuery);

    if ($updateOrderResult) {
        // Actualización exitosa
        echo json_encode(['success' => true, 'message' => 'Pago confirmado con éxito.']);
    } else {
        // Error al actualizar la orden
        echo json_encode(['success' => false, 'message' => 'Error al confirmar el pago.']);
    }
}
function cancelPayment($conn, $orderId) {
    // Primero, verifica si la orden existe antes de intentar actualizarla
    $checkOrderQuery = "SELECT id_order FROM orders WHERE id_order = $orderId";
    $checkOrderResult = $conn->query($checkOrderQuery);

    if ($checkOrderResult->num_rows === 0) {
        // La orden no existe, puedes manejar esto según tus necesidades
        echo json_encode(['success' => false, 'message' => 'La orden no existe.']);
        return;
    }

    // La orden existe, ahora actualiza el estado de la orden
    $updateOrderQuery = "UPDATE orders SET id_state_order = 3 WHERE id_order = $orderId";
    $updateOrderResult = $conn->query($updateOrderQuery);

    if ($updateOrderResult) {
        // Actualización exitosa
        echo json_encode(['success' => true, 'message' => 'Pedido cancelado con éxito.']);
    } else {
        // Error al actualizar la orden
        echo json_encode(['success' => false, 'message' => 'Error al cancelar el pedido.']);
    }
}
function deletePayment ($conn, $data){
    $query = "DELETE FROM orders WHERE id_order = $data";
    // Ejecutar la consulta y manejar errores
    if ($conn->query($query) === TRUE) {
        $conn->close();
        echo json_encode(['success' => true, 'message' => "Orden Eliminada"]);
    } else {
        // Manejar el error
        echo json_encode(['success' => false, 'message' => "Error en la petición: " . $conn->error]);
    }
}