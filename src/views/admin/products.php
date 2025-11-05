<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/admin/ProductsController.php';

$auth = new Auth();
$auth->requireAdmin();

$controller = new ProductsController($auth);

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

// Get page data
$data = $controller->index();
extract($data);

// Get search parameters
$searchRequests = trim($_GET['search_requests'] ?? '');
$searchCancellations = trim($_GET['search_cancellations'] ?? '');
$searchProducts = trim($_GET['search_products'] ?? '');

$pageTitle = 'Productbeheer - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<?php
// Defensive defaults in case controller didn't provide them (prevents undefined variable warnings)
$page = $page ?? 1;
$perPage = $perPage ?? 15;
$requestsPage = $requestsPage ?? 1;
$cancellationsPage = $cancellationsPage ?? 1;
?>

<div class="container">
    <div class="page-header">
        <h1>Productbeheer</h1>
        <button class="btn btn-primary" onclick="document.getElementById('newProductForm').style.display='block'">
            Nieuw product
        </button>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success ?? ''); ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error ?? ''); ?></div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <?php if ($requestsPaginator->getTotalItems() > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h2>In behandeling: Product Aanvragen (<?php echo $requestsPaginator->getTotalItems(); ?>)</h2>
                <input 
                    type="text" 
                    id="requestSearch" 
                    class="search-box" 
                    placeholder="Zoeken op naam..." 
                    value="<?php echo htmlspecialchars($searchRequests); ?>"
                    onkeyup="filterRows('request-row', this.value)"
                >
                <div class="table-actions">
                    <div class="per-page-selector">
                        <label>Toon:</label>
                        <select onchange="window.location.href='?per_page='+this.value">
                            <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                            <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Klant</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Domeinnaam</th>
                        <th>Datum</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $request): ?>
                        <tr class="request-row" data-search="<?php echo strtolower(htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''))); ?>">
                            <td><?php echo htmlspecialchars(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($request['requested_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($request['requested_domain'] ?? '-'); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($request['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Goedkeuren</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject_request">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Afwijzen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php 
                // Preserve other pagination states
                $requestsParams = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'cancellations_page' => $cancellationsPage
                ];
                echo $requestsPaginator->render('products.php', $requestsParams, 'requests_page'); 
            ?>
        </div>
    <?php endif; ?>

    <!-- Pending Cancellations -->
    <?php if ($cancellationsPaginator->getTotalItems() > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h2>In behandeling: Opzegverzoeken (<?php echo $cancellationsPaginator->getTotalItems(); ?>)</h2>
                <input 
                    type="text" 
                    id="cancellationSearch" 
                    class="search-box" 
                    placeholder="Zoeken op naam of product..." 
                    value="<?php echo htmlspecialchars($searchCancellations); ?>"
                    onkeyup="filterRows('cancellation-row', this.value)"
                >
                <div class="table-actions">
                    <div class="per-page-selector">
                        <label>Toon:</label>
                        <select onchange="window.location.href='?per_page='+this.value">
                            <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                            <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Klant</th>
                        <th>Product</th>
                        <th>Reden</th>
                        <th>Datum</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingCancellations as $cancellation): ?>
                        <tr class="cancellation-row" data-search="<?php echo strtolower(htmlspecialchars(($cancellation['first_name'] ?? '') . ' ' . ($cancellation['last_name'] ?? '') . ' ' . ($cancellation['product_name'] ?? ''))); ?>">
                            <td><?php echo htmlspecialchars(($cancellation['first_name'] ?? '') . ' ' . ($cancellation['last_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($cancellation['product_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(substr($cancellation['reason'] ?? '', 0, 50)) . '...'; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($cancellation['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_cancellation">
                                    <input type="hidden" name="cancellation_id" value="<?php echo $cancellation['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">Goedkeuren</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject_cancellation">
                                    <input type="hidden" name="cancellation_id" value="<?php echo $cancellation['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Afwijzen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            // Preserve other pagination states
            $cancellationsParams = [
                'page' => $page,
                'per_page' => $perPage,
                'requests_page' => $requestsPage
            ];
            echo $cancellationsPaginator->render('products.php', $cancellationsParams, 'cancellations_page');
            ?>
        </div>
    <?php endif; ?>

    <!-- All Products -->
    <div class="table-container">
        <div class="table-header">
            <h2>Alle Producten (<?php echo $paginator->getTotalItems(); ?>)</h2>
            <input 
                type="text" 
                id="productSearch" 
                class="search-box" 
                placeholder="Zoeken op naam of klant..." 
                value="<?php echo htmlspecialchars($searchProducts); ?>"
                onkeyup="filterRows('product-row', this.value)"
            >
            <div class="table-actions">
                <div class="per-page-selector">
                    <label>Toon:</label>
                    <select onchange="window.location.href='?per_page='+this.value">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Klant</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Verloopt</th>
                    <th>Prijs</th>
                    <th>Status</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="product-row" data-search="<?php echo strtolower(htmlspecialchars(($product['name'] ?? '') . ' ' . ($product['first_name'] ?? '') . ' ' . ($product['last_name'] ?? ''))); ?>">
                                <td><?php echo $product['id']; ?></td>
                                <td><?php echo htmlspecialchars(($product['first_name'] ?? '') . ' ' . ($product['last_name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($product['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($product['type_name'] ?? ''); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></td>
                                <td>€<?php echo number_format($product['price'] ?? 0, 2, ',', '.'); ?></td>
                        <td><span class="badge badge-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                        <td>
                            <?php if ($product['status'] === 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="extend">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <select name="months" class="btn btn-sm btn-primary">
                                        <option value="1">1 maand</option>
                                        <option value="3">3 maanden</option>
                                        <option value="6">6 maanden</option>
                                        <option value="12" selected>12 maanden</option>
                                        <option value="24">24 maanden</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Verlengen</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary">Opzeggen</button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Weet u zeker dat u dit product wilt verwijderen?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Verwijderen</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($product['status'] !== 'active'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Weet u zeker dat u dit product wilt verwijderen?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Verwijderen</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="activate">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="btn btn-sm badge-active">Activieren</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php 
        // Preserve other pagination states for main products
        $mainParams = [
            'per_page' => $perPage,
            'requests_page' => $requestsPage,
            'cancellations_page' => $cancellationsPage
        ];
        echo $paginator->render('products.php', $mainParams, 'page'); 
        ?>
    </div>

    <!-- New Product Modal -->
    <div id="newProductForm" style="display: none;" class="form-modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('newProductForm').style.display='none'">&times;</span>
            <h2>Nieuw Product Toevoegen</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="user_id">Klant *</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- Selecteer een klant --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' (' . ($user['email'] ?? '') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="product_type_id">Product Type *</label>
                    <select id="product_type_id" name="product_type_id" required>
                        <option value="">-- Selecteer een type --</option>
                        <?php foreach ($productTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="name">Product Naam *</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="description">Beschrijving</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="domain_name">Domeinnaam</label>
                    <input type="text" id="domain_name" name="domain_name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="registration_date">Registratiedatum *</label>
                        <input type="date" id="registration_date" name="registration_date"
                            value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="duration_months">Looptijd (maanden) *</label>
                        <input type="number" id="duration_months" name="duration_months" value="12" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="price">Prijs (€) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>

                <button type="submit" class="btn btn-primary">Product Aanmaken</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newProductForm').style.display='none'">
                    Annuleren
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>