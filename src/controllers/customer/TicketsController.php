<?php
class CustomerTicketsController
{
    private $db;
    private $ticketModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Ticket.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->ticketModel = new Ticket();
        $this->userId = $userId;
    }

    public function handlePost()
{
    $result = ['success' => '', 'error' => '', 'redirect' => false, 'ticketId' => null];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $result;
    }

    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = 'medium'; // Default priority for customers
    $attachmentPath = null; // Default: no file

    if (empty($subject) || empty($message)) {
        $result['error'] = 'Vul alle verplichte velden in';
        return $result;
    }

    if (!empty($_FILES['attachment']['name'])) {
        $allowedTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $maxSize = 5 * 1024 * 1024;

        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = 'Fout bij het uploaden van het bestand.';
            return $result;
        }

        if ($file['size'] > $maxSize) {
            $result['error'] = 'Bestand is te groot (max. 5MB).';
            return $result;
        }

        if (!in_array($file['type'], $allowedTypes)) {
            $result['error'] = 'Bestandstype niet toegestaan.';
            return $result;
        }

        $uploadDir = __DIR__ . '/../../uploads/tickets/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename = uniqid('ticket_', true) . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentPath = '/uploads/tickets/' . $filename;
        } else {
            $result['error'] = 'Er is een fout opgetreden bij het opslaan van de bijlage.';
            return $result;
        }
    }

    try {
        $ticketId = $this->ticketModel->create($this->userId, $subject, $message, $priority, $attachmentPath);
        $result['success'] = 'Ticket succesvol aangemaakt.';
        $result['redirect'] = true;
        $result['ticketId'] = $ticketId;
    } catch (Exception $e) {
        $result['error'] = 'Er is een fout opgetreden bij het aanmaken van het ticket.';
    }

    return $result;
}

    public function index()
    {
        $data = [];
        
        // Pagination setup
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 10;

        // Count total tickets for this user
        $countQuery = "SELECT COUNT(*) FROM tickets WHERE user_id = ?";
        $data['paginator'] = Paginator::fromQuery($this->db, $countQuery, [$this->userId], $perPage, $page);

        // Get tickets with pagination
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            " . $data['paginator']->getLimitClause()
        );
        $stmt->execute([$this->userId]);
        $data['tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['perPage'] = $perPage;

        return $data;
    }
}
