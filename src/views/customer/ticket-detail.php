<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/TicketDetailController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();
$ticketId = $_GET['id'] ?? 0;

$controller = new CustomerTicketDetailController($userId);

// Handle POST requests
$result = $controller->handlePost($ticketId);
if ($result['redirect']) {
    header('Location: /views/customer/ticket-detail.php?id=' . $ticketId . '&success=1');
    exit;
}

// Get page data
$data = $controller->show($ticketId);

// Check if ticket exists and belongs to user
if (!$data) {
    header('Location: /views/customer/tickets.php');
    exit;
}

extract($data);

$success = '';
$error = $result['error'];

// Check for success parameter from redirect
if (isset($_GET['success'])) {
    $success = 'Bericht succesvol toegevoegd';
}

$pageTitle = 'Ticket #' . $ticket['id'] . ' - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Ticket #<?php echo $ticket['id']; ?>: <?php echo htmlspecialchars($ticket['subject']); ?></h1>
        <a href="/views/customer/tickets.php" class="btn btn-secondary">Terug naar overzicht</a>
    </div>

    <div class="stats-grid ticket-stats-grid">
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
        <div class="stat-card ticket-detail-stat">
            <p>Aangemaakt</p>
            <h3><?php echo date('d-m-Y H:i', strtotime($ticket['created_at'])); ?></h3>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="ticket-messages">
        <div class="ticket-details">
        <h2><?php echo htmlspecialchars($data['ticket']['subject']); ?></h2>
        <div class="ticket-attachment">
            <?php if (!empty($data['ticket']['attachment'])): ?>
            <?php 
                $file = '/uploads/tickets/' . rawurlencode(basename($data['ticket']['attachment']));
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            ?>
                <style>
                    .ticket-attachment {
                        background: var(--light-color);
                        padding: 15px;
                        border-radius: 8px;
                        border-left: 4px solid var(--border-color);
                        margin-top: 15px;
                        margin-bottom: 15px;
                    }

                    .ticket-attachment img {
                        max-width: 50%;
                        border-radius: 5px;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                        margin-top: 10px;
                    }

                    .ticket-attachment a.pdf-link {
                        display: inline-block;
                        margin-top: 10px;
                        font-weight: 600;
                        color: var(--primary-color);
                        text-decoration: none;
                    }

                    .ticket-attachment a.pdf-link:hover {
                        text-decoration: underline;
                    }
                </style>

                <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <img src="<?php echo $file; ?>" alt="Bijlage">
                <?php elseif ($ext === 'pdf'): ?>
                    <p>Bijlage (PDF): <a href="<?php echo $file; ?>" download class="pdf-link">Download PDF</a></p>
                <?php else: ?>
                    <p>Bijlage: <a href="<?php echo $file; ?>" download class="pdf-link">Download bestand</a></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

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

                <span class="message-date">
                    <?php echo date('d-m-Y H:i', strtotime($message['created_at'])); ?>
                </span>
            </div>

            <div class="message-content">
                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
            </div>

            <?php if (!empty($message['attachment'])): ?>
                <?php 
                    $file = $message['attachment'];
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                ?>

                <div class="message-attachment" style="margin-top:10px;">

                    <?php if (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                        <img src="<?php echo $file; ?>" 
                             alt="Bijlage" 
                             style="max-width:300px; border-radius:5px; margin-top: 10px; display:block;">

                    <?php elseif ($ext === 'pdf'): ?>
                        <p>PDF Bijlage: 
                            <a href="<?php echo $file; ?>" download>Download PDF</a>
                        </p>

                    <?php else: ?>
                        <p>Bijlage: 
                            <a href="<?php echo $file; ?>" download>Download bestand</a>
                        </p>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        </div>
    <?php endforeach; ?>
</div>



    <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="dashboard-section">
            <h2>Antwoord Toevoegen</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group full-width">
                    <label for="message">Uw bericht</label>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>

                <div class="form-group">
                    <label for="attachment">Bijlage toevoegen (optioneel)</label>
                    <input type="file" name="attachment" id="attachment" accept=".pdf,.png,.jpg,.jpeg,.docx">
                    <small>Max: 5MB. Alleen PDF, JPG, PNG, DOCX toegestaan.</small>
                </div>

                <button type="submit" class="btn btn-primary full-width">Bericht verzenden</button>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Dit ticket is gesloten. Voeg een nieuw bericht toe om het ticket te heropenen.
        </div>
        <div class="dashboard-section">
            <h2>Ticket Heropenen</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group full-width">
                    <label for="message">Uw bericht</label>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>

                <div class="form-group">
                    <label for="attachment">Bijlage toevoegen (optioneel)</label>
                    <input type="file" name="attachment" id="attachment" accept=".pdf,.png,.jpg,.jpeg,.docx">
                    <small>Max: 5MB. Alleen PDF, JPG, PNG, DOCX toegestaan.</small>
                </div>

                <button type="submit" class="btn btn-primary full-width">Ticket heropenen en bericht verzenden</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>