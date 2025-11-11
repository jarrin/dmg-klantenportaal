<?php

class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name 
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            WHERE p.user_id = ?
            ORDER BY p.expiry_date ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT p.*, pt.name as type_name, 
                   u.first_name, u.last_name, u.email
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name 
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO products (user_id, product_type_id, name, description, domain_name, 
                                registration_date, expiry_date, duration_months, price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['user_id'],
            $data['product_type_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['domain_name'] ?? null,
            $data['registration_date'],
            $data['expiry_date'],
            $data['duration_months'] ?? 12,
            $data['price'],
            $data['status'] ?? 'active'
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $id;
        
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    public function delete($id) {
        $product = $this->getById($id);
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        // Notify admins about actual product deletion
        if ($result && $product) {
            try {
                $this->sendEmailToAdminProductDeleted($id, $product);
            } catch (Exception $e) {
                // Swallow exception
            }
        }
        
        return $result;
    }

    public function activateProduct($id) {
    $stmt = $this->db->prepare("UPDATE products SET status = 'active' WHERE id = ?");
    return $stmt->execute([$id]);
    }

    
    public function getProductTypes() {
        $stmt = $this->db->query("SELECT * FROM product_types ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function extendProduct($id, $months = 12) {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET expiry_date = DATE_ADD(expiry_date, INTERVAL ? MONTH),
                status = 'active'
            WHERE id = ?
        ");
        return $stmt->execute([$months, $id]);
    }
    
    public function cancelProduct($id) {
        $stmt = $this->db->prepare("UPDATE products SET status = 'cancelled' WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getExpiringProducts($days = 30) {
        $stmt = $this->db->prepare("
            SELECT p.*, DATEDIFF(p.expiry_date, CURDATE()) AS days_left, pt.name as type_name, u.email, u.first_name, u.last_name
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'active'
            AND DATEDIFF(p.expiry_date, CURDATE()) BETWEEN 0 AND ?
            ORDER BY p.expiry_date ASC
        ");
        $stmt->execute([(int)$days]);
        return $stmt->fetchAll();
    }

    /**
     * Send email to admins when a product is actually deleted
     */
    private function sendEmailToAdminProductDeleted($productId, $product) {
        $stmt = $this->db->prepare("SELECT email, first_name FROM users WHERE role = 'admin' AND active = 1");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        if (empty($admins)) {
            return false;
        }
        
        // Get user info
        $userStmt = $this->db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $userStmt->execute([$product['user_id']]);
        $user = $userStmt->fetch();
        
        $userName = $user ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : 'Onbekend';
        $productName = htmlspecialchars($product['name']);
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Product verwijderd voor klant <strong>" . $userName . "</strong>:</p>";
        $body .= "<p><strong>Product:</strong> " . $productName . " (ID: " . $productId . ")</p>";
        $body .= "<p>Het product is nu permanent uit het systeem verwijderd.</p>";
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Product permanent verwijderd: ' . $productName;
        
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
