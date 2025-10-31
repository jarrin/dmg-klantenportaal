<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();
$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

// Get current payment preferences
$stmt = $db->prepare("SELECT * FROM payment_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$preferences = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? 'invoice';
    $iban = trim($_POST['iban'] ?? '');
    $accountHolder = trim($_POST['account_holder_name'] ?? '');
    $mandateDate = $_POST['mandate_date'] ?? null;
    
    // Handle signature upload
    $signaturePath = $preferences['mandate_signature'] ?? null;
    
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $fileType = $_FILES['signature']['type'];
        
        if (in_array($fileType, $allowedTypes) && $_FILES['signature']['size'] <= MAX_UPLOAD_SIZE) {
            $extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
            $filename = 'signature_' . $userId . '_' . time() . '.' . $extension;
            $uploadPath = UPLOAD_PATH . '/signatures/';
            
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $uploadPath . $filename)) {
                $signaturePath = '/uploads/signatures/' . $filename;
            }
        } else {
            $error = 'Ongeldige handtekening. Gebruik een JPG of PNG bestand (max 5MB)';
        }
    }
    
    if (empty($error)) {
        // Validate direct debit requirements
        if ($paymentMethod === 'direct_debit') {
            if (empty($iban) || empty($accountHolder) || empty($mandateDate)) {
                $error = 'Voor automatisch incasso zijn IBAN, naam rekeninghouder en mandaatdatum verplicht';
            }
        }
        
        if (empty($error)) {
            try {
                if ($preferences) {
                    // Update existing
                    $stmt = $db->prepare("
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
                        $userId
                    ]);
                } else {
                    // Insert new
                    $stmt = $db->prepare("
                        INSERT INTO payment_preferences 
                        (user_id, payment_method, iban, account_holder_name, mandate_date, mandate_signature)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $paymentMethod,
                        $paymentMethod === 'direct_debit' ? $iban : null,
                        $paymentMethod === 'direct_debit' ? $accountHolder : null,
                        $paymentMethod === 'direct_debit' ? $mandateDate : null,
                        $paymentMethod === 'direct_debit' ? $signaturePath : null
                    ]);
                }
                
                $success = 'Betaalvoorkeuren succesvol opgeslagen';
                
                // Refresh preferences
                $stmt = $db->prepare("SELECT * FROM payment_preferences WHERE user_id = ?");
                $stmt->execute([$userId]);
                $preferences = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Betaalvoorkeuren - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .payment-method-info {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        .direct-debit-fields {
            display: none;
            background: var(--light-color);
            padding: 20px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .direct-debit-fields.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Betaalvoorkeuren</h1>
            <a href="/customer/dashboard.php" class="btn btn-secondary">Terug naar dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="dashboard-section" style="max-width: 800px; margin: 0 auto;">
            <h2>Betaalmethode</h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>
                        <input type="radio" name="payment_method" value="invoice" 
                               <?php echo (!$preferences || $preferences['payment_method'] === 'invoice') ? 'checked' : ''; ?>
                               onchange="toggleDirectDebit()">
                        <strong>Betalen via factuur</strong>
                    </label>
                    <div class="payment-method-info" style="margin-left: 25px; margin-top: 10px;">
                        U ontvangt maandelijks een factuur per e-mail met een betalingstermijn van 14 dagen.
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="radio" name="payment_method" value="direct_debit"
                               <?php echo ($preferences && $preferences['payment_method'] === 'direct_debit') ? 'checked' : ''; ?>
                               onchange="toggleDirectDebit()">
                        <strong>Automatisch incasso</strong>
                    </label>
                    <div class="payment-method-info" style="margin-left: 25px; margin-top: 10px;">
                        Het verschuldigde bedrag wordt automatisch van uw rekening afgeschreven. 
                        Hiervoor is een éénmalig mandaat vereist.
                    </div>
                </div>
                
                <div id="directDebitFields" class="direct-debit-fields <?php echo ($preferences && $preferences['payment_method'] === 'direct_debit') ? 'active' : ''; ?>">
                    <h3>Incasso Mandaat</h3>
                    <p>Vul de onderstaande gegevens in voor het SEPA incasso mandaat:</p>
                    
                    <div class="form-group">
                        <label for="iban">IBAN-nummer *</label>
                        <input type="text" id="iban" name="iban" 
                               value="<?php echo htmlspecialchars($preferences['iban'] ?? ''); ?>"
                               placeholder="NL00 BANK 0123 4567 89"
                               pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]+"
                               maxlength="34">
                        <small>Formaat: NL00 BANK 0123 4567 89</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_holder_name">Naam rekeninghouder *</label>
                        <input type="text" id="account_holder_name" name="account_holder_name"
                               value="<?php echo htmlspecialchars($preferences['account_holder_name'] ?? ''); ?>"
                               placeholder="Volledige naam zoals op bankpas">
                    </div>
                    
                    <div class="form-group">
                        <label for="mandate_date">Mandaat datum *</label>
                        <input type="date" id="mandate_date" name="mandate_date"
                               value="<?php echo $preferences['mandate_date'] ?? date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signature">Handtekening *</label>
                        <?php if ($preferences && $preferences['mandate_signature']): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="<?php echo htmlspecialchars($preferences['mandate_signature']); ?>" 
                                     alt="Huidige handtekening" style="max-width: 200px; border: 1px solid #ddd; padding: 5px;">
                                <p><small>Huidige handtekening</small></p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="signature" name="signature" accept="image/jpeg,image/png,image/jpg">
                        <small>Upload een afbeelding van uw handtekening (JPG/PNG, max 5MB)</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Let op:</strong> Door het verstrekken van dit mandaat geeft u toestemming aan 
                        <?php echo APP_NAME; ?> om betalingen van uw rekening af te schrijven volgens de afgesproken voorwaarden.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Voorkeuren Opslaan</button>
            </form>
        </div>
    </div>
    
    <script>
        function toggleDirectDebit() {
            const directDebit = document.querySelector('input[name="payment_method"][value="direct_debit"]').checked;
            const fields = document.getElementById('directDebitFields');
            
            if (directDebit) {
                fields.classList.add('active');
                // Make fields required
                document.getElementById('iban').required = true;
                document.getElementById('account_holder_name').required = true;
                document.getElementById('mandate_date').required = true;
            } else {
                fields.classList.remove('active');
                // Make fields optional
                document.getElementById('iban').required = false;
                document.getElementById('account_holder_name').required = false;
                document.getElementById('mandate_date').required = false;
            }
        }
        
        // Initialize on page load
        toggleDirectDebit();
    </script>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
