<?php
$name = "localhost";
$username = "root";
$password = "";
$database = "zentexus_panel";

$conn = new mysqli($name, $username, $password, $database);

if ($conn->connect_error) {
    $output = array();
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "DB Connection Lost...";

    echo json_encode($output, JSON_NUMERIC_CHECK);
};


// <<<<<<<<<<===================== Function For Check Numbers Only =====================>>>>>>>>>>

function numericCheck($data)
{
    if (!preg_match('/[^0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets Only =====================>>>>>>>>>>

function alphaCheck($data)
{
    if (!preg_match('/[^a-zA-Z]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function For Check Alphabets and Numbers Only =====================>>>>>>>>>>

function alphaNumericCheck($data)
{
    if (!preg_match('/[^a-zA-Z0-9]+/', $data)) {
        return true;
    } else {
        return false;
    }
}

// <<<<<<<<<<===================== Function for checking user exist or not =====================>>>>>>>>>>
function userExist($user)
{
    global $conn;

    $checkUser = $conn->query("SELECT `Name` FROM `users` WHERE `user_id`='$user'");
    if ($checkUser->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}




function convertUniqueName($value)
{

    $value = str_replace(' ', '', $value);
    $value = strtolower($value);

    return $value;
}






function pngImageToWebP($data, $file_path)
{
    // Check if the GD extension is available
    if (!extension_loaded('gd')) {
        echo 'GD extension is not available. Please install or enable the GD extension.';
        return false;
    }

    // Decode the base64 image data
    $imageData = base64_decode($data);

    // Create an image resource from the PNG data
    $sourceImage = imagecreatefromstring($imageData);

    if ($sourceImage === false) {
        echo 'Failed to create the source image.';
        return false;
    }
    //dyanamic file path
    date_default_timezone_set('Asia/Calcutta');

    $timestamp = date('Y-m-d H:i:s');

    $timestamp = str_replace(array(" ", ":"), "-", $timestamp);

    $file_pathnew = $file_path . $timestamp . ".webp";

    $retunfilename = $timestamp . ".webp";
    try {
        // Convert PNG to WebP
        if (!imagewebp($sourceImage, $file_pathnew, 80)) {
            echo 'Failed to convert PNG to WebP.';
            return false;
        }
    } catch (\Throwable $th) {
        echo $th;
    }



    // Free up memory
    imagedestroy($sourceImage);

    //echo 'WebP image saved successfully.';
    return $retunfilename;
}

function isBase64ImageValid($base64Image)
{
    // Check if the provided string is a valid base64 string
    if (!preg_match('/^(data:image\/(png|jpeg|jpg|gif);base64,)/', $base64Image)) {
        return false;
    }

    // Remove the data URI prefix
    $base64Image = str_replace('data:image/png;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpeg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/jpg;base64,', '', $base64Image);
    $base64Image = str_replace('data:image/gif;base64,', '', $base64Image);

    // Check if the remaining string is a valid base64 string
    if (!base64_decode($base64Image, true)) {
        return false;
    }

    // Check if the decoded data is a valid image
    $image = imagecreatefromstring(base64_decode($base64Image));
    if (!$image) {
        return false;
    }

    // Clean up resources
    imagedestroy($image);

    return true;
}


function ImageRemove($string, $id)
{
    global $conn;
    $status = "No Data Updated";
    if ($string == "user") {
        $sql_user = "UPDATE `user` SET `img`=null WHERE `id` ='$id' ";
        if ($conn->query($sql_user) === TRUE) {
            $status = "User Image Removed Successfully";
        } else {
            $status = "User Image Not Removed !";
        }
    } else if ($string == "staff") {
        $sql_staff = "UPDATE `staff` SET `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_staff) === TRUE) {
            $status = "staff Image Removed Successfully";
        } else {
            $status = "staff Image Not Removed !";
        }
    } else if ($string == "company") {
        $sql_company = "UPDATE `company` SET  `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_company) === TRUE) {
            $status = "company Image Removed Successfully";
        } else {
            $status = "company Image Not Removed !";
        }
    } else if ($string == "product") {
        $sql_products = " UPDATE `products` SET `img`=null WHERE `id`='$id' ";
        if ($conn->query($sql_products) === TRUE) {
            $status = "products Image Removed Successfully";
        } else {
            $status = "products Image Not Removed !";
        }
    }
    return $status;
}


function uniqueID($prefix_name, $auto_increment_id)
{

    date_default_timezone_set('Asia/Calcutta');
    $timestamp = date('Y-m-d H:i:s');
    $encryptId = $prefix_name . "_" . $timestamp . "_" . $auto_increment_id;

    $hashid = md5($encryptId);

    return $hashid;
}

function saveBase64File($base64String, $uploadDir = 'uploads/')
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Extract MIME type and Base64 data
    if (!preg_match('#^data:([a-zA-Z0-9]+/[a-zA-Z0-9]+);base64,(.+)$#', $base64String, $matches)) {
        return ["success" => false, "message" => "Invalid Base64 data format"];
    }

    $mimeType = $matches[1]; // e.g., application/pdf, image/jpeg
    $base64Data = $matches[2]; // Base64-encoded data
    $base64Data = str_replace(' ', '+', $base64Data);
    $decodedData = base64_decode($base64Data);

    if ($decodedData === false) {
        return ["success" => false, "message" => "Failed to decode Base64 data"];
    }

    // Determine file extension based on MIME type
    $extension = '';
    switch ($mimeType) {
        case 'application/pdf':
            $extension = 'pdf';
            break;
        case 'image/jpeg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/gif':
            $extension = 'gif';
            break;
        default:
            return ["success" => false, "message" => "Unsupported file type: $mimeType"];
    }

    // Generate a unique file name
    $fileName = 'file_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Save the file
    if (file_put_contents($filePath, $decodedData)) {
        return ["success" => true, "filePath" => $filePath, "mimeType" => $mimeType];
    } else {
        return ["success" => false, "message" => "Failed to save file"];
    }
}

function generateSaleInvoiceNo($conn)
{
    $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM sales");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $max_id = $row['max_id'] ? $row['max_id'] + 1 : 1;
    $sales_invoice_no = sprintf("zen_%03d_sale", $max_id);
    $stmt->close();


    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE sales_invoice_no = ?");
    $stmt->bind_param("s", $sales_invoice_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        $max_id++;
        $sales_invoice_no = sprintf("zen_%03d_sale", $max_id);
    }
    $stmt->close();

    return $sales_invoice_no;
}

function generatePerformaInvoiceNo($conn)
{
    $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM performa");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $max_id = $row['max_id'] ? $row['max_id'] + 1 : 1;
    $performa_invoice_no = sprintf("zen_%03d_performa", $max_id);
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM performa WHERE performa_invoice_no = ?");
    $stmt->bind_param("s", $performa_invoice_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        $max_id++;
        $performa_invoice_no = sprintf("zen_%03d_performa", $max_id);
    }
    $stmt->close();

    return $performa_invoice_no;
}

function fetchPaginatedRecords($conn, $table, $filters, $obj)
{
    $page = isset($obj['page']) ? max(1, (int)$obj['page']) : 1;
    $limit = isset($obj['limit']) ? max(1, (int)$obj['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Count Query
    $countQuery = "SELECT COUNT(*) as total FROM $table WHERE delete_at = 0";
    $countParams = [];
    $countTypes = '';

    foreach ($filters as $field) {
        if (!empty($obj[$field])) {
            $countQuery .= " AND $field LIKE ?";
            $countParams[] = '%' . $obj[$field] . '%';
            $countTypes .= 's';
        }
    }

    $countStmt = $conn->prepare($countQuery);
    if ($countParams) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Data Query
    $query = "SELECT * FROM $table WHERE delete_at = 0";
    $params = [];
    $types = '';

    foreach ($filters as $field) {
        if (!empty($obj[$field])) {
            $query .= " AND $field LIKE ?";
            $params[] = '%' . $obj[$field] . '%';
            $types .= 's';
        }
    }


    $query .= " ORDER BY create_at ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $records = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return [
        'records' => $records,
        'totalRecords' => $totalRecords
    ];
}
