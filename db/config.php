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

// Generate unique bill number
function generateBillNo($conn)
{
    $query = "SELECT bill_no FROM salesbill ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastBillNo = (int)$row['bill_no'] + 1;
        return str_pad($lastBillNo, 3, '0', STR_PAD_LEFT);
    }
    return '001';
}

function generatePartyBillNo($conn)
{
    $query = "SELECT bill_no FROM salesparty ORDER BY bill_no DESC LIMIT 1";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_bill_no = $row['bill_no'];
        // Extract number part and increment
        $bill_no = (int)substr($last_bill_no, 1) + 1;
        return 'B' . str_pad($bill_no, 5, '0', STR_PAD_LEFT); // Generate bill number like B00001, B00002, etc.
    } else {
        return 'B00001'; // Start from B00001 if no previous bill exists
    }
}

function generatePurchaseNo($conn)
{
    $prefix = 'PUR';
    $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(purchase_no, 4) AS UNSIGNED)) AS max_no FROM purchase");
    $row = $stmt->fetch_assoc();
    $next_no = $row['max_no'] + 1;
    return $prefix . str_pad($next_no, 6, "0", STR_PAD_LEFT);
}

function generatePurchaseOrderNo($conn)
{
    $prefix = 'PO-';
    // Query to get the highest purchase order number, extracting the numeric part
    $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(purchase_order_billno, 4) AS UNSIGNED)) AS max_no FROM purchase_order");

    // Fetch the result
    $row = $stmt->fetch_assoc();

    // If no record exists, start from 1
    $next_no = isset($row['max_no']) ? $row['max_no'] + 1 : 1;

    // Return the next purchase order number with padding
    return $prefix . str_pad($next_no, 6, "0", STR_PAD_LEFT);
}
