<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../controllers/admin/DashboardController.php';

$auth = new Auth();
$auth->requireAdmin();

$controller = new DashboardController();

// Get page data
$data = $controller->index();
extract($data);

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