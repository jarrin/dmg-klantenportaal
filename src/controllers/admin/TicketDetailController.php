<?php
class TicketDetailController
{
    private $db;
    private $ticketModel;
    private $auth;

    public function __construct($auth)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Ticket.php';

        $this->db = Database::getInstance()->getConnection();
        $this->ticketModel = new Ticket();
        $this->auth = $auth;
    }

    public function handlePost($ticketId)
    {
        $result = ['success' => '', 'error' => '', 'redirect' => false];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'reply') {
            $message = trim($_POST['message'] ?? '');

            if (empty($message)) {
                $result['error'] = 'Vul een bericht in';
            } else {
                if ($this->ticketModel->addMessage($ticketId, $this->auth->getCurrentUserId(), $message, true)) {
                    // Set status to in_progress if it was new
                    $ticket = $this->ticketModel->getById($ticketId);
                    if ($ticket['status'] === 'new') {
                        $this->ticketModel->updateStatus($ticketId, 'in_progress');
                    }
                    $result['redirect'] = true;
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het verzenden van het antwoord';
                }
            }
        } elseif ($action === 'update_status') {
            $status = $_POST['status'] ?? '';

            if ($this->ticketModel->updateStatus($ticketId, $status)) {
                $result['redirect'] = true;
            } else {
                $result['error'] = 'Er is een fout opgetreden bij het bijwerken van de status';
            }
        } elseif ($action === 'update_priority') {
            $priority = $_POST['priority'] ?? '';

            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
                $result['error'] = 'Ongeldige prioriteit';
            } else {
                $stmt = $this->db->prepare("UPDATE tickets SET priority = ? WHERE id = ?");
                if ($stmt->execute([$priority, $ticketId])) {
                    $result['redirect'] = true;
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het bijwerken van de prioriteit';
                }
            }
        }

        return $result;
    }

    public function show($ticketId)
    {
        $data = [];
        
        $data['ticket'] = $this->ticketModel->getById($ticketId);
        
        if (!$data['ticket']) {
            return null;
        }

        $data['messages'] = $this->ticketModel->getMessages($ticketId);

        return $data;
    }
}
