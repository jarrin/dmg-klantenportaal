<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/TicketsController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new CustomerTicketsController($userId);

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

if ($result['redirect']) {
    header('Location: /views/customer/ticket-detail.php?id=' . $result['ticketId']);
    exit;
}

// Get page data
$data = $controller->index();
extract($data);

$pageTitle = 'Tickets - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Mijn Tickets</h1>
        <button class="btn btn-primary" onclick="document.getElementById('newTicketForm').classList.remove('hidden')">
            Nieuw ticket
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div id="newTicketForm" class="form-modal hidden">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('newTicketForm').classList.add('hidden')">&times;</span>
            <h2>Nieuw Ticket Aanmaken</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="subject">Onderwerp *</label>
                    <input type="text" id="subject" name="subject" required>
                </div>

                <div class="form-group">
                    <label for="message">Bericht *</label>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Ticket aanmaken</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newTicketForm').classList.add('hidden')">
                    Annuleren
                </button>
            </form>
        </div>
    </div>

    <?php if (empty($tickets)): ?>
        <div class="alert alert-info">
            U heeft nog geen tickets aangemaakt.
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="table-header">
                <h2>Mijn Tickets (<?php echo $paginator->getTotalItems(); ?>)</h2>
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
                        <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                        <td><span class="badge badge-<?php echo $ticket['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span></td>
                        <td><span class="badge badge-priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                        <td><?php echo $ticket['message_count']; ?></td>
                        <td><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></td>
                        <td>
                            <a href="/views/customer/ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">Bekijken</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $paginator->render('tickets.php', ['per_page' => $perPage]); ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>