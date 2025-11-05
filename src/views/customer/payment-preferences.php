<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/PaymentPreferencesController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new PaymentPreferencesController($userId);

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

// Get preferences
$preferences = $result['preferences'] ?? $controller->getPreferences();

$pageTitle = 'Betaalvoorkeuren - ' . APP_NAME;
?>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Betaalvoorkeuren</h1>
        <a href="/views/customer/dashboard.php" class="btn btn-secondary">Terug naar dashboard</a>
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