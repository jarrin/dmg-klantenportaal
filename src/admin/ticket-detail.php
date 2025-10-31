<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Ticket.php';

$auth = new Auth();
$auth->requireAdmin();

$ticketModel = new Ticket();
$adminUserId = $auth->getCurrentUserId();

$ticketId = $_GET['id'] ?? 0;
$ticket = $ticketModel->getById($ticketId);

if (!$ticket) {
    header('Location: /admin/tickets.php');
    exit;
}

$messages = $ticketModel->getMessages($ticketId);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');
        
        if (empty($message)) {
            $error = 'Vul een bericht in';
        } else {
            if ($ticketModel->addMessage($ticketId, $adminUserId, $message, true)) {
                // Set status to in_progress if it was new
                if ($ticket['status'] === 'new') {
                    $ticketModel->updateStatus($ticketId, 'in_progress');
                }
                $success = 'Antwoord succesvol verzonden';
                // Refresh messages
                $messages = $ticketModel->getMessages($ticketId);
                $ticket = $ticketModel->getById($ticketId);
            } else {
                $error = 'Er is een fout opgetreden bij het verzenden van het antwoord';
            }
        }
    } elseif ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        
        if ($ticketModel->updateStatus($ticketId, $status)) {
            $success = 'Ticket status bijgewerkt';
            $ticket = $ticketModel->getById($ticketId);
        } else {
            $error = 'Er is een fout opgetreden bij het bijwerken van de status';
        }
    }
}
$pageTitle = 'Ticket #' . $ticket['id'] . ' - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
    .ticket-messages {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .message {
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
        border-left: 4px solid var(--border-color);
    }
    .message.staff-reply {
        background: #f0f9ff;
        border-left-color: var(--primary-color);
    }
    .message.customer-message {
        background: var(--light-color);
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .message-author {
            font-weight: 600;
            color: var(--dark-color);
        }
        .message-date {
            color: var(--secondary-color);
        }
    .message-body {
        line-height: 1.6;
    }
</style>

<div class="container">
        <div class="page-header">
            <h1>Ticket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <a href="/admin/tickets.php" class="btn btn-secondary">Terug naar overzicht</a>
        </div>
        
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card">
                <p>Klant</p>
                <h3 style="font-size: 18px;">
                    <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                </h3>
                <small><?php echo htmlspecialchars($ticket['email']); ?></small>
            </div>
            <div class="stat-card">
                <p>Status</p>
                <h3>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_status">
                        <select name="status" onchange="this.form.submit()" class="badge badge-<?php echo $ticket['status']; ?>" 
                                style="border: none; padding: 8px 12px; cursor: pointer; font-size: 14px;">
                            <option value="new" <?php echo $ticket['status'] === 'new' ? 'selected' : ''; ?>>Nieuw</option>
                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In behandeling</option>
                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Gesloten</option>
                        </select>
                    </form>
                </h3>
            </div>
            <div class="stat-card">
                <p>Prioriteit</p>
                <h3><span class="badge badge-priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span></h3>
            </div>
            <div class="stat-card">
                <p>Aangemaakt</p>
                <h3 style="font-size: 16px;"><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></h3>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="ticket-messages">
            <h2>Berichten</h2>
            <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['is_staff_reply'] ? 'staff-reply' : 'customer-message'; ?>">
                    <div class="message-header">
                        <span class="message-author">
                            <?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?>
                            <?php if ($message['role'] === 'admin'): ?>
                                <span class="badge badge-active">Staff</span>
                            <?php else: ?>
                                <span class="badge badge-info">Klant</span>
                            <?php endif; ?>
                        </span>
                        <span class="message-date"><?php echo date('d-m-Y H:i', strtotime($message['created_at'])); ?></span>
                    </div>
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="dashboard-section">
            <h2>Antwoord Toevoegen</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reply">
                <div class="form-group">
                    <label for="message">Uw antwoord</label>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Antwoord Verzenden</button>
            </form>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
