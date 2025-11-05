<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Paginator.php';

$auth = new Auth();
$auth->requireAdmin();

$productModel = new Product();
$userModel = new User();
$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $userId = $_POST['user_id'] ?? 0;
        $productTypeId = $_POST['product_type_id'] ?? 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $domainName = trim($_POST['domain_name'] ?? '');
        $registrationDate = $_POST['registration_date'] ?? date('Y-m-d');
        $durationMonths = $_POST['duration_months'] ?? 12;
        $price = $_POST['price'] ?? 0;

        if (empty($userId) || empty($productTypeId) || empty($name) || empty($price)) {
            $error = 'Vul alle verplichte velden in';
        } else {
            $expiryDate = date('Y-m-d', strtotime($registrationDate . ' + ' . $durationMonths . ' months'));

            $data = [
                'user_id' => $userId,
                'product_type_id' => $productTypeId,
                'name' => $name,
                'description' => $description,
                'domain_name' => $domainName,
                'registration_date' => $registrationDate,
                'expiry_date' => $expiryDate,
                'duration_months' => $durationMonths,
                'price' => $price,
                'status' => 'active'
            ];

            if ($productModel->create($data)) {
                $success = 'Product succesvol aangemaakt';
            } else {
                $error = 'Er is een fout opgetreden bij het aanmaken van het product';
            }
        }
    } elseif ($action === 'extend') {
        $productId = $_POST['product_id'] ?? 0;
        $months = $_POST['months'] ?? 12;

        if ($productModel->extendProduct($productId, $months)) {
            $success = 'Product succesvol verlengd met ' . $months . ' maanden';
        } else {
            $error = 'Er is een fout opgetreden bij het verlengen van het product';
        }
    } elseif ($action === 'cancel') {
        $productId = $_POST['product_id'] ?? 0;

        if ($productModel->cancelProduct($productId)) {
            $success = 'Product succesvol opgezegd';
        } else {
            $error = 'Er is een fout opgetreden bij het opzeggen van het product';
        }
    } elseif ($action === 'delete') {
        $productId = $_POST['product_id'] ?? 0;

        if ($productModel->delete($productId)) {
            $success = 'Product succesvol verwijderd';
        } else {
            $error = 'Er is een fout opgetreden bij het verwijderen van het product';
        }
    } elseif ($action === 'activate') {
        $productId = $_POST['product_id'] ?? 0;

        if ($productModel->activateProduct($productId)) {
            $success = 'Product succesvol geactiveerd';
        } else {
            $error = 'Er is een fout opgetreden bij het activeren van het product';
        }
    } elseif ($action === 'approve_request') {
        $requestId = $_POST['request_id'] ?? 0;

        // Get request details
        $stmt = $db->prepare("SELECT * FROM product_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if ($request) {
            $expiryDate = date('Y-m-d', strtotime('+12 months'));

            // Create product
            $data = [
                'user_id' => $request['user_id'],
                'product_type_id' => $request['product_type_id'],
                'name' => $request['requested_name'],
                'description' => $request['additional_info'],
                'domain_name' => $request['requested_domain'],
                'registration_date' => date('Y-m-d'),
                'expiry_date' => $expiryDate,
                'duration_months' => 12,
                'price' => 99.99,
                'status' => 'active'
            ];

            if ($productModel->create($data)) {
                // Update request status
                $stmt = $db->prepare("
                    UPDATE product_requests 
                    SET status = 'completed', processed_at = NOW(), processed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$auth->getCurrentUserId(), $requestId]);

                $success = 'Product aanvraag goedgekeurd en product aangemaakt';
            }
        }
    } elseif ($action === 'reject_request') {
        $requestId = $_POST['request_id'] ?? 0;

        $stmt = $db->prepare("
            UPDATE product_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ?
            WHERE id = ?
        ");

        if ($stmt->execute([$auth->getCurrentUserId(), $requestId])) {
            $success = 'Product aanvraag afgewezen';
        }
    } elseif ($action === 'approve_cancellation') {
        $cancellationId = $_POST['cancellation_id'] ?? 0;

        $stmt = $db->prepare("SELECT * FROM cancellation_requests WHERE id = ?");
        $stmt->execute([$cancellationId]);
        $cancellation = $stmt->fetch();

        if ($cancellation) {
            // Cancel the product
            $productModel->cancelProduct($cancellation['product_id']);

            // Update cancellation request
            $stmt = $db->prepare("
                UPDATE cancellation_requests 
                SET status = 'approved', processed_at = NOW(), processed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$auth->getCurrentUserId(), $cancellationId]);

            $success = 'Opzegverzoek goedgekeurd';
        }
    } elseif ($action === 'reject_cancellation') {
        $cancellationId = $_POST['cancellation_id'] ?? 0;

        $stmt = $db->prepare("
            UPDATE cancellation_requests 
            SET status = 'rejected', processed_at = NOW(), processed_by = ?
            WHERE id = ?
        ");

        if ($stmt->execute([$auth->getCurrentUserId(), $cancellationId])) {
            $success = 'Opzegverzoek afgewezen';
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 15;

// Count total products
$countQuery = "SELECT COUNT(*) FROM products";
$paginator = Paginator::fromQuery($db, $countQuery, [], $perPage, $page);

// Get products with pagination
$stmt = $db->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email, pt.name as type_name
    FROM products p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN product_types pt ON p.product_type_id = pt.id
    ORDER BY p.created_at DESC
    " . $paginator->getLimitClause()
);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = $userModel->getAll('customer');
$productTypes = $productModel->getProductTypes();

// Pagination for pending requests
$requestsPage = isset($_GET['requests_page']) ? max(1, (int)$_GET['requests_page']) : 1;
$requestsPerPage = 10;
$countRequestsQuery = "SELECT COUNT(*) FROM product_requests WHERE status = 'pending'";
$requestsPaginator = Paginator::fromQuery($db, $countRequestsQuery, [], $requestsPerPage, $requestsPage);

// Get pending requests with pagination
$stmt = $db->prepare("
    SELECT pr.*, pt.name as type_name, u.first_name, u.last_name, u.email
    FROM product_requests pr
    JOIN product_types pt ON pr.product_type_id = pt.id
    JOIN users u ON pr.user_id = u.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
    " . $requestsPaginator->getLimitClause()
);
$stmt->execute();
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination for pending cancellations
$cancellationsPage = isset($_GET['cancellations_page']) ? max(1, (int)$_GET['cancellations_page']) : 1;
$cancellationsPerPage = 10;
$countCancellationsQuery = "SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'";
$cancellationsPaginator = Paginator::fromQuery($db, $countCancellationsQuery, [], $cancellationsPerPage, $cancellationsPage);

// Get pending cancellations with pagination
$stmt = $db->prepare("
    SELECT cr.*, p.name as product_name, pt.name as type_name, u.first_name, u.last_name, u.email
    FROM cancellation_requests cr
    JOIN products p ON cr.product_id = p.id
    JOIN product_types pt ON p.product_type_id = pt.id
    JOIN users u ON cr.user_id = u.id
    WHERE cr.status = 'pending'
    ORDER BY cr.created_at DESC
    " . $cancellationsPaginator->getLimitClause()
);
$stmt->execute();
$pendingCancellations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Productbeheer - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Productbeheer</h1>
        <button class="btn btn-primary" onclick="document.getElementById('newProductForm').style.display='block'">
            Nieuw product
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Pending Requests -->
    <?php if ($requestsPaginator->getTotalItems() > 0): ?>
        <div class="table-container">
            <div class="table-header">
                <h2>In behandeling: Product Aanvragen (<?php echo $requestsPaginator->getTotalItems(); ?>)</h2>
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
                        <tr>
                            <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['requested_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
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
                        <tr>
                            <td><?php echo htmlspecialchars($cancellation['first_name'] . ' ' . $cancellation['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($cancellation['product_name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($cancellation['reason'], 0, 50)) . '...'; ?></td>
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
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['type_name']); ?></td>
                        <td><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></td>
                        <td>€<?php echo number_format($product['price'], 2, ',', '.'); ?></td>
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
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
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
                                <?php echo htmlspecialchars($type['name']); ?>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>