<?php
class RequestProductController
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

    public function handlePost()
    {
        $result = ['success' => '', 'error' => ''];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $productTypeId = $_POST['product_type_id'] ?? 0;
        $requestedName = trim($_POST['requested_name'] ?? '');
        $requestedDomain = trim($_POST['requested_domain'] ?? '');
        $additionalInfo = trim($_POST['additional_info'] ?? '');

        if (empty($productTypeId) || empty($requestedName)) {
            $result['error'] = 'Vul alle verplichte velden in';
        } else {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO product_requests (user_id, product_type_id, requested_name, requested_domain, additional_info, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");

                if ($stmt->execute([$this->userId, $productTypeId, $requestedName, $requestedDomain, $additionalInfo])) {
                    $result['success'] = 'Product aanvraag succesvol ingediend. U ontvangt bericht zodra deze is verwerkt.';
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het indienen van de aanvraag';
                }
            } catch (Exception $e) {
                $result['error'] = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }

        return $result;
    }

    public function index()
    {
        $data = [];
        
        $data['productTypes'] = $this->productModel->getProductTypes();

        // Get user's pending requests
        $stmt = $this->db->prepare("
            SELECT pr.*, pt.name as type_name
            FROM product_requests pr
            JOIN product_types pt ON pr.product_type_id = pt.id
            WHERE pr.user_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$this->userId]);
        $data['requests'] = $stmt->fetchAll();

        return $data;
    }
}
