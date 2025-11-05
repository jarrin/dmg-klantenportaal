<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

$auth = new Auth();
$auth->requireCustomer();

$productModel = new Product();
$userId = $auth->getCurrentUserId();

$productId = $_GET['id'] ?? 0;
$product = $productModel->getById($productId);

// Check if product exists and belongs to user
if (!$product || $product['user_id'] != $userId) {
    header('Location: /customer/products.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason'] ?? '');

    if (empty($reason)) {
        $error = 'Geef alstublieft een reden op voor de opzegging';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO cancellation_requests (user_id, product_id, reason, status)
                VALUES (?, ?, ?, 'pending')
            ");

            if ($stmt->execute([$userId, $productId, $reason])) {
                $success = 'Opzegverzoek succesvol ingediend. U ontvangt bericht zodra dit is verwerkt.';
                header('Location: /customer/products.php?cancelled=1');
                exit;
            } else {
                $error = 'Er is een fout opgetreden bij het indienen van het opzegverzoek';
            }
        } catch (Exception $e) {
            $error = 'Er is een fout opgetreden: ' . $e->getMessage();
        }
    }
}
$pageTitle = 'Product Opzeggen - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Product Opzeggen</h1>
        <a href="/customer/products.php" class="btn btn-secondary">Annuleren</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-section" style="max-width: 800px; margin: 0 auto;">
        <h2>Product Gegevens</h2>
        <div class="product-card">
            <div class="product-header">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <span class="badge badge-<?php echo $product['status']; ?>">
                    <?php echo ucfirst($product['status']); ?>
                </span>
            </div>

            <div class="product-details">
                <div class="detail-row">
                    <span class="label">Type:</span>
                    <span class="value"><?php echo htmlspecialchars($product['type_name']); ?></span>
                </div>

                <?php if ($product['domain_name']): ?>
                    <div class="detail-row">
                        <span class="label">Domeinnaam:</span>
                        <span class="value"><?php echo htmlspecialchars($product['domain_name']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="label">Verloopdatum:</span>
                    <span class="value"><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></span>
                </div>
            </div>
        </div>

        <div class="alert alert-warning" style="margin-top: 20px;">
            <strong>Let op:</strong> Door dit product op te zeggen, wordt het aan het einde van de looptijd beëindigd.
            Het product blijft actief tot de verloopdatum.
        </div>

        <h2 style="margin-top: 30px;">Opzegverzoek Indienen</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="reason">Reden voor opzegging *</label>
                <textarea id="reason" name="reason" rows="6" required
                    placeholder="Geef alstublieft de reden voor opzegging..."></textarea>
                <small>Deze informatie helpt ons onze dienstverlening te verbeteren</small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" required>
                    Ik begrijp dat dit product wordt opgezegd per de verloopdatum
                </label>
            </div>

            <button type="submit" class="btn btn-danger">Opzegging Bevestigen</button>
            <a href="/customer/products.php" class="btn btn-secondary">Annuleren</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>