<?php

class Ticket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT t.*, 
                   u.first_name, u.last_name, u.email,
                   (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            ORDER BY 
                CASE t.status 
                    WHEN 'new' THEN 1 
                    WHEN 'in_progress' THEN 2 
                    WHEN 'closed' THEN 3 
                END,
                t.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.first_name, u.last_name, u.email
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
   public function create($userId, $subject, $message, $priority = 'medium', $attachmentPath = null) {
    try {
        $this->db->beginTransaction();

        $stmt = $this->db->prepare("
            INSERT INTO tickets (user_id, subject, priority, status, attachment) 
            VALUES (?, ?, ?, 'new', ?)
        ");
        $stmt->execute([$userId, $subject, $priority, $attachmentPath]);
        $ticketId = $this->db->lastInsertId();

        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply) 
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$ticketId, $userId, $message]);

        $this->db->commit();

        try {
            $this->sendEmailToAdminNewTicket($ticketId, $userId, $subject, $message);
        } catch (Exception $e) {
        }

        return $ticketId;
    } catch (Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
}

    
    public function addMessage($ticketId, $userId, $message, $isStaffReply = false) {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply) 
            VALUES (?, ?, ?, ?)
        ");
            $result = $stmt->execute([$ticketId, $userId, $message, $isStaffReply ? 1 : 0]);

            // If the message was posted by staff, try to notify the customer by email.
            if ($result && $isStaffReply) {
                // best-effort: attempt to send email but don't break the DB operation if mail fails
                try {
                    $this->sendEmailToCustomer($ticketId, $userId, $message);
                } catch (Exception $e) {
                    // Could log the exception to a logger/file in future. For now, swallow to avoid breaking flow.
                }
            }
            
            // If the message was posted by customer, notify admins
            if ($result && !$isStaffReply) {
                try {
                    $this->sendEmailToAdminCustomerReply($ticketId, $userId, $message);
                } catch (Exception $e) {
                    // Swallow exception to avoid breaking flow
                }
            }

            return $result;
    }

        /**
         * Send a notification email to the ticket owner when staff replies.
         * This is a best-effort helper that uses PHP's mail() and the MAIL_* constants from config.
         *
         * @param int $ticketId
         * @param int $staffUserId
         * @param string $message
         * @return bool
         */
        private function sendEmailToCustomer($ticketId, $staffUserId, $message) {
            // Get ticket (includes customer name and email)
            $ticket = $this->getById($ticketId);
            if (!$ticket || empty($ticket['email'])) {
                return false;
            }

            // Try to fetch staff name/email for Reply-To
            $stmt = $this->db->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $stmt->execute([$staffUserId]);
            $staff = $stmt->fetch();

            $to = $ticket['email'];
            $subject = 'Re: Ticket #' . $ticketId . ' - ' . $ticket['subject'];
            $ticketUrl = rtrim(APP_URL, '/') . '/customer/ticket-detail.php?id=' . $ticketId;

            // Build simple HTML email
            $customerName = !empty($ticket['first_name']) ? htmlspecialchars($ticket['first_name']) : '';
            $staffName = $staff ? htmlspecialchars(trim($staff['first_name'] . ' ' . $staff['last_name'])) : MAIL_FROM_NAME;
            $safeMessage = nl2br(htmlspecialchars($message));

            $body = "<html><body>";
            $body .= "<p>Beste {$customerName},</p>";
            $body .= "<p>Er is een antwoord geplaatst op uw ticket <strong>#{$ticketId}</strong> (" . htmlspecialchars($ticket['subject']) . "):</p>";
            $body .= "<div style=\"border-left:4px solid #ccc;padding-left:8px;margin:8px 0;\">{$safeMessage}</div>";
            $body .= "<p>U kunt het ticket bekijken via: <a href=\"{$ticketUrl}\">{$ticketUrl}</a></p>";
            $body .= "<p>Met vriendelijke groet,<br>" . $staffName . "</p>";
            $body .= "</body></html>";

            // Try SendGrid API first
            $apiKey = getenv('SENDGRID_API_KEY');
            if (!empty($apiKey)) {
                $result = $this->sendViaSendGridAPI($to, $subject, $body, $customerName);
                if ($result) {
                    return true;
                }
                // API failed, fall through to mail()
            }

            // Fallback: use PHP mail()
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
            $headers .= "Reply-To: " . ($staff && !empty($staff['email']) ? $staff['email'] : MAIL_FROM_ADDRESS) . "\r\n";

            $ok = @mail($to, $subject, $body, $headers);

            // log mail() result
            $logPath = dirname(__DIR__) . '/logs/mail.log';
            $logEntry = date('c') . " | mail() fallback | to={$to} | subject=" . str_replace(["\\r","\\n"], ['',''], $subject) . " | sent=" . ($ok ? '1' : '0') . "\n";
            @file_put_contents($logPath, $logEntry . "\n", FILE_APPEND | LOCK_EX);

            return $ok;
        }

        /**
         * Send email via SendGrid REST API (fast, non-blocking).
         */
        private function sendViaSendGridAPI($to, $subject, $body, $customerName) {
            $apiKey = getenv('SENDGRID_API_KEY');
            if (empty($apiKey)) {
                // API key niet gevonden - log en return false
                $logPath = dirname(__DIR__) . '/logs/mail.log';
                $logEntry = date('c') . " | SendGrid API | ERROR: SENDGRID_API_KEY not set | to={$to}\n";
                @file_put_contents($logPath, $logEntry . "\n", FILE_APPEND | LOCK_EX);
                return false;
            }

            $fromEmail = MAIL_FROM_ADDRESS;
            $fromName = MAIL_FROM_NAME;

            $data = [
                'personalizations' => [
                    [
                        'to' => [['email' => $to, 'name' => $customerName]],
                        'subject' => $subject
                    ]
                ],
                'from' => ['email' => $fromEmail, 'name' => $fromName],
                'content' => [
                    ['type' => 'text/html', 'value' => $body]
                ]
            ];

            $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $success = ($httpCode === 202);

            // Log result met volledige debug info
            $logPath = dirname(__DIR__) . '/logs/mail.log';
            $logEntry = date('c') . " | SendGrid API | from={$fromEmail} | to={$to} | http_code={$httpCode} | sent=" . ($success ? '1' : '0');
            if (!empty($curlError)) {
                $logEntry .= " | curl_error={$curlError}";
            }
            if (!$success && !empty($response)) {
                $logEntry .= " | response=" . substr($response, 0, 300);
            }
            $logEntry .= "\n";
            @file_put_contents($logPath, $logEntry . "\n", FILE_APPEND | LOCK_EX);

            return $success;
        }
    
    public function getMessages($ticketId) {
        $stmt = $this->db->prepare("
            SELECT tm.*, u.first_name, u.last_name, u.role
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status) {
        $closedAt = ($status === 'closed') ? 'NOW()' : 'NULL';
        $stmt = $this->db->prepare("
            UPDATE tickets 
            SET status = ?, closed_at = $closedAt
            WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }
    
    public function getStatistics() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM tickets
        ");
        return $stmt->fetch();
    }

    /**
     * Send a notification email to admins when a new ticket is created
     */
    private function sendEmailToAdminNewTicket($ticketId, $userId, $subject, $message) {
        // Get all admin users
        $stmt = $this->db->prepare("SELECT email, first_name, last_name FROM users WHERE role = 'admin' AND active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        if (empty($admins)) {
            return false;
        }
        
        // Get customer info
        $customer = $this->getById($ticketId);
        if (!$customer) {
            return false;
        }
        
        $ticketUrl = rtrim(APP_URL, '/') . '/admin/ticket-detail.php?id=' . $ticketId;
        $customerName = htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']);
        $safeSubject = htmlspecialchars($subject);
        $safeMessage = nl2br(htmlspecialchars($message));
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Er is een nieuw ticket aangemaakt door <strong>{$customerName}</strong>:</p>";
        $body .= "<p><strong>Ticket #" . $ticketId . ":</strong> " . $safeSubject . "</p>";
        $body .= "<div style=\"border-left:4px solid #ccc;padding-left:8px;margin:8px 0;\">{$safeMessage}</div>";
        $body .= "<p>Bekijk het ticket: <a href=\"{$ticketUrl}\">{$ticketUrl}</a></p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Nieuw ticket: #' . $ticketId . ' - ' . $safeSubject;
        
        foreach ($admins as $admin) {
            try {
                $this->sendEmailViaSendGrid($admin['email'], $subject, $body, $admin['first_name']);
            } catch (Exception $e) {
                // Continue with next admin
            }
        }
        
        return true;
    }

    /**
     * Send a notification email to admins when a customer replies to a ticket
     */
    private function sendEmailToAdminCustomerReply($ticketId, $userId, $message) {
        // Get all admin users
        $stmt = $this->db->prepare("SELECT email, first_name, last_name FROM users WHERE role = 'admin' AND active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        if (empty($admins)) {
            return false;
        }
        
        // Get ticket and customer info
        $ticket = $this->getById($ticketId);
        if (!$ticket) {
            return false;
        }
        
        $ticketUrl = rtrim(APP_URL, '/') . '/admin/ticket-detail.php?id=' . $ticketId;
        $customerName = htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']);
        $safeSubject = htmlspecialchars($ticket['subject']);
        $safeMessage = nl2br(htmlspecialchars($message));
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Klant <strong>{$customerName}</strong> heeft gereageerd op ticket <strong>#{$ticketId}</strong> (" . $safeSubject . "):</p>";
        $body .= "<div style=\"border-left:4px solid #ccc;padding-left:8px;margin:8px 0;\">{$safeMessage}</div>";
        $body .= "<p>Bekijk het ticket: <a href=\"{$ticketUrl}\">{$ticketUrl}</a></p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Klant gereageerd: Ticket #' . $ticketId . ' - ' . $safeSubject;
        
        foreach ($admins as $admin) {
            try {
                $this->sendEmailViaSendGrid($admin['email'], $subject, $body, $admin['first_name']);
            } catch (Exception $e) {
                // Continue with next admin
            }
        }
        
        return true;
    }

    /**
     * Send email via SendGrid REST API (reusable method for admin emails)
     */
    private function sendEmailViaSendGrid($to, $subject, $body, $toName = '') {
        $apiKey = getenv('SENDGRID_API_KEY');
        if (empty($apiKey)) {
            return false;
        }
        
        $fromEmail = MAIL_FROM_ADDRESS;
        $fromName = MAIL_FROM_NAME;
        
        $data = [
            'personalizations' => [
                [
                    'to' => [['email' => $to, 'name' => $toName]],
                    'subject' => $subject
                ]
            ],
            'from' => ['email' => $fromEmail, 'name' => $fromName],
            'content' => [
                ['type' => 'text/html', 'value' => $body]
            ]
        ];
        
        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 202);
    }
}
