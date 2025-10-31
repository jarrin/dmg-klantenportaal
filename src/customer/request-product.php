<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

$auth = new Auth();
$auth->requireCustomer();

$productModel = new Product();
$userId = $auth->getCurrentUserId();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productTypeId = $_POST['product_type_id'] ?? 0;
    $requestedName = trim($_POST['requested_name'] ?? '');
    $requestedDomain = trim($_POST['requested_domain'] ?? '');
    $additionalInfo = trim($_POST['additional_info'] ?? '');
    
    if (empty($productTypeId) || empty($requestedName)) {
        $error = 'Vul alle verplichte velden in';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO product_requests (user_id, product_type_id, requested_name, requested_domain, additional_info, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            
            if ($stmt->execute([$userId, $productTypeId, $requestedName, $requestedDomain, $additionalInfo])) {
                $success = 'Product aanvraag succesvol ingediend. U ontvangt bericht zodra deze is verwerkt.';
            } else {
                $error = 'Er is een fout opgetreden bij het indienen van de aanvraag';
            }
        } catch (Exception $e) {
            $error = 'Er is een fout opgetreden: ' . $e->getMessage();
        }
    }
}

$productTypes = $productModel->getProductTypes();

// Get user's pending requests
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT pr.*, pt.name as type_name
    FROM product_requests pr
    JOIN product_types pt ON pr.product_type_id = pt.id
    WHERE pr.user_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();
$pageTitle = 'Product Aanvragen - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Product Aanvragen</h1>
            <a href="/customer/products.php" class="btn btn-secondary">Terug naar producten</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <div class="dashboard-section">
                <h2>Nieuwe Aanvraag</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="product_type_id">Product Type *</label>
                        <select id="product_type_id" name="product_type_id" required>
                            <option value="">-- Selecteer een product type --</option>
                            <?php foreach ($productTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['name']); ?>
                                    - €<?php echo htmlspecialchars($type['description'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="requested_name">Product Naam *</label>
                        <input type="text" id="requested_name" name="requested_name" 
                               placeholder="Bijv: Basis Webhosting" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="requested_domain">Domeinnaam (optioneel)</label>
                        <input type="text" id="requested_domain" name="requested_domain" 
                               placeholder="Bijv: mijnbedrijf.nl">
                        <small>Laat leeg indien niet van toepassing</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="additional_info">Aanvullende Informatie</label>
                        <textarea id="additional_info" name="additional_info" rows="4" 
                                  placeholder="Eventuele extra wensen of opmerkingen..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Aanvraag Indienen</button>
                </form>
            </div>
            
            <div class="dashboard-section">
                <h2>Mijn Aanvragen</h2>
                <?php if (empty($requests)): ?>
                    <p>U heeft nog geen aanvragen ingediend.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['requested_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $request['status']; ?>">
                                            <?php 
                                            $statusText = [
                                                'pending' => 'In behandeling',
                                                'approved' => 'Goedgekeurd',
                                                'rejected' => 'Afgewezen',
                                                'completed' => 'Voltooid'
                                            ];
                                            echo $statusText[$request['status']] ?? $request['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d-m-Y', strtotime($request['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
