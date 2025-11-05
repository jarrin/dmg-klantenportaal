<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/ProductsController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new CustomerProductsController($userId);

// Get page data
$data = $controller->index();
extract($data);

$pageTitle = 'Mijn Producten - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Mijn Producten (<?php echo $paginator->getTotalItems(); ?>)</h1>
        <div class="flex-gap-center">
            <div class="per-page-selector">
                <label>Toon:</label>
                <select onchange="window.location.href='?per_page='+this.value+'&page=1'">
                    <option value="12" <?php echo $perPage == 12 ? 'selected' : ''; ?>>12</option>
                    <option value="24" <?php echo $perPage == 24 ? 'selected' : ''; ?>>24</option>
                    <option value="36" <?php echo $perPage == 36 ? 'selected' : ''; ?>>36</option>
                    <option value="48" <?php echo $perPage == 48 ? 'selected' : ''; ?>>48</option>
                </select>
            </div>
            <a href="/views/customer/request-product.php" class="btn btn-primary">Nieuw product aanvragen</a>
        </div>
    </div>

    <?php if (empty($products)): ?>
        <div class="alert alert-info">
            U heeft momenteel geen producten. Klik op "Nieuw product aanvragen" om een product aan te vragen.
        </div>
    <?php else: ?>
        <div class="products-grid" id="productsContainer">
            <?php foreach ($products as $product): ?>
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
                            <span class="label">Registratiedatum:</span>
                            <span class="value"><?php echo date('d-m-Y', strtotime($product['registration_date'])); ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="label">Verloopdatum:</span>
                            <span class="value"><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="label">Looptijd:</span>
                            <span class="value"><?php echo $product['duration_months']; ?> maanden</span>
                        </div>

                        <div class="detail-row">
                            <span class="label">Prijs:</span>
                            <span class="value">€ <?php echo number_format($product['price'], 2, ',', '.'); ?></span>
                        </div>
                    </div>

                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($product['status'] === 'active'): ?>
                        <div class="product-actions">
                            <a href="/views/customer/cancel-product.php?id=<?php echo $product['id']; ?>"
                                class="btn btn-danger btn-sm">
                                Opzeggen
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php echo $paginator->render('products.php', ['per_page' => $perPage]); ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>