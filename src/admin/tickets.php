<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Ticket.php';

$auth = new Auth();
$auth->requireAdmin();

$ticketModel = new Ticket();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $ticketId = $_POST['ticket_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        if ($ticketModel->updateStatus($ticketId, $status)) {
            $success = 'Ticket status bijgewerkt';
        } else {
            $error = 'Er is een fout opgetreden bij het bijwerken van de status';
        }
    }
}

$tickets = $ticketModel->getAll();
$stats = $ticketModel->getStatistics();
$pageTitle = 'Ticketbeheer - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <h1>Ticketbeheer</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 30px;">
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
        
        <div class="dashboard-section">
            <h2>Alle Tickets</h2>
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
                        <tr>
                            <td>#<?php echo $ticket['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                <br><small><?php echo htmlspecialchars($ticket['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="badge badge-<?php echo $ticket['status']; ?>" 
                                            style="border: none; padding: 4px 8px; cursor: pointer;">
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
                                <a href="/admin/ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">Bekijken</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
