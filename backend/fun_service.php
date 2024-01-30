<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require('conexion/db.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener los datos del formulario
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['post']) ? $_GET['post'] : null;
    if ($action) {
        // Escoger la acción basada en la variable 'action'
        switch ($action) {
            case 'create':
                createService($conn, $data);
                break;
            case 'delete':
                deleteService($conn, $data);
                break;
            case 'deleteR':
                deleteReservation($conn, $data);
                break;
            case 'reservation':
                createReservation($conn, $data);
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
            case 'getservice':
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                getServices($conn, $search);
                break;
            case 'reservationforcalendar':
                getResertCalendar($conn);
                break;
            case 'myreservation':
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                getMyReservations($conn, $id);
                break;
            case 'reservation-for-stylist-notAccept':
                $id_stylist = isset($_GET['id']) ? $_GET['id'] : '';
                getReservationForStylistNotAccept($conn, $id_stylist);
                break;
            case 'getReservationAccept':
                $id_stylist = isset($_GET['id']) ? $_GET['id'] : '';
                getReservationAccept($conn, $id_stylist);
                break;
            case 'getReservationDenied':
                $id_stylist = isset($_GET['id']) ? $_GET['id'] : '';
                getReservationDenied($conn, $id_stylist);
                break;
            case 'reservation-notAccept':
                $id_reservation =isset($_GET['id']) ? $_GET['id'] : '';
                denyReservation($conn, $id_reservation);
                break;
            case 'reservation-accept':
                $id_reservation =isset($_GET['id']) ? $_GET['id'] : '';
                acceptReservation($conn, $id_reservation);
                break;
            case 'getReservationComplete' :
                $id_reservation =isset($_GET['id']) ? $_GET['id'] : '';
                getCompleteReservation($conn, $id_reservation);
                break;
            default:
                echo json_encode('Acción no válida');
                break;
        }
    } else {
        echo json_encode('Acción no especificada');
    }
}else if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));
    $action = isset($_GET['put']) ? $_GET['put'] : null;
    if ($action) {
        switch ($action) {
            case 'edit':
                editService($conn, $data);
                break;
            case 'reservation-completada':
                completeRerservation($conn, $data);
                break;
            // ... Resto del código ...
        }
    } else {
        echo json_encode('Acción no especificada');
    }
} else {
    echo json_encode('Método de solicitud no válido');
}

function getServices($conn, $search){
    $query = "SELECT *
                FROM services LIMIT 10";
    $query = searchService($search, $query);
    $result = $conn->query($query);
    $services = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $conn->close();
    echo json_encode(['services'=>$services]);
}
function searchService($search, $query){
    if ($search && strlen($search) >= 1) {
            $query .=  " WHERE name_service LIKE '%$search%'";
    }
    return $query;
}
function createService($conn, $new_service){
    $name_service = $new_service->name_service;
    $description = $new_service->description;
    $price = $new_service->price;
    $unique =uniqueName($conn, $name_service);
    if(!$unique){
        echo json_encode(['success' =>false, 'message' => "El nombre del Servicio ya esta en uso"]);
        exit;
    }
    $query = "INSERT INTO `services`( `name_service`, `price`, `description`)
                VALUES ('$name_service', '$description', '$price')";
     // Ejecutar la consulta y manejar errores
    if ($conn->query($query) === TRUE) {
        $conn->close();
        echo json_encode(['success' => true, 'message' => "Servicio creado"]);
    } else {
        // Manejar el error
        echo json_encode(['success' => false, 'message' => "Error en la consulta: " . $conn->error]);
    }
}
function uniqueName($conn, $name_service){
    $query = "SELECT name_service FROM `services` WHERE name_service = '$name_service' LIMIT 1";
    $statement = $conn->query($query);
    $result = mysqli_fetch_assoc($statement);
    return empty($result); 
}

