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

// **List Purchases**
if ($action === 'listPurchases') {
    $filters = [
        'company_name' => $obj['company_name'] ?? '',
        'company_mobile_number' => $obj['company_mobile_number'] ?? '',
        'company_address' => $obj['company_address'] ?? '',
        'company_email' => $obj['company_email'] ?? '',
    ];

    $page = isset($obj['page']) ? max(1, (int)$obj['page']) : 1;
    $limit = isset($obj['limit']) ? max(1, (int)$obj['limit']) : 10;
    $offset = ($page - 1) * $limit;

    // Count total records for pagination
    $countQuery = "SELECT COUNT(*) as total FROM purchase WHERE delete_at = 0";
    $countParams = [];
    $countTypes = '';
    foreach ($filters as $field => $value) {
        if ($value !== '') {
            $countQuery .= " AND $field LIKE ?";
            $countParams[] = "%$value%";
            $countTypes .= 's';
        }
    }

    $countStmt = $conn->prepare($countQuery);
    if ($countParams) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

    // Fetch paginated records
    $query = "SELECT * FROM purchase WHERE delete_at = 0";
    $params = [];
    $types = '';
    foreach ($filters as $field => $value) {
        if ($value !== '') {
            $query .= " AND $field LIKE ?";
            $params[] = "%$value%";
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
    $purchases = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $baseUrl = 'http://localhost/zentexuspanel/api/';
    foreach ($purchases as &$purchase) {
        if (!empty($purchase['company_proof'])) {
            $purchase['company_proof'] = $baseUrl . $purchase['company_proof'];
        }
    }
    unset($purchase);

    echo json_encode([
        'head' => ['code' => 200, 'msg' => $purchases ? 'Success' : 'No Purchases Found'],
        'body' => [
            'purchases' => $purchases,
            'totalRecords' => $totalRecords
        ]
    ], JSON_NUMERIC_CHECK);
    exit();
}

// **Add Purchase**
elseif ($action === 'createPurchase') {
    $company_name = $obj['company_name'] ?? null;
    $purchase_date = $obj['purchase_date'] ?? null;
    $company_mobile_number = $obj['company_mobile_number'] ?? null;
    $company_email = $obj['company_email'] ?? null;
    $company_address = $obj['company_address'] ?? null;
    $company_gst_no = $obj['company_gst_no'] ?? null;
    $company_proof = $obj['company_proof'] ?? null;
    $products = $obj['products'] ?? null;
    $subtotal_without_gst = $obj['subtotal_without_gst'] ?? null;
    $subtotal_with_gst = $obj['subtotal_with_gst'] ?? null;
    $overall_total = $obj['overall_total'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;

    $payment_details = $obj['payment_details'] ?? null;
    $balance = $obj['balance'] ?? null;
    $reference = $obj['reference'] ?? null;
    $paid_by = $obj['paid_by'] ?? null;

    // Validate required fields
    if (!$company_name || !$purchase_date || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Company Name, Purchase Date, and Company Mobile Number are required"
        ];
    } else {
        // Handle Base64 PDF
        $company_proof_path = null;
        if ($company_proof) {
            $pdfResult = saveBase64PDF($company_proof);
            if ($pdfResult['success']) {
                $company_proof_path = $pdfResult['filePath'];
            } else {
                $response = [
                    "status" => 400,
                    "message" => $pdfResult['message']
                ];
                echo json_encode($response, JSON_NUMERIC_CHECK);
                exit();
            }
        }

        // Prepare and execute the insert query
        $stmt = $conn->prepare("INSERT INTO purchase (purchase_date, company_name, company_mobile_number, company_email, company_address, company_gst_no, company_proof, products, subtotal_without_gst, subtotal_with_gst, overall_total, discount_type, discount, payment_details, balance, reference, paid_by, create_at, delete_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param(
            "ssssssssdddsdsssss",
            $purchase_date,
            $company_name,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof_path,
            $products,
            $subtotal_without_gst,
            $subtotal_with_gst,
            $overall_total,
            $discount_type,
            $discount,

            $payment_details,
            $balance,
            $reference,
            $paid_by,
            $timestamp
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            $purchase_id = uniqueID("purchase", $insertId);

            $stmtUpdate = $conn->prepare("UPDATE purchase SET purchase_id = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $purchase_id, $insertId);

            if ($stmtUpdate->execute()) {
                $response = [
                    "status" => 200,
                    "message" => "Purchase Added Successfully",
                    "purchase_id" => $purchase_id,
                    "company_proof_path" => $company_proof_path
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
    $edit_purchase_id = $obj['edit_purchase_id'] ?? null;
    $company_name = $obj['company_name'] ?? null;
    $purchase_date = $obj['purchase_date'] ?? null;
    $company_mobile_number = $obj['company_mobile_number'] ?? null;
    $company_email = $obj['company_email'] ?? null;
    $company_address = $obj['company_address'] ?? null;
    $company_gst_no = $obj['company_gst_no'] ?? null;
    $company_proof = $obj['company_proof'] ?? null;
    $products = $obj['products'] ?? null;
    $subtotal_without_gst = $obj['subtotal_without_gst'] ?? null;
    $subtotal_with_gst = $obj['subtotal_with_gst'] ?? null;
    $overall_total = $obj['overall_total'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;

    $payment_details = $obj['payment_details'] ?? null;
    $balance = $obj['balance'] ?? null;
    $reference = $obj['reference'] ?? null;
    $paid_by = $obj['paid_by'] ?? null;

    // Validate required fields
    if (!$edit_purchase_id || !$company_name || !$purchase_date || !$company_mobile_number) {
        $response = [
            "status" => 400,
            "message" => "Purchase ID, Company Name, Purchase Date, and Company Mobile Number are required"
        ];
    } else {
        // Handle Base64 PDF
        $company_proof_path = null;
        if ($company_proof) {
            $pdfResult = saveBase64PDF($company_proof);
            if ($pdfResult['success']) {
                $company_proof_path = $pdfResult['filePath'];

                $stmtOld = $conn->prepare("SELECT company_proof FROM purchase WHERE purchase_id = ?");
                $stmtOld->bind_param("s", $edit_purchase_id);
                $stmtOld->execute();
                $resultOld = $stmtOld->get_result();
                if ($resultOld->num_rows > 0) {
                    $oldProof = $resultOld->fetch_assoc()['company_proof'];
                    if ($oldProof && file_exists($oldProof)) {
                        unlink($oldProof);
                    }
                }
                $stmtOld->close();
            } else {
                $response = [
                    "status" => 400,
                    "message" => $pdfResult['message']
                ];
                echo json_encode($response, JSON_NUMERIC_CHECK);
                exit();
            }
        }

        // Prepare and execute the update query
        $stmt = $conn->prepare("UPDATE purchase SET company_name = ?, purchase_date = ?, company_mobile_number = ?, company_email = ?, company_address = ?, company_gst_no = ?, company_proof = COALESCE(?, company_proof), products = ?, subtotal_without_gst = ?, subtotal_with_gst = ?, overall_total = ?, discount_type = ?, discount = ?, payment_details = ?, balance = ?, reference = ?, paid_by = ? WHERE purchase_id = ?");
        $stmt->bind_param(
            "ssssssssdddsdsssss",
            $company_name,
            $purchase_date,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof_path,
            $products,
            $subtotal_without_gst,
            $subtotal_with_gst,
            $overall_total,
            $discount_type,
            $discount,

            $payment_details,
            $balance,
            $reference,
            $paid_by,
            $edit_purchase_id
        );

        if ($stmt->execute()) {
            $response = [
                "status" => 200,
                "message" => "Purchase Updated Successfully",
                "company_proof_path" => $company_proof_path
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
    $delete_purchase_id = $obj['delete_purchase_id'] ?? null;

    if ($delete_purchase_id) {
        $stmtOld = $conn->prepare("SELECT company_proof FROM purchase WHERE purchase_id = ?");
        $stmtOld->bind_param("s", $delete_purchase_id);
        $stmtOld->execute();
        $resultOld = $stmtOld->get_result();
        if ($resultOld->num_rows > 0) {
            $oldProof = $resultOld->fetch_assoc()['company_proof'];
            if ($oldProof && file_exists($oldProof)) {
                unlink($oldProof);
            }
        }
        $stmtOld->close();

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
