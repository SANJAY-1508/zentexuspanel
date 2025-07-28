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

// **List Purchases**
if ($action === 'listPurchases') {
    $query = "SELECT * FROM purchase WHERE delete_at = 0 ORDER BY create_at ASC";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $purchases = $result->fetch_all(MYSQLI_ASSOC);
        $response = [
            "head" => ["code" => 200, "msg" => "Success"],
            "body" => ["purchases" => $purchases]
        ];
    } else {
        $response = [
            "head" => ["code" => 200, "msg" => "No Purchases Found"],
            "body" => ["purchases" => []]
        ];
    }
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit();
}

// **Add Purchase**
elseif ($action === 'createPurchase') {
    $company_name = $obj->company_name ?? null;
    $purchase_date = $obj->purchase_date ?? null;
    $company_mobile_number = $obj->company_mobile_number ?? null;
    $company_email = $obj->company_email ?? null;
    $company_address = $obj->company_address ?? null;
    $company_gst_no = $obj->company_gst_no ?? null;
    $company_proof = $obj->company_proof ?? null;
    $company_products = $obj->company_products ?? null;
    $subtotal_without_gst = $obj->subtotal_without_gst ?? null;
    $subtotal_with_gst = $obj->subtotal_with_gst ?? null;
    $overall_total = $obj->overall_total ?? null;
    $discount_type = $obj->discount_type ?? null;
    $discount = $obj->discount ?? null;
    $total = $obj->total ?? null;
    $payment_details = $obj->payment_details ?? null;
    $balance = $obj->balance ?? null;
    $reference = $obj->reference ?? null;
    $paid_by = $obj->paid_by ?? null;

    // Validate required fields
    if (!$company_name || !$purchase_date || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Company Name, Purchase Date, and Company Mobile Number are required"
        ];
    } else {
        // Prepare and execute the insert query
        $stmt = $conn->prepare("INSERT INTO purchase (purchase_date, company_name, company_mobile_number, company_email, company_address, company_gst_no, company_proof, company_products, subtotal_without_gst, subtotal_with_gst, overall_total, discount_type, discount, total, payment_details, balance, reference, paid_by, create_at, delete_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param(
            "ssssssssdddsddsssss",
            $purchase_date,
            $company_name,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof,
            $company_products,
            $subtotal_without_gst,
            $subtotal_with_gst,
            $overall_total,
            $discount_type,
            $discount,
            $total,
            $payment_details,
            $balance,
            $reference,
            $paid_by,
            $timestamp
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;

            // Generate a unique purchase ID
            $purchase_id = uniqueID("purchase", $insertId);

            // Update the purchase record with the generated unique ID
            $stmtUpdate = $conn->prepare("UPDATE purchase SET purchase_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $purchase_id, $insertId);

            if ($stmtUpdate->execute()) {
                $response = [
                    "status" => 200,
                    "message" => "Purchase Added Successfully",
                    "purchase_id" => $purchase_id
                ];
            } else {
                $response = [
                    "status" => 400,
                    "message" => "Failed to update Purchase ID"
                ];
            }

            $stmtUpdate->close();
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Add Purchase. Error: " . $stmt->error
            ];
        }

        $stmt->close();
    }
}

// **Update Purchase**
elseif ($action === 'updatePurchase') {
    $edit_purchase_id = $obj->edit_purchase_id ?? null;
    $company_name = $obj->company_name ?? null;
    $purchase_date = $obj->purchase_date ?? null;
    $company_mobile_number = $obj->company_mobile_number ?? null;
    $company_email = $obj->company_email ?? null;
    $company_address = $obj->company_address ?? null;
    $company_gst_no = $obj->company_gst_no ?? null;
    $company_proof = $obj->company_proof ?? null;
    $company_products = $obj->company_products ?? null;
    $subtotal_without_gst = $obj->subtotal_without_gst ?? null;
    $subtotal_with_gst = $obj->subtotal_with_gst ?? null;
    $overall_total = $obj->overall_total ?? null;
    $discount_type = $obj->discount_type ?? null;
    $discount = $obj->discount ?? null;
    $total = $obj->total ?? null;
    $payment_details = $obj->payment_details ?? null;
    $balance = $obj->balance ?? null;
    $reference = $obj->reference ?? null;
    $paid_by = $obj->paid_by ?? null;

    // Validate required fields
    if (!$edit_purchase_id || !$company_name || !$purchase_date || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Purchase ID, Company Name, Purchase Date, and Company Mobile Number are required"
        ];
    } else {
        $stmt = $conn->prepare("UPDATE purchase SET company_name = ?, purchase_date = ?, company_mobile_number = ?, company_email = ?, company_address = ?, company_gst_no = ?, company_proof = ?, company_products = ?, subtotal_without_gst = ?, subtotal_with_gst = ?, overall_total = ?, discount_type = ?, discount = ?, total = ?, payment_details = ?, balance = ?, reference = ?, paid_by = ? WHERE purchase_id = ?");
        $stmt->bind_param(
            "ssssssssdddsddsssss",
            $company_name,
            $purchase_date,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof,
            $company_products,
            $subtotal_without_gst,
            $subtotal_with_gst,
            $overall_total,
            $discount_type,
            $discount,
            $total,
            $payment_details,
            $balance,
            $reference,
            $paid_by,
            $edit_purchase_id
        );

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Purchase Updated Successfully"
            ];
        } else {
            $response = [
                "status" => 400,
                "message" => "Failed to Update Purchase. Error: " . $stmt->error
            ];
        }
        $stmt->close();
    }
}

// **Delete Purchase**
elseif ($action === 'deletePurchase') {
    $delete_purchase_id = $obj->delete_purchase_id ?? null;

    if ($delete_purchase_id) {
        $stmt = $conn->prepare("UPDATE purchase SET delete_at = 1 WHERE purchase_id = ?");
        $stmt->bind_param("s", $delete_purchase_id);

        if ($stmt->execute()) {
            $response = [
                "head" => ["code" => 200, "msg" => "Purchase Deleted Successfully"]
            ];
        } else {
            $response = [
                "head" => ["code" => 400, "msg" => "Failed to Delete Purchase. Error: " . $stmt->error]
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
