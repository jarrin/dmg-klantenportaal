<?php
class DashboardController
{
    private $db;
    private $userModel;
    private $productModel;
    private $ticketModel;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/User.php';
        require_once __DIR__ . '/../../classes/Product.php';
        require_once __DIR__ . '/../../classes/Ticket.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->userModel = new User();
        $this->productModel = new Product();
        $this->ticketModel = new Ticket();
    }

    public function index()
    {
        $data = [];
        
        // Get statistics
        $data['totalUsers'] = count($this->userModel->getAll('customer'));
        $data['totalProducts'] = count($this->productModel->getAll());
        $data['ticketStats'] = $this->ticketModel->getStatistics();

        // Pagination for expiring products
        $expiringPage = isset($_GET['expiring_page']) ? max(1, (int)$_GET['expiring_page']) : 1;
        $expiringPerPage = 10;

        // Count expiring products
        $countExpiringQuery = "SELECT COUNT(*) FROM products WHERE expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) AND expiry_date >= CURRENT_DATE AND status = 'active'";
        $data['expiringPaginator'] = Paginator::fromQuery($this->db, $countExpiringQuery, [], $expiringPerPage, $expiringPage);

        // Get expiring products with pagination
        $stmt = $this->db->prepare("
            SELECT p.*, u.first_name, u.last_name 
            FROM products p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) 
            AND p.expiry_date >= CURRENT_DATE 
            AND p.status = 'active'
            ORDER BY p.expiry_date ASC 
            " . $data['expiringPaginator']->getLimitClause()
        );
        $stmt->execute();
        $data['expiringProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }
}
