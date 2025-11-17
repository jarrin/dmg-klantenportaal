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
        $attachmentPath = null;

        if (empty($message)) {
            $result['error'] = 'Vul een bericht in';
        } else {
            // Handle file upload
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

            if ($this->ticketModel->addMessage(
                $ticketId, 
                $this->auth->getCurrentUserId(), 
                $message, 
                true, 
                $attachmentPath
            )) {
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