function editService($conn, $edit_service){
    $id_service = $edit_service->id_service;
    $name_service = $edit_service->name_service;
    $description = $edit_service->description;
    $price = $edit_service->price;
    
    $query = "UPDATE services 
    SET name_service='$name_service', description='$description', price='$price'
    WHERE id_service = $id_service";
    
    if ($conn->query($query) === TRUE) {
        $conn->close();
        echo json_encode(['success'=>true,'message'=>"Se actualizó el servicio"]);
    } else {
        $conn->close();
        echo json_encode(['success'=>false,'message'=>"Error al actualizar"]);
    }
}
function deleteService($conn, $service){
    $query = "DELETE FROM services WHERE id_service = '$service'";
    $conn->query($query);
    if ($conn->error) {
        $conn->close();
        echo json_encode(['success' => false, 'message' =>"Error al eliminar el servicio: $conn->error"]);
    } else {
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Eliminado con exito']);
    }
}
function createReservation($conn, $data){
    // Establece la zona horaria adecuada
    // Obtén los datos del cuerpo de la solicitud (si estás utilizando $_POST)
    
    $id_service = $data->id_service;
    $id_user = $data->id_user;
    $id_employee = $data->id_employee;
    $time_reservation = $data->time_reservation;
    
    $unique_employee = uniqueEmployee($conn, $id_employee);
    $unique_time = uniqueTime($conn, $time_reservation);
    if(!$unique_employee && !$unique_time){
        $response = array('success' => false, 'message' => 'El estilista ya tiene una reservación, le recomendamos 30 minutos después');
        $conn->close();
        echo json_encode($response);
        exit; 
    } 
     // Construye la consulta SQL
    $query = "INSERT INTO reservation (id_service, id_user, id_employee, time_reservation)
    VALUES ('$id_service', '$id_user', '$id_employee', '$time_reservation')";
     // Ejecuta la consulta
    if (mysqli_query($conn, $query)) {
        // Si la inserción fue exitosa, puedes enviar una respuesta de éxito
        $response = array('success' => true, 'message' => 'Se ha hecho la reservación, este pendiente al estado de su reservación en su perfil');
        $conn->close();
        echo json_encode($response);
    } else {
        // Si hubo un error, puedes enviar un mensaje de error
        $response = array('success' => false, 'message' => 'Error al crear la reservación');
        $conn->close();
        echo json_encode($response);
    }
}

function uniqueTime($conn, $time)
{
    $timeUTC = new DateTime($time);
    $adjustedTime = clone $timeUTC;
    $adjustedTime->sub(new DateInterval('PT29M'));

    $thirtyMinutesAfter = clone $timeUTC;
    $thirtyMinutesAfter->add(new DateInterval('PT29M'));

    $formattedAdjustedTime = $adjustedTime->format('Y-m-d H:i:s');
    $formattedThirtyMinutesAfter = $thirtyMinutesAfter->format('Y-m-d H:i:s');

    $query = "SELECT id_service FROM reservation WHERE time_reservation BETWEEN '$formattedAdjustedTime' AND '$formattedThirtyMinutesAfter' AND id_state=1 LIMIT 1";
    $statement = $conn->query($query);
    $result = mysqli_fetch_assoc($statement);
    return empty($result);
}


function uniqueEmployee($conn, $id){
    $query = "SELECT id_service FROM reservation WHERE id_employee = '$id' AND id_state=1 LIMIT 1"; 
    $statement = $conn->query($query);
    $result = mysqli_fetch_assoc($statement);
    return empty($result); 
}

