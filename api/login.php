<?php
include 'db/config.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

$secret_key = "9025148394zentexus";

if (isset($obj->email) && isset($obj->password)) {
    $email = $obj->email;
    $password = $obj->password;

    if (!empty($email) && !empty($password)) {
        // Check user in the database
        $stmt = $conn->prepare("SELECT `id`, `user_id`, `User_Name`, `email`, `Password` FROM `users` WHERE `email` = ? AND `delete_at` = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            // Verify password
            if ($row['Password'] === $password) {
                // Create JWT payload
                $payload = array(
                    "iss" => "http://localhost",
                    "aud" => "http://localhost",
                    "iat" => time(),
                    "exp" => time() + (60 * 60),
                    "data" => array(
                        "id" => $row['user_id'],
                        "user_name" => $row['User_Name'],
                        "email" => $row['email']
                    )
                );

                // Generate JWT
                $jwt = JWT::encode($payload, $secret_key, 'HS256');

                $output["head"]["code"] = 200;
                $output["head"]["msg"] = "Success";
                $output["body"]["user"] = array(
                    "id" => $row['user_id'],
                    "user_name" => $row['User_Name'],
                    "email" => $row['email']
                );
                $output["body"]["token"] = $jwt;
            } else {
                $output["head"]["code"] = 400;
                $output["head"]["msg"] = "Invalid Credentials";
            }
        } else {
            $output["head"]["code"] = 400;
            $output["head"]["msg"] = "User Not Found.";
        }
    } else {
        $output["head"]["code"] = 400;
        $output["head"]["msg"] = "Please provide all the required details.";
    }
} else {
    $output["head"]["code"] = 400;
    $output["head"]["msg"] = "Parameter is Mismatch";
}

echo json_encode($output, JSON_NUMERIC_CHECK);
