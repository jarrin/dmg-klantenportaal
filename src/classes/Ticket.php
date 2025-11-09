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
    
    public function create($userId, $subject, $message, $priority = 'medium') {
        try {
            $this->db->beginTransaction();
            
            // Create ticket
            $stmt = $this->db->prepare("
                INSERT INTO tickets (user_id, subject, priority, status) 
                VALUES (?, ?, ?, 'new')
            ");
            $stmt->execute([$userId, $subject, $priority]);
            $ticketId = $this->db->lastInsertId();
            
            // Add initial message
            $stmt = $this->db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply) 
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$ticketId, $userId, $message]);
            
            $this->db->commit();
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

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
            $headers .= "Reply-To: " . ($staff && !empty($staff['email']) ? $staff['email'] : MAIL_FROM_ADDRESS) . "\r\n";

            // Prefer PHPMailer with SMTP when available and configured.
            $composerAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (MAIL_USE_SMTP && file_exists($composerAutoload)) {
                try {
                    require_once $composerAutoload;

                    // Dynamically instantiate PHPMailer to avoid static analyzer errors when
                    // the package isn't installed in the environment.
                    $phPMailerClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
                    if (class_exists($phPMailerClass)) {
                        $mail = new $phPMailerClass(true);

                        // Prepare debug capture
                        $debugLines = [];
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = function($str, $level) use (&$debugLines) {
                            $debugLines[] = trim($str);
                        };

                        // Server settings
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        // Only enable SMTPAuth when credentials are provided
                        $mail->SMTPAuth = !empty(SMTP_USER);
                        if ($mail->SMTPAuth) {
                            $mail->Username = SMTP_USER;
                            $mail->Password = SMTP_PASS;
                        }

                        // Respect explicit SMTP_SECURE. If empty, disable automatic STARTTLS
                        $secure = SMTP_SECURE ?: '';
                        $mail->SMTPSecure = $secure;
                        if (empty($secure)) {
                            // Prevent PHPMailer from attempting STARTTLS when server doesn't support it (eg. MailHog)
                            $mail->SMTPAutoTLS = false;
                        }

                        $mail->Port = (int)SMTP_PORT ?: 587;

                        // Recipients
                        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                        // Ensure envelope sender is set explicitly (helps some SMTP providers and SPF checks)
                        $mail->Sender = MAIL_FROM_ADDRESS;
                        $mail->addAddress($to, $customerName);
                        if ($staff && !empty($staff['email'])) {
                            $mail->addReplyTo($staff['email'], $staffName);
                        }

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $body;

                        $sent = false;
                        try {
                            $sent = $mail->send();
                        } catch (\Throwable $e) {
                            $debugLines[] = 'PHPMailer exception: ' . $e->getMessage();
                        }

                        // write debug to log file
                        $logPath = dirname(__DIR__) . '/logs/mail.log';
                        $logEntry = date('c') . " | PHPMailer | to={$to} | subject=" . str_replace(["\\r","\\n"], ['',''], $subject) . " | sent=" . ($sent ? '1' : '0') . "\n";
                        if (!empty($debugLines)) {
                            $logEntry .= "DEBUG:\n" . implode("\n", $debugLines) . "\n";
                        }
                        @file_put_contents($logPath, $logEntry . "\n", FILE_APPEND | LOCK_EX);

                        return $sent;
                    }
                } catch (\Throwable $e) {
                    // PHPMailer not available or SMTP failed — fallback to mail()
                }
            }

            // Fallback: use PHP mail() as best-effort
            $ok = mail($to, $subject, $body, $headers);

            // log mail() result
            $logPath = dirname(__DIR__) . '/logs/mail.log';
            $logEntry = date('c') . " | mail() | to={$to} | subject=" . str_replace(["\\r","\\n"], ['',''], $subject) . " | sent=" . ($ok ? '1' : '0') . "\n";
            if (!$ok) {
                $last = error_get_last();
                if ($last && !empty($last['message'])) {
                    $logEntry .= "ERROR: " . $last['message'] . "\n";
                }
            }
            @file_put_contents($logPath, $logEntry . "\n", FILE_APPEND | LOCK_EX);

            return $ok;
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
}
