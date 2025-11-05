<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/admin/TicketsController.php';

$auth = new Auth();
$auth->requireAdmin();

$controller = new TicketsController();

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

// Get page data
$data = $controller->index();
extract($data);

// Get search parameter
$search = trim($_GET['search'] ?? '');

$pageTitle = 'Ticketbeheer - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <h1>Ticketbeheer</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Totaal Tickets</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['new_tickets']; ?></h3>
            <p>Nieuwe Tickets</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['in_progress']; ?></h3>
            <p>In Behandeling</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['closed']; ?></h3>
            <p>Gesloten</p>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2>Alle Tickets (<?php echo $paginator->getTotalItems(); ?>)</h2>
            <input 
                type="text" 
                id="ticketSearch" 
                class="search-box" 
                placeholder="Zoeken op onderwerp of klant..." 
                value="<?php echo htmlspecialchars($search); ?>"
                onkeyup="filterTickets(this.value)"
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
                    <th>Onderwerp</th>
                    <th>Status</th>
                    <th>Prioriteit</th>
                    <th>Berichten</th>
                    <th>Aangemaakt</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket): ?>
                    <tr class="ticket-row" data-search="<?php echo strtolower(htmlspecialchars($ticket['subject'] . ' ' . $ticket['first_name'] . ' ' . $ticket['last_name'] . ' ' . $ticket['email'])); ?>">
                        <td>#<?php echo $ticket['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                            <br><small><?php echo htmlspecialchars($ticket['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                <select name="status" onchange="this.form.submit()" class="badge badge-<?php echo $ticket['status']; ?>"
                                    class="btn-icon">
                                    <option value="new" <?php echo $ticket['status'] === 'new' ? 'selected' : ''; ?>>Nieuw</option>
                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In behandeling</option>
                                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Gesloten</option>
                                </select>
                            </form>
                        </td>
                        <td><span class="badge badge-priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                        <td><?php echo $ticket['message_count']; ?></td>
                        <td><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></td>
                        <td>
                            <a href="/views/admin/ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">Bekijken</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $paginator->render('tickets.php', ['per_page' => $perPage]); ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>