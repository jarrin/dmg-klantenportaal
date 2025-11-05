<?php
class CancelProductController
{
    private $db;
    private $productModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Product.php';

        $this->db = Database::getInstance()->getConnection();
        $this->productModel = new Product();
        $this->userId = $userId;
    }

    public function handlePost($productId)
    {
        $result = ['success' => '', 'error' => '', 'redirect' => false];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $result['error'] = 'Geef alstublieft een reden op voor de opzegging';
        } else {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO cancellation_requests (user_id, product_id, reason, status)
                    VALUES (?, ?, ?, 'pending')
                ");

                if ($stmt->execute([$this->userId, $productId, $reason])) {
                    $result['success'] = 'Opzegverzoek succesvol ingediend. U ontvangt bericht zodra dit is verwerkt.';
                    $result['redirect'] = true;
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het indienen van het opzegverzoek';
                }
            } catch (Exception $e) {
                $result['error'] = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }

        return $result;
    }

    public function show($productId)
    {
        $product = $this->productModel->getById($productId);

        // Check if product exists and belongs to user
        if (!$product || $product['user_id'] != $this->userId) {
            return null;
        }

        return $product;
    }
}
