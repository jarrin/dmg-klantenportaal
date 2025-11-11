<?php
class PaymentPreferencesController
{
    private $db;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../config/Database.php';

        $this->db = Database::getInstance()->getConnection();
        $this->userId = $userId;
    }

    /**
     * Validate IBAN format
     */
    private function isValidIban($iban)
    {
        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));
        
        // Check basic format (2 letters + 2 digits + alphanumeric)
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }
        
        // Check length for common countries
        $ibanLengths = [
            'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 12,
            'BG' => 22, 'BH' => 22, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28,
            'CZ' => 24, 'DE' => 22, 'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24,
            'FI' => 18, 'FO' => 18, 'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18,
            'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22, 'IL' => 23, 'IS' => 26,
            'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LC' => 32, 'LI' => 21,
            'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19,
            'MR' => 27, 'MT' => 31, 'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28,
            'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'SA' => 24, 'SE' => 24,
            'SI' => 19, 'SK' => 24, 'SM' => 27, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VA' => 22,
            'VG' => 24, 'XK' => 20
        ];
        
        $country = substr($iban, 0, 2);
        
        if (isset($ibanLengths[$country]) && strlen($iban) !== $ibanLengths[$country]) {
            return false;
        }
        
        // Perform mod-97 check
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_digit($char)) {
                $numeric .= $char;
            } else {
                // Convert letter to number (A=10, B=11, ..., Z=35)
                $numeric .= (ord($char) - ord('A') + 10);
            }
        }
        
        // Check using modulo 97
        return (int)bcmod($numeric, '97') == 1;
    }

    public function handlePost()
    {
        $result = ['success' => '', 'error' => '', 'preferences' => null];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $paymentMethod = $_POST['payment_method'] ?? 'invoice';
        $iban = strtoupper(str_replace([' ', '-'], '', trim($_POST['iban'] ?? '')));
        $accountHolder = trim($_POST['account_holder_name'] ?? '');
        $mandateDate = $_POST['mandate_date'] ?? null;
        
        // Get current preferences
        $preferences = $this->getPreferences();
        
        // Handle signature data
        $signaturePath = $preferences['mandate_signature'] ?? null;
        
        if (!empty($_POST['signature_data'])) {
            // Canvas-based signature
            $imageData = $_POST['signature_data'];
            
            // Validate and decode base64
            if (preg_match('/^data:image\/png;base64,/', $imageData)) {
                $imageData = substr($imageData, 22); // Remove the data:image/png;base64, prefix
                $imageData = base64_decode($imageData);
                
                if ($imageData) {
                    $filename = 'signature_' . $this->userId . '_' . time() . '.png';
                    $uploadPath = UPLOAD_PATH . '/signatures/';
                    
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    if (file_put_contents($uploadPath . $filename, $imageData)) {
                        $signaturePath = '/uploads/signatures/' . $filename;
                    }
                }
            }
        }
        
        // Validate direct debit requirements
        if ($paymentMethod === 'direct_debit') {
            if (empty($iban) || empty($accountHolder) || empty($mandateDate)) {
                $result['error'] = 'Voor automatisch incasso zijn IBAN, naam rekeninghouder, mandaatdatum en handtekening verplicht';
                return $result;
            }
            
            if (!$this->isValidIban($iban)) {
                $result['error'] = 'Het IBAN-nummer is ongeldig. Controleer het formaat.';
                return $result;
            }
            
            if (empty($signaturePath)) {
                $result['error'] = 'Handtekening is verplicht voor automatisch incasso';
                return $result;
            }
        }
        
        try {
            if ($preferences) {
                // Update existing
                $stmt = $this->db->prepare("
                    UPDATE payment_preferences 
                    SET payment_method = ?, iban = ?, account_holder_name = ?, 
                        mandate_date = ?, mandate_signature = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $paymentMethod,
                    $paymentMethod === 'direct_debit' ? $iban : null,
                    $paymentMethod === 'direct_debit' ? $accountHolder : null,
                    $paymentMethod === 'direct_debit' ? $mandateDate : null,
                    $paymentMethod === 'direct_debit' ? $signaturePath : null,
                    $this->userId
                ]);
            } else {
                // Insert new
                $stmt = $this->db->prepare("
                    INSERT INTO payment_preferences 
                    (user_id, payment_method, iban, account_holder_name, mandate_date, mandate_signature)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $this->userId,
                    $paymentMethod,
                    $paymentMethod === 'direct_debit' ? $iban : null,
                    $paymentMethod === 'direct_debit' ? $accountHolder : null,
                    $paymentMethod === 'direct_debit' ? $mandateDate : null,
                    $paymentMethod === 'direct_debit' ? $signaturePath : null
                ]);
            }
            
            // Notify admins about payment preferences change
            try {
                $this->sendEmailToAdminPaymentPreferencesUpdate($paymentMethod, $accountHolder);
            } catch (Exception $e) {
                // Swallow exception
            }
            
            $result['success'] = 'Betaalvoorkeuren succesvol opgeslagen';
            $result['preferences'] = $this->getPreferences();
            
        } catch (Exception $e) {
            $result['error'] = 'Er is een fout opgetreden: ' . $e->getMessage();
        }

        return $result;
    }

    public function getPreferences()
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_preferences WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        return $stmt->fetch();
    }

    /**
     * Send email to admins when payment preferences are updated
     */
    private function sendEmailToAdminPaymentPreferencesUpdate($paymentMethod, $accountHolder = '') {
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
        
        $userName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $paymentMethodDisplay = $paymentMethod === 'direct_debit' ? 'Automatisch incasso' : 'Factuur';
        
        $body = "<html><body>";
        $body .= "<p>Beste beheerder,</p>";
        $body .= "<p>Klant <strong>" . $userName . "</strong> (ID: " . $this->userId . ") heeft hun betaalvoorkeuren bijgewerkt.</p>";
        $body .= "<p><strong>Betaalmethode:</strong> " . htmlspecialchars($paymentMethodDisplay) . "</p>";
        if (!empty($accountHolder)) {
            $body .= "<p><strong>Rekeninghouder:</strong> " . htmlspecialchars($accountHolder) . "</p>";
        }
        $body .= "<p>Met vriendelijke groet,<br>DMG Klantportaal</p>";
        $body .= "</body></html>";
        
        $subject = 'Betaalvoorkeuren bijgewerkt: ' . $userName;
        
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
