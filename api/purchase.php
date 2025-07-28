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

// Function to handle Base64 PDF saving
function saveBase64PDF($base64String, $uploadDir = 'uploads/')
{

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }


    $base64String = preg_replace('#^data:application/pdf;base64,#', '', $base64String);
    $base64String = str_replace(' ', '+', $base64String);
    $decodedData = base64_decode($base64String);

    if ($decodedData === false) {
        return ["success" => false, "message" => "Invalid Base64 data"];
    }

    // Generate a unique file name
    $fileName = 'pdf_' . uniqid() . '.pdf';
    $filePath = $uploadDir . $fileName;

    // Save the file
    if (file_put_contents($filePath, $decodedData)) {
        return ["success" => true, "filePath" => $filePath];
    } else {
        return ["success" => false, "message" => "Failed to save PDF file"];
    }
}

// **List Purchases**
if ($action === 'listPurchases') {
    $query = "SELECT * FROM purchase WHERE delete_at = 0 ORDER BY create_at ASC";
    $result = $conn->query($query);
    $baseUrl = 'http://localhost/zentexuspanel/api/';

    if ($result && $result->num_rows > 0) {
        $purchases = $result->fetch_all(MYSQLI_ASSOC);


        foreach ($purchases as &$purchase) {
            if (!empty($purchase['company_proof'])) {
                $purchase['company_proof'] = $baseUrl . $purchase['company_proof'];
            }
        }
        unset($purchase);

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
    $company_name = $obj['company_name'] ?? null;
    $purchase_date = $obj['purchase_date'] ?? null;
    $company_mobile_number = $obj['company_mobile_number'] ?? null;
    $company_email = $obj['company_email'] ?? null;
    $company_address = $obj['company_address'] ?? null;
    $company_gst_no = $obj['company_gst_no'] ?? null;
    $company_proof = $obj['company_proof'] ?? null;
    $company_products = $obj['company_products'] ?? null;
    $subtotal_without_gst = $obj['subtotal_without_gst'] ?? null;
    $subtotal_with_gst = $obj['subtotal_with_gst'] ?? null;
    $overall_total = $obj['overall_total'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;
    $total = $obj['total'] ?? null;
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
        $stmt = $conn->prepare("INSERT INTO purchase (purchase_date, company_name, company_mobile_number, company_email, company_address, company_gst_no, company_proof, company_products, subtotal_without_gst, subtotal_with_gst, overall_total, discount_type, discount, total, payment_details, balance, reference, paid_by, create_at, delete_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param(
            "ssssssssdddsddsssss",
            $purchase_date,
            $company_name,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof_path,
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
    $company_products = $obj['company_products'] ?? null;
    $subtotal_without_gst = $obj['subtotal_without_gst'] ?? null;
    $subtotal_with_gst = $obj['subtotal_with_gst'] ?? null;
    $overall_total = $obj['overall_total'] ?? null;
    $discount_type = $obj['discount_type'] ?? null;
    $discount = $obj['discount'] ?? null;
    $total = $obj['total'] ?? null;
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
        $stmt = $conn->prepare("UPDATE purchase SET company_name = ?, purchase_date = ?, company_mobile_number = ?, company_email = ?, company_address = ?, company_gst_no = ?, company_proof = COALESCE(?, company_proof), company_products = ?, subtotal_without_gst = ?, subtotal_with_gst = ?, overall_total = ?, discount_type = ?, discount = ?, total = ?, payment_details = ?, balance = ?, reference = ?, paid_by = ? WHERE purchase_id = ?");
        $stmt->bind_param(
            "ssssssssdddsddsssss",
            $company_name,
            $purchase_date,
            $company_mobile_number,
            $company_email,
            $company_address,
            $company_gst_no,
            $company_proof_path,
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
        // Optionally, delete the associated PDF file
        $stmtOld = $conn->prepare("SELECT company_proof FROM purchase WHERE purchase_id = ?");
        $stmtOld->bind_param("s", $delete_purchase_id);
        $stmtOld->execute();
        $resultOld = $stmtOld->get_result();
        if ($resultOld->num_rows > 0) {
            $oldProof = $resultOld->fetch_assoc()['company_proof'];
            if ($oldProof && file_exists($oldProof)) {
                unlink($oldProof); // Delete the file
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
