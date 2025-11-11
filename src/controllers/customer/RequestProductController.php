<?php
class RequestProductController
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

    public function handlePost()
    {
        $result = ['success' => '', 'error' => ''];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $productTypeId = $_POST['product_type_id'] ?? 0;
        $requestedName = trim($_POST['requested_name'] ?? '');
        $requestedDomain = trim($_POST['requested_domain'] ?? '');
        $additionalInfo = trim($_POST['additional_info'] ?? '');

        if (empty($productTypeId) || empty($requestedName)) {
            $result['error'] = 'Vul alle verplichte velden in';
        } else {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO product_requests (user_id, product_type_id, requested_name, requested_domain, additional_info, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");

                if ($stmt->execute([$this->userId, $productTypeId, $requestedName, $requestedDomain, $additionalInfo])) {
                    $requestId = $this->db->lastInsertId();
                    
                    // Notify admins about product request
                    try {
                        $this->sendEmailToAdminProductRequest($requestId, $productTypeId, $requestedName, $requestedDomain, $additionalInfo);
                    } catch (Exception $e) {
                        // Swallow exception
                    }
                    
                    $result['success'] = 'Product aanvraag succesvol ingediend. U ontvangt bericht zodra deze is verwerkt.';
                } else {
                    $result['error'] = 'Er is een fout opgetreden bij het indienen van de aanvraag';
                }
            } catch (Exception $e) {
                $result['error'] = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }

        return $result;
    }

    public function index()
    {
        $data = [];
        
        $data['productTypes'] = $this->productModel->getProductTypes();

        // Get user's pending requests
        $stmt = $this->db->prepare("
            SELECT pr.*, pt.name as type_name
            FROM product_requests pr
            JOIN product_types pt ON pr.product_type_id = pt.id
            WHERE pr.user_id = ?
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([$this->userId]);
        $data['requests'] = $stmt->fetchAll();

        return $data;
    }

    /**
     * Send email to admins when a product is requested
     */
    private function sendEmailToAdminProductRequest($requestId, $productTypeId, $requestedName, $requestedDomain, $additionalInfo)
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
        
        // Get product type name
        $typeStmt = $this->db->prepare("SELECT name FROM product_types WHERE id = ?");
        $typeStmt->execute([$productTypeId]);
        $type = $typeStmt->fetch();
        
        $userName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $typeName = $type ? htmlspecialchars($type['name']) : 'Onbekend';
        $productName = htmlspecialchars($requestedName);
        $domain = !empty($requestedDomain) ? htmlspecialchars($requestedDomain) : 'Niet opgegeven';
        $info = !empty($additionalInfo) ? nl2br(htmlspecialchars($additionalInfo)) : 'Geen';
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Klant <strong>" . $userName . "</strong> heeft een product aangevraagd:</p>";
        $body .= "<p><strong>Type:</strong> " . $typeName . "</p>";
        $body .= "<p><strong>Productnaam:</strong> " . $productName . "</p>";
        $body .= "<p><strong>Domeinnaam:</strong> " . $domain . "</p>";
        $body .= "<p><strong>Aanvullende informatie:</strong><br>" . $info . "</p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Product aangevraagd: ' . $productName . ' door ' . $userName;
        
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
