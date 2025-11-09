<?php
// Simple API endpoint to add a staff reply to a ticket and notify the customer.
// Authentication: API key via X-API-KEY header or Authorization: Bearer <key>

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Ticket.php';

header('Content-Type: application/json; charset=utf-8');

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// Read API key from header
$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
        $headers[$name] = $v;
    }
}

$providedKey = '';
if (!empty($headers['X-Api-Key'])) {
    $providedKey = $headers['X-Api-Key'];
} elseif (!empty($headers['Authorization']) && preg_match('#Bearer\s+(\S+)#i', $headers['Authorization'], $m)) {
    $providedKey = $m[1];
}

if (empty(API_KEY) || $providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    // also accept form-encoded
    $input = $_POST;
}

$ticketId = isset($input['ticket_id']) ? (int)$input['ticket_id'] : 0;
$staffId = isset($input['staff_user_id']) ? (int)$input['staff_user_id'] : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

$errors = [];
if ($ticketId <= 0) $errors[] = 'ticket_id is required';
if ($staffId <= 0) $errors[] = 'staff_user_id is required';
if ($message === '') $errors[] = 'message is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Validation failed', 'details' => $errors]);
    exit;
}

try {
    $ticket = new Ticket();
    $ok = $ticket->addMessage($ticketId, $staffId, $message, true);
    if ($ok) {
        echo json_encode(['ok' => true, 'message' => 'Reply added and notification sent (best-effort)']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to add message']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
