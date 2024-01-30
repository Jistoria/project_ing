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
                createProduct($conn);
                break;
            case 'delete':
                deleteProduct($conn, $data);
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
            case 'paginate':
                //declarar datos para la paginacion, busqueda y filtro
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                $page = !isset($_GET['page']) ? 1 : $_GET['page'];
                $f_category = isset($_GET['category']) ? $_GET['category'] : '';
                paginateProducts($conn, $search, $page, $f_category);
                break;
            case 'table':
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                $f_category = isset($_GET['f_category']) ? $_GET['f_category'] : '';
                getProducts($conn, $search, $f_category);
                break;
            case 'infoProduct':
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                infoProduct($conn, $id);
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
            case 'edit':
                editProduct($conn, $data);
                break;
            case 'updateStock':
                updateStock($conn, $data);
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




//Funciones
function infoProduct($conn, $id) {
    $query = "SELECT p.id_product, p.name_product, p.description, p.stock, p.image, b.name_brand, c.name_category
    FROM products p
    INNER JOIN brands b ON b.id_brand = p.id_brand
    INNER JOIN categories c ON c.id_category = p.id_category WHERE id_product = '$id'";
    $result = $conn->query($query);
    // Verificar si se encontraron resultados
    if ($result->num_rows > 0) {
        // Extraer el primer elemento del resultado
        $product = $result->fetch_assoc();
        
        $response = [
            "product" => $product,
            "success" => true,
        ];
    } else {
        $response = [
            "success" => false,
            "message" => "No se encontró el producto con el ID proporcionado.",
        ];
    }
    // Devolver la respuesta como JSON
    $conn->close();
    echo json_encode($response);
}
function paginateProducts($conn, $search, $page, $f_category){
    $query = "SELECT p.id_product, p.name_product, p.stock, p.image, b.name_brand, p.price, AVG(pr.rating) as average_rating
            FROM products p
            LEFT JOIN product_ratings pr ON p.id_product = pr.id_product
            INNER JOIN brands b ON b.id_brand = p.id_brand
            INNER JOIN categories c ON c.id_category = p.id_category";
    $query =  filterProducts($f_category, $query);
    $query = searchProducts($search, $query, $f_category);
    $pagination = dataPaginateProducts($page, $query, $conn);
    $query .= $pagination['paginate']; 
    $result = $conn->query($query);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC); 
    $response = [
        "products" => $products,
        "pages" => $pagination['pages'],
        "total_products" => $pagination['total_products']
    ];
    $conn->close();
    // Devolver la respuesta como JSON
    echo json_encode($response);
}

function searchProducts($search, $query, $f_category){
    if (!empty($search) && strlen($search) >= 1) {
        if(!empty($f_category)){
            $query .=  " AND name_product LIKE '%$search%'";
        }else{
            $query .=  " WHERE name_product LIKE '%$search%'";
        }
    }
    return $query;
}

function filterProducts($f_category, $query){
    if (!empty($f_category)) {
        $query .= " WHERE c.id_category = '$f_category'";
    }
    return $query;
}

function dataPaginateProducts($page, $query, $conn){
    $total_for_page = 3;
    $offset = ($page - 1) * $total_for_page;
    $paginate = " GROUP BY name_product LIMIT $offset, $total_for_page";
    $tuki = $query." GROUP BY name_product ";
    $total_products = $conn->query($tuki)->num_rows;
    $pages = ceil($total_products / $total_for_page);
    return [
        "pages"=>$pages,
        "paginate" => $paginate,
        "total_products" => $total_products
    ];
}

function getProducts($conn, $search, $f_category){
    $query = "SELECT *
                FROM products LIMIT 20";
    $query = searchProducts($search, $query, $f_category);
    $result = $conn->query($query);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $conn->close();
    echo json_encode(['products'=>$products]);
}

function createProduct($conn){
        $name_product = $_POST['name_product'];
        $description  = $_POST['description'];
        $stock = $_POST['stock'];
        $price = $_POST['price'];
        $id_brand = $_POST['id_brand'];
        $id_category = $_POST['id_category'];
        $file = $_FILES['image'];

        $verify_image = uploadImage($file);
        if($verify_image['success']=== false){
            echo json_encode(['success' => false, 'message' => $verify_image['message']]);
            exit;
        }
        $url_image = $verify_image['message'];

        $unique = uniqueName($conn, $name_product);
        if(!$unique){
            echo json_encode(['success' => false, 'message' => "Nombre del producto ya existe"]);
            exit;
        }

        $query = "INSERT INTO products (id_brand, id_category, name_product, description, price,stock, image) 
                    VALUES ('$id_brand', '$id_category', '$name_product', '$description',$price,$stock,'$url_image')";

        
        if ($conn->query($query) === TRUE) {
            $conn->close();
            echo json_encode(['success' => true, 'message' => "Producto creado con éxito"]);
        } else {
            $conn->close();
            echo json_encode(['success' => false, 'message' => "Hubo un error"]);
        }
}
function uniqueName($conn, $name_product){
    $query = "SELECT name_product FROM `products` WHERE name_product = '$name_product' LIMIT 1";
    $statement = $conn->query($query);
    $result = mysqli_fetch_assoc($statement);
    return empty($result); 
}

