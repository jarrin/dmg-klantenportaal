<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$productTypeId = $_GET['id'] ?? 0;

if (empty($productTypeId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product type ID required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, description, default_duration_months FROM product_types WHERE id = ?");
    $stmt->execute([$productTypeId]);
    $productType = $stmt->fetch();
    
    if (!$productType) {
        http_response_code(404);
        echo json_encode(['error' => 'Product type not found']);
        exit;
    }
    
    echo json_encode($productType);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
