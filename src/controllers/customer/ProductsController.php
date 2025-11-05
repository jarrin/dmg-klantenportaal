<?php
class CustomerProductsController
{
    private $db;
    private $productModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Product.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->productModel = new Product();
        $this->userId = $userId;
    }

    public function index()
    {
        $data = [];
        
        // Pagination setup
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 12;

        // Count total products for this user
        $countQuery = "SELECT COUNT(*) FROM products WHERE user_id = ?";
        $data['paginator'] = Paginator::fromQuery($this->db, $countQuery, [$this->userId], $perPage, $page);

        // Get products with pagination
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name
            FROM products p
            LEFT JOIN product_types pt ON p.product_type_id = pt.id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            " . $data['paginator']->getLimitClause()
        );
        $stmt->execute([$this->userId]);
        $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['perPage'] = $perPage;

        return $data;
    }
}
