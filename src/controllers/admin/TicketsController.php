<?php
class TicketsController
{
    private $db;
    private $ticketModel;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Ticket.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->ticketModel = new Ticket();
    }

    public function handlePost()
    {
        $result = ['success' => '', 'error' => ''];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'update_status') {
            $ticketId = $_POST['ticket_id'] ?? 0;
            $status = $_POST['status'] ?? '';

            if ($this->ticketModel->updateStatus($ticketId, $status)) {
                $result['success'] = 'Ticket status bijgewerkt';
            } else {
                $result['error'] = 'Er is een fout opgetreden bij het bijwerken van de status';
            }
        }

        return $result;
    }

    public function index()
    {
        $data = [];
        
        // Pagination setup
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 15;

        // Count total tickets
        $countQuery = "SELECT COUNT(*) FROM tickets";
        $data['paginator'] = Paginator::fromQuery($this->db, $countQuery, [], $perPage, $page);

        // Get tickets with pagination
        $stmt = $this->db->prepare("
            SELECT 
                t.*, 
                u.first_name, 
                u.last_name, 
                u.email,
                (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY 
                CASE 
                    WHEN t.status = 'new' THEN 1
                    WHEN t.status = 'in_progress' THEN 2
                    WHEN t.status = 'closed' THEN 3
                END,
                t.created_at DESC
            " . $data['paginator']->getLimitClause()
        );
        $stmt->execute();
        $data['tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['stats'] = $this->ticketModel->getStatistics();
        $data['perPage'] = $perPage;

        return $data;
    }
}
