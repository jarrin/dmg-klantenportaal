<?php
class CancelProductController
{
    private $db;
    private $productModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Product.php';

        $this->db = Database::getInstance()->getConnection();
        $this->productModel = new Product();
        $this->userId = $userId;
    }

    public function handlePost($productId)
    {
        $result = ['success' => '', 'error' => '', 'redirect' => false];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $reason = trim($_POST['reason'] ?? '');

        if (empty($reason)) {
            $result['error'] = 'Geef alstublieft een reden op voor de opzegging';
        } else {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO cancellation_requests (user_id, product_id, reason, status)
                    VALUES (?, ?, ?, 'pending')
                ");

                if ($stmt->execute([$this->userId, $productId, $reason])) {
                    $cancellationId = $this->db->lastInsertId();
                    
                    // Notify admins about cancellation request
                    try {
                        $this->sendEmailToAdminCancellationRequest($cancellationId, $productId, $reason);
                    } catch (Exception $e) {
                        // Swallow exception
                    }
                    
                    $result['success'] = 'Opzegverzoek succesvol ingediend. U ontvangt bericht zodra dit is verwerkt.';
                    $result['redirect'] = true;
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het indienen van het opzegverzoek';
                }
            } catch (Exception $e) {
                $result['error'] = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }

        return $result;
    }

    public function show($productId)
    {
        $product = $this->productModel->getById($productId);

        // Check if product exists and belongs to user
        if (!$product || $product['user_id'] != $this->userId) {
            return null;
        }

        return $product;
    }

    /**
     * Send email to admins when a product cancellation is requested
     */
    private function sendEmailToAdminCancellationRequest($cancellationId, $productId, $reason)
    {
        $stmt = $this->db->prepare("SELECT email, first_name FROM users WHERE role = 'admin' AND active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        if (empty($admins)) {
            return false;
        }
        
        // Get user info
        $userStmt = $this->db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $userStmt->execute([$this->userId]);
        $user = $userStmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Get product info
        $productStmt = $this->db->prepare("SELECT name FROM products WHERE id = ?");
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        
        $userName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $productName = $product ? htmlspecialchars($product['name']) : 'Onbekend';
        $safeReason = nl2br(htmlspecialchars($reason));
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Klant <strong>" . $userName . "</strong> heeft een opzegverzoek ingediend voor product <strong>" . $productName . "</strong>:</p>";
        $body .= "<p><strong>Reden:</strong></p>";
        $body .= "<div style=\"border-left:4px solid #ccc;padding-left:8px;margin:8px 0;\">" . $safeReason . "</div>";
        $body .= "<p>Het product zal worden verwijderd op de vervaldatum u hoeft nu niets te doen.</p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Opzegverzoek: ' . $productName . ' van ' . $userName;
        
        foreach ($admins as $admin) {
            try {
                $this->sendEmailViaSendGrid($admin['email'], $subject, $body, $admin['first_name']);
            } catch (Exception $e) {
                // Continue
            }
        }
        
        return true;
    }

    /**
     * Send email via SendGrid API
     */
    private function sendEmailViaSendGrid($to, $subject, $body, $toName = '')
    {
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
