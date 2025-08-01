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

// **List Performa**
if ($action === 'listPerforma') {
    $filters = [
        'company_name' => 'company_name',
        'company_mobile_number' => 'company_mobile_number',
        'company_address' => 'company_address',
        'company_email' => 'company_email',
    ];

    $result = fetchPaginatedRecords($conn, 'performa', $filters, $obj);

    echo json_encode([
        'head' => ['code' => 200, 'msg' => $result['records'] ? 'Success' : 'No Performa Found'],
        'body' => [
            'performa' => $result['records'],
            'totalRecords' => $result['totalRecords']
        ]
    ], JSON_NUMERIC_CHECK);
    exit();
}


// **Add Performa**
elseif ($action === 'createPerforma') {
    $performa_date = $obj['performa_date'] ?? null;
    $performa_due_date = $obj['performa_due_date'] ?? null;
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

    if (!$performa_date || !$company_name  || !$performa_due_date || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Performa Date, Company Name,Performa Due Date  and Company Mobile Number are required"
        ];
    } else {
        $performa_invoice_no = generatePerformaInvoiceNo($conn);

        // Prepare and execute the insert query
        $stmt = $conn->prepare("INSERT INTO performa (performa_date, performa_due_date, company_name, company_mobile_number, company_email, company_address, company_gst_no, payment_terms, products, sub_total, gst_type, gst_amount, discount_type, discount, total, performa_invoice_no, create_at, delete_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param(
            "sssssssssdsdsddss",
            $performa_date,
            $performa_due_date,
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
            $performa_invoice_no,
            $timestamp
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            $performa_id = uniqueID("performa", $insertId);

            $stmtUpdate = $conn->prepare("UPDATE performa SET performa_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $performa_id, $insertId);

            if ($stmtUpdate->execute()) {
                $response = [
                    "status" => 200,
                    "message" => "Performa Added Successfully",
                    "performa_id" => $performa_id
                ];
            } else {
                $response = [
                    "status" => 400,
                    "message" => "Failed to update Performa ID"
                ];
            }

            $stmtUpdate->close();
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Performa. Error: " . $stmt->error
            ];
        }

        $stmt->close();
    }
}

// **Update Performa**
elseif ($action === 'updatePerforma') {
    $edit_performa_id = $obj['edit_performa_id'] ?? null;
    $performa_date = $obj['performa_date'] ?? null;
    $performa_due_date = $obj['performa_due_date'] ?? null;
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
    if (!$edit_performa_id || !$performa_date || !$performa_due_date || !$company_name || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Performa ID, Performa Date,Performa Due Date ,Company Name, and Company Mobile Number are required"
        ];
    } else {
        // Prepare and execute the update query
        $stmt = $conn->prepare("UPDATE performa SET performa_date = ?, performa_due_date = ?, company_name = ?, company_mobile_number = ?, company_email = ?, company_address = ?, company_gst_no = ?, payment_terms = ?, products = ?, sub_total = ?, gst_type = ?, gst_amount = ?, discount_type = ?, discount = ?, total = ? WHERE performa_id = ?");
        $stmt->bind_param(
            "sssssssssdsdsdds",
            $performa_date,
            $performa_due_date,
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
            $edit_performa_id
        );

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Performa Updated Successfully"
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Performa. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    }
}

// **Delete Performa**
elseif ($action === 'deletePerforma') {
    $delete_performa_id = $obj['delete_performa_id'] ?? null;

    if ($delete_performa_id) {
        $stmt = $conn->prepare("UPDATE performa SET delete_at = 1 WHERE performa_id = ?");
        $stmt->bind_param("s", $delete_performa_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Performa Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Performa. Error: " . $stmt->error]
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
