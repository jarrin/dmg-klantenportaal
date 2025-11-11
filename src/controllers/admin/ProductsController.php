<?php
class ProductsController
{
    private $db;
    private $productModel;
    private $userModel;
    private $auth;

    public function __construct($auth)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/Product.php';
        require_once __DIR__ . '/../../classes/User.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->auth = $auth;
    }

    public function handlePost()
    {
        $result = ['success' => '', 'error' => ''];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $result = $this->createProduct();
                break;
            case 'extend':
                $result = $this->extendProduct();
                break;
            case 'cancel':
                $result = $this->cancelProduct();
                break;
            case 'delete':
                $result = $this->deleteProduct();
                break;
            case 'activate':
                $result = $this->activateProduct();
                break;
            case 'approve_request':
                $result = $this->approveRequest();
                break;
            case 'reject_request':
                $result = $this->rejectRequest();
                break;
            case 'approve_cancellation':
                $result = $this->approveCancellation();
                break;
            case 'reject_cancellation':
                $result = $this->rejectCancellation();
                break;
        }

        return $result;
    }

    private function createProduct()
    {
        $userId = $_POST['user_id'] ?? 0;
        $productTypeId = $_POST['product_type_id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $domainName = trim($_POST['domain_name'] ?? '');
        $registrationDate = $_POST['registration_date'] ?? date('Y-m-d');
        $durationMonths = $_POST['duration_months'] ?? 12;
        $price = $_POST['price'] ?? 0;

        if (empty($userId) || empty($productTypeId) || empty($name) || empty($price)) {
            return ['success' => '', 'error' => 'Vul alle verplichte velden in'];
        }

        $expiryDate = date('Y-m-d', strtotime($registrationDate . ' + ' . $durationMonths . ' months'));

        $data = [
            'user_id' => $userId,
            'product_type_id' => $productTypeId,
            'name' => $name,
            'description' => $description,
            'domain_name' => $domainName,
            'registration_date' => $registrationDate,
            'expiry_date' => $expiryDate,
            'duration_months' => $durationMonths,
            'price' => $price,
            'status' => 'active'
        ];

        if ($this->productModel->create($data)) {
            return ['success' => 'Product succesvol aangemaakt', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het aanmaken van het product'];
    }

    private function extendProduct()
    {
        $productId = $_POST['product_id'] ?? 0;
        $months = $_POST['months'] ?? 12;

        if ($this->productModel->extendProduct($productId, $months)) {
            return ['success' => 'Product succesvol verlengd met ' . $months . ' maanden', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het verlengen van het product'];
    }

    private function cancelProduct()
    {
        $productId = $_POST['product_id'] ?? 0;

        if ($this->productModel->cancelProduct($productId)) {
            return ['success' => 'Product succesvol opgezegd', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het opzeggen van het product'];
    }

    private function deleteProduct()
    {
        $productId = $_POST['product_id'] ?? 0;

        if ($this->productModel->delete($productId)) {
            return ['success' => 'Product succesvol verwijderd', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het verwijderen van het product'];
    }

    private function activateProduct()
    {
        $productId = $_POST['product_id'] ?? 0;

        if ($this->productModel->activateProduct($productId)) {
            return ['success' => 'Product succesvol geactiveerd', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het activeren van het product'];
    }

    private function approveRequest()
    {
        $requestId = $_POST['request_id'] ?? 0;

        $stmt = $this->db->prepare("SELECT * FROM product_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if ($request) {
            $expiryDate = date('Y-m-d', strtotime('+12 months'));

            $data = [
                'user_id' => $request['user_id'],
                'product_type_id' => $request['product_type_id'],
                'name' => $request['requested_name'],
                'description' => $request['additional_info'],
                'domain_name' => $request['requested_domain'],
                'registration_date' => date('Y-m-d'),
                'expiry_date' => $expiryDate,
                'duration_months' => 12,
                'price' => 99.99,
                'status' => 'active'
            ];

            if ($this->productModel->create($data)) {
                $stmt = $this->db->prepare("
                    UPDATE product_requests 
                    SET status = 'completed', processed_at = NOW(), processed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$this->auth->getCurrentUserId(), $requestId]);

                // Send approval email to customer
                try {
                    $this->sendEmailToCustomerApproval($request);
                } catch (Exception $e) {
                    // Swallow exception
                }

                return ['success' => 'Product aanvraag goedgekeurd en product aangemaakt', 'error' => ''];
            }
        }

        return ['success' => '', 'error' => 'Er is een fout opgetreden'];
    }

    private function rejectRequest()
    {
        $requestId = $_POST['request_id'] ?? 0;

        $stmt = $this->db->prepare("
            UPDATE product_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ?
            WHERE id = ?
        ");

        if ($stmt->execute([$this->auth->getCurrentUserId(), $requestId])) {
            return ['success' => 'Product aanvraag afgewezen', 'error' => ''];
        }

        return ['success' => '', 'error' => 'Er is een fout opgetreden'];
    }

    private function approveCancellation()
    {
        $cancellationId = $_POST['cancellation_id'] ?? 0;

        $stmt = $this->db->prepare("SELECT * FROM cancellation_requests WHERE id = ?");
        $stmt->execute([$cancellationId]);
        $cancellation = $stmt->fetch();

        if ($cancellation) {
            $this->productModel->cancelProduct($cancellation['product_id']);

            $stmt = $this->db->prepare("
                UPDATE cancellation_requests 
                SET status = 'approved', processed_at = NOW(), processed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$this->auth->getCurrentUserId(), $cancellationId]);

            return ['success' => 'Opzegverzoek goedgekeurd', 'error' => ''];
        }

        return ['success' => '', 'error' => 'Er is een fout opgetreden'];
    }

    private function rejectCancellation()
    {
        $cancellationId = $_POST['cancellation_id'] ?? 0;

        $stmt = $this->db->prepare("
            UPDATE cancellation_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ?
            WHERE id = ?
        ");

        if ($stmt->execute([$this->auth->getCurrentUserId(), $cancellationId])) {
            return ['success' => 'Opzegverzoek afgewezen', 'error' => ''];
        }

        return ['success' => '', 'error' => 'Er is een fout opgetreden'];
    }

    public function index()
    {
        $data = [];
        
        // Pagination setup
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 15;

        // Count total products
        $countQuery = "SELECT COUNT(*) FROM products";
        $data['paginator'] = Paginator::fromQuery($this->db, $countQuery, [], $perPage, $page);

        // Get products with pagination
        $stmt = $this->db->prepare("
            SELECT p.*, u.first_name, u.last_name, u.email, pt.name as type_name
            FROM products p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN product_types pt ON p.product_type_id = pt.id
            ORDER BY p.created_at DESC
            " . $data['paginator']->getLimitClause()
        );
        $stmt->execute();
        $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['users'] = $this->userModel->getAll('customer');
        $data['productTypes'] = $this->productModel->getProductTypes();

        // Pagination for pending requests
        $requestsPage = isset($_GET['requests_page']) ? max(1, (int)$_GET['requests_page']) : 1;
        $requestsPerPage = 10;
        $countRequestsQuery = "SELECT COUNT(*) FROM product_requests WHERE status = 'pending'";
        $data['requestsPaginator'] = Paginator::fromQuery($this->db, $countRequestsQuery, [], $requestsPerPage, $requestsPage);

        // Get pending requests with pagination
        $stmt = $this->db->prepare("
            SELECT pr.*, pt.name as type_name, u.first_name, u.last_name, u.email
            FROM product_requests pr
            JOIN product_types pt ON pr.product_type_id = pt.id
            JOIN users u ON pr.user_id = u.id
            WHERE pr.status = 'pending'
            ORDER BY pr.created_at DESC
            " . $data['requestsPaginator']->getLimitClause()
        );
        $stmt->execute();
        $data['pendingRequests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pagination for pending cancellations
        $cancellationsPage = isset($_GET['cancellations_page']) ? max(1, (int)$_GET['cancellations_page']) : 1;
        $cancellationsPerPage = 10;
        $countCancellationsQuery = "SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'";
        $data['cancellationsPaginator'] = Paginator::fromQuery($this->db, $countCancellationsQuery, [], $cancellationsPerPage, $cancellationsPage);

        // Get pending cancellations with pagination
        $stmt = $this->db->prepare("
            SELECT cr.*, p.name as product_name, pt.name as type_name, u.first_name, u.last_name, u.email
            FROM cancellation_requests cr
            JOIN products p ON cr.product_id = p.id
            JOIN product_types pt ON p.product_type_id = pt.id
            JOIN users u ON cr.user_id = u.id
            WHERE cr.status = 'pending'
            ORDER BY cr.created_at DESC
            " . $data['cancellationsPaginator']->getLimitClause()
        );
        $stmt->execute();
        $data['pendingCancellations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['perPage'] = $perPage;
        $data['page'] = $page;
        $data['requestsPage'] = $requestsPage;
        $data['cancellationsPage'] = $cancellationsPage;

        return $data;
    }

    /**
     * Send approval email to customer when their product request is approved
     */
    private function sendEmailToCustomerApproval($request)
    {
        // Get customer info
        $userStmt = $this->db->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $userStmt->execute([$request['user_id']]);
        $user = $userStmt->fetch();
        
        if (!$user || empty($user['email'])) {
            return false;
        }
        
        // Get product type name
        $typeStmt = $this->db->prepare("SELECT name FROM product_types WHERE id = ?");
        $typeStmt->execute([$request['product_type_id']]);
        $type = $typeStmt->fetch();
        
        $customerName = htmlspecialchars($user['first_name']);
        $typeName = $type ? htmlspecialchars($type['name']) : 'Onbekend';
        $productName = htmlspecialchars($request['requested_name']);
        $dashboardUrl = rtrim(APP_URL, '/') . '/customer/products.php';
        
        $body = "<html><body>";
        $body .= "<p>Beste " . $customerName . ",</p>";
        $body .= "<p>Goed nieuws! Uw aanvraag voor het product <strong>" . $productName . "</strong> is goedgekeurd.</p>";
        $body .= "<p><strong>Type:</strong> " . $typeName . "</p>";
        $body .= "<p>Het product is nu actief en beschikbaar in uw account. U kunt dit zien in uw productlijst.</p>";
        $body .= "<p><a href=\"" . $dashboardUrl . "\">Bekijk uw producten</a></p>";
        $body .= "<p>Dank u wel dat u gebruik maakt van onze diensten!</p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Uw productaanvraag is goedgekeurd: ' . $productName;
        
        return $this->sendEmailViaSendGrid($user['email'], $subject, $body, $customerName);
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
