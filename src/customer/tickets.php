<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Ticket.php';

$auth = new Auth();
$auth->requireCustomer();

$ticketModel = new Ticket();
$userId = $auth->getCurrentUserId();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($subject) || empty($message)) {
        $error = 'Vul alle verplichte velden in';
    } else {
        try {
            $ticketId = $ticketModel->create($userId, $subject, $message, $priority);
            $success = 'Ticket succesvol aangemaakt';
            header('Location: /customer/ticket-detail.php?id=' . $ticketId);
            exit;
        } catch (Exception $e) {
            $error = 'Er is een fout opgetreden bij het aanmaken van het ticket';
        }
    }
}

$tickets = $ticketModel->getByUserId($userId);
$pageTitle = 'Tickets - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Mijn Tickets</h1>
            <button class="btn btn-primary" onclick="document.getElementById('newTicketForm').style.display='block'">
                Nieuw ticket
            </button>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div id="newTicketForm" style="display: none;" class="form-modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('newTicketForm').style.display='none'">&times;</span>
                <h2>Nieuw Ticket Aanmaken</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="subject">Onderwerp *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Prioriteit</label>
                        <select id="priority" name="priority">
                            <option value="low">Laag</option>
                            <option value="medium" selected>Normaal</option>
                            <option value="high">Hoog</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Bericht *</label>
                        <textarea id="message" name="message" rows="6" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Ticket aanmaken</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('newTicketForm').style.display='none'">
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
                                <a href="/customer/ticket-detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">Bekijken</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
    </table>
<?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
