<?php
class CustomerTicketDetailController
{
    private $ticketModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../classes/Ticket.php';

        $this->ticketModel = new Ticket();
        $this->userId = $userId;
    }

    public function handlePost($ticketId)
    {
        $result = ['success' => '', 'error' => '', 'redirect' => false];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            $result['error'] = 'Vul een bericht in';
        } else {
            if ($this->ticketModel->addMessage($ticketId, $this->userId, $message, false)) {
                // Reopen ticket if closed
                $ticket = $this->ticketModel->getById($ticketId);
                if ($ticket['status'] === 'closed') {
                    $this->ticketModel->updateStatus($ticketId, 'new');
                }
                $result['redirect'] = true;
            } else {
                $result['error'] = 'Er is een fout opgetreden bij het toevoegen van het bericht';
            }
        }

        return $result;
    }

    public function show($ticketId)
    {
        $data = [];
        
        $data['ticket'] = $this->ticketModel->getById($ticketId);

        // Check if ticket exists and belongs to user
        if (!$data['ticket'] || $data['ticket']['user_id'] != $this->userId) {
            return null;
        }

        $data['messages'] = $this->ticketModel->getMessages($ticketId);

        return $data;
    }
}
