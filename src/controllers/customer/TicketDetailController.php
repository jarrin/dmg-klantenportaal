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
    $attachmentPath = null;

    if (empty($message)) {
        $result['error'] = 'Vul een bericht in';
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
            $result['error'] = 'Fout bij het uploaden van de bijlage.';
            return $result;
        }

        if ($file['size'] > $maxSize) {
            $result['error'] = 'Bestand is te groot. Max 5MB.';
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

        $filename = uniqid('msg_', true) . "_" . basename($file['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentPath = '/uploads/tickets/' . $filename;
        } else {
            $result['error'] = 'Kan bijlage niet opslaan.';
            return $result;
        }
    }

    if ($this->ticketModel->addMessage($ticketId, $this->userId, $message, false, $attachmentPath)) {

        $ticket = $this->ticketModel->getById($ticketId);
        if ($ticket['status'] === 'closed') {
            $this->ticketModel->updateStatus($ticketId, 'new');
        }

        $result['redirect'] = true;

    } else {
        $result['error'] = 'Er is een fout opgetreden bij het toevoegen van het bericht.';
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
