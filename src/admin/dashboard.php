<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Ticket.php';
require_once __DIR__ . '/../classes/Paginator.php';

$auth = new Auth();
$auth->requireAdmin();

$db = Database::getInstance()->getConnection();
$userModel = new User();
$productModel = new Product();
$ticketModel = new Ticket();

$totalUsers = count($userModel->getAll('customer'));
$totalProducts = count($productModel->getAll());
$ticketStats = $ticketModel->getStatistics();

// Pagination for expiring products
$expiringPage = isset($_GET['expiring_page']) ? max(1, (int)$_GET['expiring_page']) : 1;
$expiringPerPage = 10;

// Count expiring products
$countExpiringQuery = "SELECT COUNT(*) FROM products WHERE expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) AND expiry_date >= CURRENT_DATE AND status = 'active'";
$expiringPaginator = Paginator::fromQuery($db, $countExpiringQuery, [], $expiringPerPage, $expiringPage);

// Get expiring products with pagination
$stmt = $db->prepare("
    SELECT p.*, u.first_name, u.last_name 
    FROM products p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) 
    AND p.expiry_date >= CURRENT_DATE 
    AND p.status = 'active'
    ORDER BY p.expiry_date ASC 
    " . $expiringPaginator->getLimitClause()
);
$stmt->execute();
$expiringProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Admin Dashboard - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $totalUsers; ?></h3>
            <p>Totaal Klanten</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $totalProducts; ?></h3>
            <p>Totaal Producten</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $ticketStats['new_tickets']; ?></h3>
            <p>Nieuwe Tickets</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $ticketStats['in_progress']; ?></h3>
            <p>Tickets In Behandeling</p>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="table-container">
            <div class="table-header">
                <h2>Verlopen Producten (30 dagen) (<?php echo $expiringPaginator->getTotalItems(); ?>)</h2>
            </div>
            <?php if (empty($expiringProducts)): ?>
                <p>Geen producten verlopen binnen 30 dagen.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Klant</th>
                            <th>Verloopt op</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiringProducts as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></td>
                                <td>
                                    <a href="/admin/products.php?extend=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Verlengen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php echo $expiringPaginator->render('dashboard.php', [], 'expiring_page'); ?>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h2>Snelle Acties</h2>
            <div class="quick-actions">
                <a href="/admin/users.php?action=new" class="btn btn-primary">Nieuwe gebruiker toevoegen</a>
                <a href="/admin/products.php?action=new" class="btn btn-primary">Nieuw product toevoegen</a>
                <a href="/admin/tickets.php" class="btn btn-secondary">Alle tickets bekijken</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>