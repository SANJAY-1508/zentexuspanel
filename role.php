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

// **List Roles**
if ($action === 'listRoles') {
    $query = "SELECT * FROM role WHERE delete_at = 0 ORDER BY create_at ASC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $roles = $result->fetch_all(MYSQLI_ASSOC);
        $response = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["roles" => $roles]
        ];
    } else {
        $response = [
            "head" => ["code" => 200, "msg" => "No Roles Found"],
            "body" => ["roles" => []]
        ];
    }
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit();
}

// **Add Role**
elseif ($action === 'createRole') {
    $role = $obj->role ?? null;

    if ($role) {
        // Prepare and execute the insert query for the role
        $stmt = $conn->prepare("INSERT INTO role (role, create_at, delete_at) VALUES (?, ?, 0)");
        $stmt->bind_param("ss", $role, $timestamp);

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;

            // Generate a unique role ID
            $role_id = uniqueID("role", $insertId);

            // Update the role record with the generated unique ID
            $stmtUpdate = $conn->prepare("UPDATE role SET role_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $role_id, $insertId);

            if ($stmtUpdate->execute()) {
                $response = [
                    "status" => 200,
                    "message" => "Role Added Successfully",
                    "role_id" => $role_id
                ];
            } else {
                $response = [
                    "status" => 400,
                    "message" => "Failed to update Role ID"
                ];
            }

            $stmtUpdate->close();
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Role. Error: " . $stmt->error
            ];
        }

        $stmt->close();
    } else {
        $response = [
            "status" => 400,
            "message" => "Role Name is required"
        ];
    }
}

// **Update Role**
elseif ($action === 'updateRole') {
    $edit_role_id = $obj->edit_role_id ?? null;
    $role = $obj->role ?? null;

    if ($edit_role_id && $role) {
        $stmt = $conn->prepare("UPDATE role SET role = ? WHERE role_id = ?");
        $stmt->bind_param("ss", $role, $edit_role_id);

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Role Updated Successfully"
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Role. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "status" => 400,
            "message" => "Missing or Invalid Parameters"
        ];
    }
}

// **Delete Role**
elseif ($action === 'deleteRole') {
    $delete_role_id = $obj->delete_role_id ?? null;

    if ($delete_role_id) {
        $stmt = $conn->prepare("UPDATE role SET delete_at = 1 WHERE role_id = ?");
        $stmt->bind_param("s", $delete_role_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Role Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Role. Error: " . $stmt->error]
            ];
        }
        $stmt->close();
    } else {
        $response = [
            "head" => ["code" => 400, "msg" => "Missing or Invalid Parameters"]
        ];
    }
}

// **Invalid Action**
else {
    $response = [
        "head" => ["code" => 400, "msg" => "Invalid Action"]
    ];
}

// Close Database Connection
$conn->close();

// Return JSON Response
echo json_encode($response, JSON_NUMERIC_CHECK);
