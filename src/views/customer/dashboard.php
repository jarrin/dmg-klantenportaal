<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/DashboardController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new CustomerDashboardController($userId);

// Get page data
$data = $controller->index();
extract($data);

$pageTitle = 'Dashboard - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $totalProducts; ?></h3>
            <p>Totaal Producten</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $activeProducts; ?></h3>
            <p>Actieve Producten</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $openTickets; ?></h3>
            <p>Open Tickets</p>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Recente Producten</h2>
            <?php if (empty($products)): ?>
                <p>U heeft nog geen producten.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Verloopt op</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($products, 0, 5) as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['type_name']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($product['expiry_date'])); ?></td>
                                <td><span class="badge badge-<?php echo $product['status']; ?>"><?php echo ucfirst($product['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="/views/customer/products.php" class="btn btn-secondary">Alle producten</a>
            <?php endif; ?>
        </div>
        
        <div class="dashboard-section">
            <h2>Recente Tickets</h2>
            <?php if (empty($tickets)): ?>
                <p>U heeft nog geen tickets aangemaakt.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Onderwerp</th>
                            <th>Status</th>
                            <th>Aangemaakt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($tickets, 0, 5) as $ticket): ?>
                            <tr>
                                <td><a href="/customer/ticket-detail.php?id=<?php echo $ticket['id']; ?>"><?php echo htmlspecialchars($ticket['subject']); ?></a></td>
                                <td><span class="badge badge-<?php echo $ticket['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span></td>
                                <td><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="/views/customer/tickets.php" class="btn btn-secondary">Alle tickets</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