function getResertCalendar($conn){

    $query = "SELECT r.id_service, r.time_reservation, u.name, u.cellphone 
                FROM reservation r
                INNER JOIN users u ON r.id_employee = u.id_user
                WHERE r.id_state != 3"
                ;
    $statement = $conn->query($query);

    $result = [];
    while ($row = mysqli_fetch_assoc($statement)) {
        // Crea un objeto DateTime en UTC
        $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

        // Establece la zona horaria a 'America/Guayaquil'
        $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

        // Obtiene la fecha y hora en el formato local
        $local_time = $utc_time->format('Y-m-d H:i:s');

        $result[] = [
            'id_service' => $row['id_service'],
            'time_reservation' => $local_time,
            'name' => $row['name'],
            'cellphone' => $row['cellphone']
        ];
    }
    $conn->close();
    echo json_encode(['success'=> true, 'reservation_calendar' => $result]);
}
//funcion para traer todas las reservaciones de un usuario
function getMyReservations($conn, $id) {
    $query = "SELECT r.id_reservation, r.time_reservation, sr.state, s.name_service, s.price FROM reservation r
                INNER JOIN state_reservation sr ON r.id_state = sr.id_state
                INNER JOIN services s ON r.id_service = s.id_service
                WHERE id_user = '$id'";
    $result = $conn->query($query);
    if ($result) {
        $reservations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Crea un objeto DateTime en UTC
            $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

            // Establece la zona horaria a 'America/Guayaquil'
            $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

            // Obtiene la fecha y hora en el formato local
            $local_time = $utc_time->format('Y-m-d H:i:s');

            $reservations[] = [
                'id_reservation' => $row['id_reservation'],
                'time_reservation' => $local_time,
                'state' => $row['state'],
                'name_service' => $row['name_service'],
                'price' => $row['price'],
            ];
        }

        $conn->close();
        echo json_encode(['reservations' => $reservations]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}
//funcion para traer reservaciones sin aceptar
function getReservationForStylistNotAccept($conn, $id){
    $query = "SELECT r.id_reservation, r.time_reservation,u.name, u.cellphone, sr.state, s.name_service, s.price FROM reservation r
                INNER JOIN state_reservation sr ON r.id_state = sr.id_state
                INNER JOIN services s ON r.id_service = s.id_service
                INNER JOIN users u ON r.id_user = u.id_user 
                WHERE id_employee = $id AND sr.id_state = 1";
    $result = $conn->query($query);
    if ($result) {
        $reservations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Crea un objeto DateTime en UTC
            $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

            // Establece la zona horaria a 'America/Guayaquil'
            $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

            // Obtiene la fecha y hora en el formato local
            $local_time = $utc_time->format('Y-m-d H:i:s');

            $reservations[] = [
                'id_reservation' => $row['id_reservation'],
                'time_reservation' => $local_time,
                'state' => $row['state'],
                'name_service' => $row['name_service'],
                'name_user' => $row['name'],
                'cellphone_user' => $row['cellphone'],
                'price' => $row['price'],
            ];
        }

        $conn->close();
        echo json_encode(['reservations' => $reservations]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}

function getReservationAccept($conn, $id){
    $query = "SELECT r.id_reservation, r.time_reservation, u.name, u.cellphone, sr.state, s.name_service, s.price FROM reservation r
    INNER JOIN state_reservation sr ON r.id_state = sr.id_state
    INNER JOIN services s ON r.id_service = s.id_service 
    INNER JOIN users u ON r.id_user = u.id_user
    WHERE r.id_employee = $id AND r.id_state = 2";
    $result = $conn->query($query);
    if ($result) {
        $reservations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Crea un objeto DateTime en UTC
            $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

            // Establece la zona horaria a 'America/Guayaquil'
            $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

            // Obtiene la fecha y hora en el formato local
            $local_time = $utc_time->format('Y-m-d H:i:s');

            $reservations[] = [
                'id_reservation' => $row['id_reservation'],
                'time_reservation' => $local_time,
                'state' => $row['state'],
                'name_service' => $row['name_service'],
                'name_user' => $row['name'],
                'cellphone_user' => $row['cellphone'],
                'price' => $row['price'],
            ];
        }
        $conn->close();
        echo json_encode(['reservations' => $reservations]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}

function getReservationDenied($conn, $id){
    $query = "SELECT r.id_reservation, r.time_reservation, u.name, u.cellphone, sr.state, s.name_service, s.price FROM reservation r
    INNER JOIN state_reservation sr ON r.id_state = sr.id_state
    INNER JOIN services s ON r.id_service = s.id_service 
    INNER JOIN users u ON r.id_user = u.id_user
    WHERE r.id_employee = $id AND sr.id_state = 4";
    $result = $conn->query($query);
    if ($result) {
        $reservations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Crea un objeto DateTime en UTC
            $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

            // Establece la zona horaria a 'America/Guayaquil'
            $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

            // Obtiene la fecha y hora en el formato local
            $local_time = $utc_time->format('Y-m-d H:i:s');

            $reservations[] = [
                'id_reservation' => $row['id_reservation'],
                'time_reservation' => $local_time,
                'state' => $row['state'],
                'name_service' => $row['name_service'],
                'name_user' => $row['name'],
                'cellphone_user' => $row['cellphone'],
                'price' => $row['price'],
            ];
        }
        $conn->close();
        echo json_encode(['reservations' => $reservations]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}

function getCompleteReservation($conn, $id){
    $query = "SELECT r.id_reservation, r.time_reservation, u.name, u.cellphone, sr.state, s.name_service, s.price FROM reservation r
    INNER JOIN state_reservation sr ON r.id_state = sr.id_state
    INNER JOIN services s ON r.id_service = s.id_service
    INNER JOIN users u ON r.id_user = u.id_user
    WHERE r.id_employee = $id AND r.id_state = 3";
    $result = $conn->query($query);
    if ($result) {
        $reservations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Crea un objeto DateTime en UTC
            $utc_time = new DateTime($row['time_reservation'], new DateTimeZone('UTC'));

            // Establece la zona horaria a 'America/Guayaquil'
            $utc_time->setTimezone(new DateTimeZone('America/Guayaquil'));

            // Obtiene la fecha y hora en el formato local
            $local_time = $utc_time->format('Y-m-d H:i:s');

            $reservations[] = [
                'id_reservation' => $row['id_reservation'],
                'time_reservation' => $local_time,
                'state' => $row['state'],
                'name_service' => $row['name_service'],
                'name_user' => $row['name'],
                'cellphone_user' => $row['cellphone'],
                'price' => $row['price'],
            ];
        }
        $conn->close();
        echo json_encode(['reservations' => $reservations]);
    } else {
        // Manejo de errores, si es necesario
        $conn->close();
        echo json_encode(['error' => $conn->error]);
    }
}
function denyReservation($conn, $id){
    $query = "UPDATE reservation SET id_state = 4 WHERE id_reservation = $id";
    $result = $conn->query($query);
    if($conn->error){
        $conn->close();
        echo json_encode(['success'=> false, 'message'=> "Hubo un error en el proceso: " . $conn->error]);
    } else {
        echo json_encode(['success'=> true, 'message'=> "Se ha rechazado la reservación"]);
        $conn->close();
    }
}

function acceptReservation($conn, $id){
    $query = "UPDATE reservation SET id_state = 2 WHERE id_reservation = $id";
    $result = $conn->query($query);
    if($conn->error){
        $conn->close();
        echo json_encode(['success'=> false, 'message'=> "Hubo un error en el proceso: " . $conn->error]);
    } else {
        $conn->close();
        echo json_encode(['success'=> true, 'message'=> "Se ha aceptado la reservación"]);
    }
}

function completeRerservation($conn, $id){
    $query = "UPDATE reservation SET id_state = 3 WHERE id_reservation = $id";
    $result = $conn->query($query);
    if($conn->error){
        $conn->close();
        echo json_encode(['success'=> false, 'message'=> "Hubo un error en el proceso: " . $conn->error]);
    } else {
        $conn->close();
        echo json_encode(['success'=> true, 'message'=> "Se ha completado la reservación"]);
    }
}
function deleteReservation($conn, $id){
    $query = "DELETE reservation WHERE id_reservation = $id";
    // Ejecutar la consulta y manejar errores
    if ($conn->query($query) === TRUE) {
        $conn->close();
        echo json_encode(['success' => true, 'message' => "Reservación Eliminada"]);
    } else {
        // Manejar el error
        echo json_encode(['success' => false, 'message' => "Error en la petición: " . $conn->error]);
    }
}
