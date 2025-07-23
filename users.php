<?php

include 'headers.php';
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$json = file_get_contents('php://input');
$obj = json_decode($json);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (!isset($obj->action)) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj->action;

// List Users

if ($action === 'listUsers') {
    // Extract filter values safely
    $user_name = $obj->User_Name ?? '';
    $email = $obj->email ?? '';
    $role = $obj->role ?? '';

    // Base query and dynamic filters
    $query = "SELECT * FROM `users` WHERE `delete_at` = 0";
    $params = [];
    $types = '';

    // Dynamic filter building
    $filters = [
        ['value' => $user_name, 'sql' => "`User_Name` LIKE ?", 'bind' => function ($val) {
            return "%$val%";
        }],
        ['value' => $email,     'sql' => "`email` LIKE ?",     'bind' => function ($val) {
            return "%$val%";
        }],
        ['value' => $role,      'sql' => "`role` = ?",         'bind' => function ($val) {
            return $val;
        }],
    ];

    foreach ($filters as $filter) {
        if (!empty($filter['value'])) {
            $query .= " AND " . $filter['sql'];
            $params[] = $filter['bind']($filter['value']);
            $types .= 's';
        }
    }

    $query .= " ORDER BY `id` ASC";
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $users = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $output = [
        "head" => ["code" => 200, "msg" => $users ? "Success" : "No Users Found"],
        "body" => ["users" => $users]
    ];
}


// Add User
elseif ($action === 'addUser' && isset($obj->User_Name, $obj->Password, $obj->email, $obj->role, $obj->role_id)) {
    $User_Name = $obj->User_Name;
    $Password = $obj->Password;
    $email = $obj->email;
    $role = $obj->role;
    $role_id = $obj->role_id;

    if (
        !empty($User_Name) && !empty($Password) && !empty($email) && !empty($role)
        && !empty($role_id)
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("SELECT * FROM `users` WHERE `email` = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $emailCheck = $stmt->get_result();

            if ($emailCheck->num_rows == 0) {
                $stmtInsert = $conn->prepare("INSERT INTO `users` (`user_id`, `User_Name`, `Password`, `email`, `role`,`role_id`, `create_at`, `delete_at`) VALUES (?, ?, ?, ?, ?,?, ?, 0)");
                $user_id = uniqid('USER');
                $stmtInsert->bind_param("sssssss", $user_id, $User_Name, $Password, $email, $role, $role_id, $timestamp);

                if ($stmtInsert->execute()) {
                    $output = ["head" => ["code" => 200, "msg" => "User Created Successfully"]];
                } else {
                    $output = ["head" => ["code" => 400, "msg" => "Failed to Create User"]];
                }
            } else {
                $output = ["head" => ["code" => 400, "msg" => "Email Already Exists"]];
            }
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Invalid Email Format"]];
        }
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Missing Required Fields"]];
    }
}


// Update User
elseif ($action === 'updateUser' && isset($obj->user_id, $obj->User_Name, $obj->Password, $obj->email, $obj->role, $obj->role_id)) {
    $user_id = $obj->user_id;
    $User_Name = $obj->User_Name;
    $Password = $obj->Password;
    $email = $obj->email;
    $role = $obj->role;
    $role_id = $obj->role_id;

    if (
        !empty($User_Name) && !empty($Password) && !empty($email) && !empty($role)
        && !empty($role_id)
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check if user_id exists
            $stmt = $conn->prepare("SELECT * FROM `users` WHERE `user_id` = ? AND `delete_at` = 0");
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $userCheck = $stmt->get_result();

            if ($userCheck->num_rows === 0) {
                $output = ["head" => ["code" => 400, "msg" => "User ID does not exist"]];
            } else {
                // Check if email is already used by another user
                $stmt = $conn->prepare("SELECT * FROM `users` WHERE `email` = ? AND `user_id` != ? AND `delete_at` = 0");
                $stmt->bind_param("ss", $email, $user_id);
                $stmt->execute();
                $emailCheck = $stmt->get_result();

                if ($emailCheck->num_rows > 0) {
                    $output = ["head" => ["code" => 400, "msg" => "Email Already Exists"]];
                } else {
                    // Perform the update
                    $stmt = $conn->prepare("UPDATE `users` SET `User_Name` = ?, `Password` = ?, `email` = ?, `role` = ?, `role_id` = ? WHERE `user_id` = ?");
                    $stmt->bind_param("ssssss", $User_Name, $Password, $email, $role, $role_id, $user_id);

                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $output = ["head" => ["code" => 200, "msg" => "User Updated Successfully"]];
                    } else {
                        $output = ["head" => ["code" => 400, "msg" => "Failed to Update User: No changes made"]];
                    }
                }
            }
        } else {
            $output = ["head" => ["code" => 400, "msg" => "Invalid Email Format"]];
        }
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Missing Required Fields"]];
    }
}

// Delete User
elseif ($action === 'deleteUser' && isset($obj->user_id)) {
    $user_id = $obj->user_id;
    $stmt = $conn->prepare("UPDATE `users` SET `delete_at` = 1 WHERE `user_id` = ?");
    $stmt->bind_param("s", $user_id);

    if ($stmt->execute()) {
        $output = ["head" => ["code" => 200, "msg" => "User Deleted Successfully"]];
    } else {
        $output = ["head" => ["code" => 400, "msg" => "Failed to Delete User"]];
    }
} else {
    $output = ["head" => ["code" => 400, "msg" => "Invalid Action"]];
}

echo json_encode($output, JSON_NUMERIC_CHECK);