function deleteProduct($conn, $product) {
    $existOrder = $conn->query("SELECT id_order_line FROM order_line WHERE id_product = $product LIMIT 1");
    $result = mysqli_fetch_assoc($existOrder);
    if($result){
        echo json_encode(['success'=> false, 'message'=> 'Existe un pedido con este producto']);
        exit;
    }
    // Obtener la URL de la imagen
    $urlResult = $conn->query("SELECT image FROM products WHERE id_product = $product");

    if (!$urlResult) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener la ruta de la imagen']);
        exit;
    }

    $urlData = $urlResult->fetch_assoc();
    $imageUrl = $urlData['image'];

    // Verificar si la URL de la imagen existe antes de intentar eliminarla
    if ($imageUrl && file_exists($imageUrl)) {
        unlink($imageUrl); // Eliminar la imagen
    }

    // Eliminar el producto de la base de datos
    $query = "DELETE FROM products WHERE id_product = '$product'";
    $conn->query($query);

    if ($conn->error) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => "Error al eliminar el producto: $conn->error"]);
    } else {
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Eliminado con éxito']);
    }
}


function editProduct($conn, $edit_product){
    $id_product = $edit_product->id_product;
    $name_product = $edit_product->name_product;
    $stock = $edit_product->stock;
    $description = $edit_product->description;
    $price = $edit_product->price;
    $id_brand = $edit_product->id_brand; // Agregamos esta línea para obtener el id de la marca
    $id_category = $edit_product->id_category; // Agregamos esta línea para obtener el id de la categoría
    
    $query = "UPDATE products 
    SET name_product='$name_product', description='$description', price='$price', id_brand='$id_brand', id_category='$id_category', stock='$stock'
    WHERE id_product = $id_product";
    
    if ($conn->query($query) === TRUE) {
        $conn->close();
        echo json_encode(['success' => true, 'message' => "Producto editado con éxito"]);
    } else {
        $conn->close();
        echo json_encode(['success' => false, 'message' =>"Error al editar el producto: $conn->error"]);
    }
}

function uploadImage($file){
    $target_dir = "image/"; // Carpeta donde se guardarán las imágenes
    $target_file = $target_dir . basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Verificar si el archivo es una imagen real o un archivo falso
    $check = getimagesize($file["tmp_name"]);
    if($check !== false) {
        $uploadOk = 1;
    } else {
        return (['success'=> false, 'message'=> "El archivo no es una imagen."]);
        $uploadOk = 0;
    }

    // Verificar si el archivo ya existe
    if (file_exists($target_file)) {
        return (['success'=> false, 'message'=> "Lo siento, el archivo ya existe."]);
        $uploadOk = 0;
    }

    // Verificar el tamaño del archivo (en este ejemplo, limitado a 2MB)
    if ($file["size"] > 2000000) {
        return (['success'=> false, 'message'=> "Lo siento, el archivo es demasiado grande."]);
        $uploadOk = 0;
    }

    // Permitir ciertos formatos de archivo
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        return (['success'=> false, 'message'=> "Lo siento, solo se permiten archivos JPG, JPEG, PNG y GIF."]);
        $uploadOk = 0;
    }

    // Verificar si $uploadOk está seteado a 0 por algún error
    if ($uploadOk == 1)  {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return (['success'=> true, 'message'=>  $target_file]); // Devolver la ruta del archivo subido
        } else {
            return (['success'=> false, 'message'=> "Lo siento, hubo un error al subir tu archivo."]);
        }
    }
}

function updateStock($conn, $data){
    $id_product = $data->id_product;
    $stock = $data->stock;

    // Actualiza el stock en la base de datos
    $query = "UPDATE products SET stock = $stock WHERE id_product = $id_product";
    $result = $conn->query($query);

    if ($result) {
        // Éxito al actualizar el stock
        echo json_encode(['success' => true, 'message' => 'Stock actualizado con éxito']);
    } else {
        // Error al actualizar el stock
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el stock']);
    }
}

