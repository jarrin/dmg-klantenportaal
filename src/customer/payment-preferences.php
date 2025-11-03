<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

/**
 * Validate IBAN format
 */
function isValidIban($iban) {
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
    $iban = strtoupper(str_replace([' ', '-'], '', trim($_POST['iban'] ?? '')));
    $accountHolder = trim($_POST['account_holder_name'] ?? '');
    $mandateDate = $_POST['mandate_date'] ?? null;
    
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
                $filename = 'signature_' . $userId . '_' . time() . '.png';
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
    
    if (empty($error)) {
        // Validate direct debit requirements
        if ($paymentMethod === 'direct_debit') {
            if (empty($iban) || empty($accountHolder) || empty($mandateDate)) {
                $error = 'Voor automatisch incasso zijn IBAN, naam rekeninghouder, mandaatdatum en handtekening verplicht';
            } elseif (!isValidIban($iban)) {
                $error = 'Het IBAN-nummer is ongeldig. Controleer het formaat.';
            } elseif (empty($signaturePath)) {
                $error = 'Handtekening is verplicht voor automatisch incasso';
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
$pageTitle = 'Betaalvoorkeuren - ' . APP_NAME;
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

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
        
        <div class="dashboard-section">            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="payment-method-info">
                        <label>
                            <input type="radio" name="payment_method" value="invoice" 
                                   <?php echo (!$preferences || $preferences['payment_method'] === 'invoice') ? 'checked' : ''; ?>
                                   onchange="toggleDirectDebit()">
                            <strong>Betalen via factuur</strong>
                        </label>
                        <p>U ontvangt maandelijks een factuur per e-mail met een betalingstermijn van 14 dagen.</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="payment-method-info">
                        <label>
                            <input type="radio" name="payment_method" value="direct_debit"
                                   <?php echo ($preferences && $preferences['payment_method'] === 'direct_debit') ? 'checked' : ''; ?>
                                   onchange="toggleDirectDebit()">
                            <strong>Automatisch incasso</strong>
                        </label>
                        <p>Het verschuldigde bedrag wordt automatisch van uw rekening afgeschreven. 
                        Hiervoor is een éénmalig mandaat vereist.</p>
                    </div>
                </div>
                
                <div id="directDebitFields" class="direct-debit-fields full-width <?php echo ($preferences && $preferences['payment_method'] === 'direct_debit') ? 'active' : ''; ?>">
                    <h3>Incasso Mandaat</h3>
                    <p>Vul de onderstaande gegevens in voor het SEPA incasso mandaat:</p>
                    
                    <div class="form-group">
                        <label for="iban">IBAN-nummer *</label>
                        <input type="text" id="iban" name="iban" 
                               value="<?php echo htmlspecialchars(($preferences['iban'] ?? '') ? str_replace(' ', '', $preferences['iban']) : ''); ?>"
                               placeholder="NL91 ABNA 0417 1643 00"
                               maxlength="34"
                               oninput="formatIban(this)">
                        <small>Formaat: bijvoorbeeld NL91ABNA0417164300 (spaties worden automatisch toegevoegd)</small>
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
                            <div class="signature-current">
                                <img src="<?php echo htmlspecialchars($preferences['mandate_signature']); ?>" 
                                     alt="Huidige handtekening">
                                <p><small>Huidige handtekening</small></p>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('signaturePad').style.display='block';">
                                    Handtekening vervangen
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div id="signaturePad" class="signature-pad-container" style="display: <?php echo ($preferences && $preferences['mandate_signature']) ? 'none' : 'block'; ?>;">
                            <p><strong>Teken uw handtekening hieronder:</strong></p>
                            <canvas id="signatureCanvas" width="500" height="150">
                                Je browser ondersteunt het canvas element niet.
                            </canvas>
                            <div class="signature-actions">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearSignature()">
                                    Wissen
                                </button>
                            </div>
                            <input type="hidden" id="signature_data" name="signature_data" value="">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Let op:</strong> Door het verstrekken van dit mandaat geeft u toestemming aan 
                        <?php echo APP_NAME; ?> om betalingen van uw rekening af te schrijven volgens de afgesproken voorwaarden.
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary full-width">Voorkeuren Opslaan</button>
            </form>
        </div>
    </div>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
<script src="../js/signature.js"></script>