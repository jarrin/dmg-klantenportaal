<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Product.php';

$auth = new Auth();
$auth->requireCustomer();

$productModel = new Product();
$userId = $auth->getCurrentUserId();
$products = $productModel->getByUserId($userId);
$pageTitle = 'Mijn Producten - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Mijn Producten</h1>
        <a href="/customer/request-product.php" class="btn btn-primary">Nieuw product aanvragen</a>
    </div>
    
    <?php if (!empty($products)): ?>
        <div class="product-search-box">
            <input type="text" id="productSearch" placeholder="Zoeken op productnaam, domeinnaam of type...">
        </div>
    <?php endif; ?>
    
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
                                <a href="/customer/cancel-product.php?id=<?php echo $product['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return openConfirmModal('Opzeggen product', 'Weet u zeker dat u dit product wilt opzeggen?', '/customer/cancel-product.php?id=<?php echo $product['id']; ?>')">
                                    Opzeggen
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
            <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<script>
    // Product search/filter
    const productSearch = document.getElementById('productSearch');
    if (productSearch) {
        productSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.querySelector('h3').textContent.toLowerCase();
                const type = product.textContent.toLowerCase();
                
                if (name.includes(searchTerm) || type.includes(searchTerm)) {
                    product.style.display = '';
                } else {
                    product.style.display = 'none';
                }
            });
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
