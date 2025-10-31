<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Ticket.php';

$auth = new Auth();
$auth->requireCustomer();

$ticketModel = new Ticket();
$userId = $auth->getCurrentUserId();

$ticketId = $_GET['id'] ?? 0;
$ticket = $ticketModel->getById($ticketId);

// Check if ticket exists and belongs to user
if (!$ticket || $ticket['user_id'] != $userId) {
    header('Location: /customer/tickets.php');
    exit;
}

$messages = $ticketModel->getMessages($ticketId);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        $error = 'Vul een bericht in';
    } else {
        if ($ticketModel->addMessage($ticketId, $userId, $message, false)) {
            // Reopen ticket if closed
            if ($ticket['status'] === 'closed') {
                $ticketModel->updateStatus($ticketId, 'new');
            }
            $success = 'Bericht succesvol toegevoegd';
            // Refresh messages
            $messages = $ticketModel->getMessages($ticketId);
            $ticket = $ticketModel->getById($ticketId);
        } else {
            $error = 'Er is een fout opgetreden bij het toevoegen van het bericht';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket['id']; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/css/style.css">
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
        .message-content {
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Ticket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <a href="/customer/tickets.php" class="btn btn-secondary">Terug naar overzicht</a>
        </div>
        
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card">
                <p>Status</p>
                <h3><span class="badge badge-<?php echo $ticket['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                </span></h3>
            </div>
            <div class="stat-card">
                <p>Prioriteit</p>
                <h3><span class="badge badge-priority-<?php echo $ticket['priority']; ?>">
                    <?php echo ucfirst($ticket['priority']); ?>
                </span></h3>
            </div>
            <div class="stat-card">
                <p>Aangemaakt</p>
                <h3 style="font-size: 18px;"><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></h3>
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
        
        <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="dashboard-section">
                <h2>Antwoord Toevoegen</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="message">Uw bericht</label>
                        <textarea id="message" name="message" rows="6" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Bericht verzenden</button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Dit ticket is gesloten. Voeg een nieuw bericht toe om het ticket te heropenen.
            </div>
            <div class="dashboard-section">
                <h2>Ticket Heropenen</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="message">Uw bericht</label>
                        <textarea id="message" name="message" rows="6" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Ticket heropenen en bericht verzenden</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
