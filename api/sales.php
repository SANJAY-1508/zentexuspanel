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
$obj = json_decode($json, true);
$output = array();

date_default_timezone_set('Asia/Calcutta');
$timestamp = date('Y-m-d H:i:s');

if (!isset($obj['action'])) {
    echo json_encode(["head" => ["code" => 400, "msg" => "Action parameter is missing"]]);
    exit();
}

$action = $obj['action'];

// **List Sales**
if ($action === 'listSales') {
    $filters = [
        'company_name' => $obj->company_name ?? '',
        'company_mobile_number' => $obj->company_mobile_number ?? '',
        'company_address' => $obj->company_address ?? '',
        'company_email' => $obj->company_email ?? '',
    ];

    $query = "SELECT * FROM sales WHERE delete_at = 0";
    $params = [];
    $types = '';
    foreach ($filters as $field => $value) {
        if ($value !== '') {
            $query .= " AND $field LIKE ?";
            $params[] = "%$value%";
            $types .= 's';
        }
    }

    $query .= " ORDER BY create_at ASC";
    $stmt = $conn->prepare($query);

    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $sales = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode([
        'head' => ['code' => 200, 'msg' => $sales ? 'Success' : 'No Sales Found'],
        'body' => ['sales' => $sales]
    ], JSON_NUMERIC_CHECK);
    exit;
}

// **Add Sale**
elseif ($action === 'createSale') {
    $sales_date = $obj['sales_date'] ?? null;
    $company_name = $obj['company_name'] ?? null;
    $company_mobile_number = $obj['company_mobile_number'] ?? null;
    $company_email = $obj['company_email'] ?? null;
    $company_address = $obj['company_address'] ?? null;
    $company_gst_no = $obj['company_gst_no'] ?? null;
    $payment_terms = $obj['payment_terms'] ?? null;
    $products = $obj['products'] ?? null;
    $sub_total = $obj['sub_total'] ?? null;
    $gst_type = $obj['gst_type'] ?? null;
    $gst_amount = $obj['gst_amount'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;
    $total = $obj['total'] ?? null;


    if (!$sales_date || !$company_name || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Sales Date, Company Name, and Company Mobile Number are required"
        ];
    } else {
        // Prepare and execute the insert query
        $stmt = $conn->prepare("INSERT INTO sales (sales_date, company_name, company_mobile_number, company_email, company_address, company_gst_no,payment_terms, products, sub_total, gst_type, gst_amount, discount_type, discount, total, create_at, delete_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param(
            "ssssssssdsdsdds",
            $sales_date,
            $company_name,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $payment_terms,
            $products,
            $sub_total,
            $gst_type,
            $gst_amount,
            $discount_type,
            $discount,
            $total,
            $timestamp
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            $sales_id = uniqueID("sales", $insertId);

            $stmtUpdate = $conn->prepare("UPDATE sales SET sales_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $sales_id, $insertId);

            if ($stmtUpdate->execute()) {
                $response = [
                    "status" => 200,
                    "message" => "Sale Added Successfully",
                    "sales_id" => $sales_id
                ];
            } else {
                $response = [
                    "status" => 400,
                    "message" => "Failed to update Sales ID"
                ];
            }

            $stmtUpdate->close();
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Sale. Error: " . $stmt->error
            ];
        }

        $stmt->close();
    }
}

// **Update Sale**
elseif ($action === 'updateSale') {
    $edit_sales_id = $obj['edit_sales_id'] ?? null;
    $sales_date = $obj['sales_date'] ?? null;
    $company_name = $obj['company_name'] ?? null;
    $company_mobile_number = $obj['company_mobile_number'] ?? null;
    $company_email = $obj['company_email'] ?? null;
    $company_address = $obj['company_address'] ?? null;
    $company_gst_no = $obj['company_gst_no'] ?? null;
    $payment_terms = $obj['payment_terms'] ?? null;
    $products = $obj['products'] ?? null;
    $sub_total = $obj['sub_total'] ?? null;
    $gst_type = $obj['gst_type'] ?? null;
    $gst_amount = $obj['gst_amount'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;
    $total = $obj['total'] ?? null;

    // Validate required fields
    if (!$edit_sales_id || !$sales_date || !$company_name || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Sales ID, Sales Date, Company Name, and Company Mobile Number are required"
        ];
    } else {
        // Prepare and execute the update query
        $stmt = $conn->prepare("UPDATE sales SET sales_date = ?, company_name = ?, company_mobile_number = ?, company_email = ?, company_address = ?, company_gst_no = ?,payment_terms = ?, products = ?, sub_total = ?, gst_type = ?, gst_amount = ?, discount_type = ?, discount = ?, total = ? WHERE sales_id = ?");
        $stmt->bind_param(
            "ssssssssdsdsdds",
            $sales_date,
            $company_name,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $payment_terms,
            $products,
            $sub_total,
            $gst_type,
            $gst_amount,
            $discount_type,
            $discount,
            $total,
            $edit_sales_id
        );

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Sale Updated Successfully"
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Sale. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    }
}

// **Delete Sale**
elseif ($action === 'deleteSale') {
    $delete_sales_id = $obj['delete_sales_id'] ?? null;

    if ($delete_sales_id) {
        $stmt = $conn->prepare("UPDATE sales SET delete_at = 1 WHERE sales_id = ?");
        $stmt->bind_param("s", $delete_sales_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Sale Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Sale. Error: " . $stmt->error]
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
