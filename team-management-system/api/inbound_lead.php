<?php
/**
 * Public Inbound Lead Capture API / Webhook
 * 
 * Receives leads directly from Website Contact Forms, Landing Pages,
 * Chatbots, and Marketing campaigns.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Ensure tables exist
ensureSalesTablesExist();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST is required.'
    ]);
    exit;
}

// Support JSON payload or Form data
$inputData = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
}
if (empty($inputData)) {
    $inputData = $_POST;
}

$name = trim($inputData['name'] ?? $inputData['full_name'] ?? '');
$phone = trim($inputData['phone'] ?? $inputData['mobile'] ?? $inputData['contact'] ?? '');
$email = trim($inputData['email'] ?? '');
$city = trim($inputData['city'] ?? $inputData['location'] ?? '');
$graduation = trim($inputData['graduation'] ?? $inputData['qualification'] ?? '');
$courseName = trim($inputData['course'] ?? $inputData['service'] ?? $inputData['course_name'] ?? 'UI/UX Design');
$source = trim($inputData['source'] ?? 'Website Inflow');
$notes = trim($inputData['message'] ?? $inputData['notes'] ?? $inputData['query'] ?? 'Lead captured via website form submission');
$offeringType = (!empty($inputData['offering_type']) && $inputData['offering_type'] === 'service') ? 'service' : 'course';

if (empty($name) || empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Name and Phone number are required fields.'
    ]);
    exit;
}

try {
    $db = getDB();
    
    // Auto-assign to an active Sales Counsellor or unassigned
    $counsellorStmt = $db->query("SELECT id, name FROM users WHERE role = 'sales_counsellor' AND status = 'active' ORDER BY RAND() LIMIT 1");
    $counsellor = $counsellorStmt->fetch(PDO::FETCH_ASSOC);
    $assignedTo = $counsellor ? (int)$counsellor['id'] : null;
    $counsellorName = $counsellor ? $counsellor['name'] : 'Trivikra Tech Team';

    $leadData = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'alternate_phone' => '',
        'city' => $city,
        'graduation' => $graduation,
        'offering_type' => $offeringType,
        'course_service_id' => null,
        'course_service_name' => $courseName,
        'source' => $source,
        'status' => 'new_lead',
        'is_manual' => 0,
        'expected_revenue' => 0,
        'assigned_to' => $assignedTo,
        'next_followup_date' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        'notes' => $notes
    ];

    $leadId = addLead($leadData, $assignedTo ?: 1);

    // Generate formatted WhatsApp message & URL
    $waMsg = getWhatsAppLeadMessage($name, $counsellorName, $courseName, $source, $offeringType);
    $waUrl = getWhatsAppLeadUrl($phone, $name, $counsellorName, $courseName, $source, $offeringType);

    echo json_encode([
        'success' => true,
        'message' => 'Website lead captured successfully!',
        'lead_id' => $leadId,
        'assigned_to' => $counsellorName,
        'whatsapp_message' => $waMsg,
        'whatsapp_url' => $waUrl
    ]);
} catch (Throwable $e) {
    error_log("Inbound Lead API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error while capturing lead.'
    ]);
}
